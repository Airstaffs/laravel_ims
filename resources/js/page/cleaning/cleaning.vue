<template>
    <div class="vue-container cleaning-module">
        <TitlePage
            title="Cleaning Module"
            subtitle="Track and manage products requiring physical cleaning, refurbishment, or data clean-up before being staged for sale."
        />

        <!-- Desktop Table Container -->
        <AnimateDiv :delay="200" class="p-4">
            <XDataTable
                :value="sortedInventory"
                :loading="loading"
                :columns="visibleColumns"
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

                        <!-- Use regular product images as fallback -->
                        <div
                            v-else-if="data.img1 && data.img1 !== 'NULL'"
                            class="gallery-thumbnail position-relative"
                            @click="openImageModal(data)"
                            style="cursor: pointer"
                        >
                            <img
                                :src="`/images/thumbnails/${data.img1}`"
                                :alt="getDisplayTitle(item)"
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

                <template #datedelivered="{ data }">
                    {{ convertToLocalDate(data.datedelivered) }}
                </template>

                <template #actions="{ data }">
                    <div class="d-flex flex-column align-items-start">
                        <Button
                            size="small"
                            severity="success"
                            variant="text"
                            label="Release Condition"
                            icon="pi pi-check-circle"
                            @click="openConditionModal(data)"
                            class="text-success"
                        />
                        <Button
                            size="small"
                            severity="contrast"
                            variant="text"
                            icon="pi pi-info-circle"
                            label="Details"
                            class="text-primary"
                            @click="openEditModal(data)"
                        />
                        <Button
                            size="small"
                            severity="contrast"
                            variant="text"
                            label="Cleaning - Work Log"
                            icon="pi pi-sparkles"
                            class="text-info"
                            @click="openCleaningWorkLog(data)"
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
                                <p>RT# : {{ item.rtcounter }}</p>
                                <span>{{ getDisplayTitle(item) }}</span>
                            </h6>
                        </div>
                    </div>

                    <hr />

                    <div class="mobile-card-details">
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">Added date:</span>
                            <span class="mobile-detal-value">
                                {{ localDeliveredDate }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label"
                                >Updated date:</span
                            >
                            <span class="mobile-detal-value">
                                {{ item.lastDateUpdate }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">FNSKU:</span>
                            <span class="mobile-detal-value">
                                {{ item.FNSKUviewer }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">MSKU:</span>
                            <span class="mobile-detal-value">
                                {{ item.MSKUviewer }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">ASIN:</span>
                            <span class="mobile-detal-value">
                                {{ item.ASIN }}</span
                            >
                        </div>
                        <!-- Insert Hidden Here -->
                        <div class="mobile-detail-row mb-2" v-if="showDetails">
                            <span class="mobile-detail-label">FBM:</span>
                            <span class="mobile-detal-value">
                                {{ item.FBMAvailable }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2" v-if="showDetails">
                            <span class="mobile-detail-label">FBA:</span>
                            <span class="mobile-detal-value">
                                {{ item.FbaAvailable }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2" v-if="showDetails">
                            <span class="mobile-detail-label">Outbound:</span>
                            <span class="mobile-detal-value">
                                {{ item.Outbound }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2" v-if="showDetails">
                            <span class="mobile-detail-label">Inbound:</span>
                            <span class="mobile-detal-value">
                                {{ item.Inbound }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2" v-if="showDetails">
                            <span class="mobile-detail-label"
                                >Unfulfillable:</span
                            >
                            <span class="mobile-detal-value">
                                {{ item.Unfulfillable }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2" v-if="showDetails">
                            <span class="mobile-detail-label">Reserved:</span>
                            <span class="mobile-detal-value">
                                {{ item.Reserved }}</span
                            >
                        </div>
                        <!--  -->
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label"
                                >Fullfilment:</span
                            >
                            <span class="mobile-detal-value">
                                {{ item.Fulfilledby }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">Status:</span>
                            <span class="mobile-detal-value">
                                {{ item.status }}</span
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

                    <div class="mobile-card-actions">
                        <Button
                            @click="openConditionModal(item)"
                            icon="pi pi-check-circle"
                            size="small"
                            severity="success"
                            label="Release Condition"
                            :style="{ width: '100%', marginBottom: '0.5rem' }"
                        />
                        <Button
                            @click="openEditModal(item)"
                            icon="pi pi-info-circle"
                            size="small"
                            severity="info"
                            label="More Details"
                            :style="{ width: '100%' }"
                        />
                    </div>

                    <hr v-if="expandedRows[index]" />

                    <div
                        v-if="expandedRows[index]"
                        class="mobile-expanded-content"
                    >
                        <p><strong>Expanded Rows Here</strong></p>
                        <p>
                            <strong>Product Name:</strong>
                            {{ getFnskuDisplayTitle(data) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination with centered layout -->
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

        <!-- Receive & Release Condition Modal -->
        <ReceiveReleaseConditionModal
            v-model:visible="showConditionModal"
            :item="selectedItem"
            @saved="handleConditionSaved"
        />

        <Dialog
            class="view-modal"
            v-model:visible="showEditModal"
            modal
            :header="`RT # ${item.rtcounter} - ${getDisplayTitle(item)}`"
            style="width: 110rem"
            :pt="{
                root: { class: 'mobile-fullscreen-dialog' },
            }"
        >
            <div class="modal-body">
                <div class="view-info-container">
                    <div class="view-grid-wrapper">
                        <div class="form-col-left">
                            <gallery :item="item" />
                            <Card>
                                <template #title>
                                    <h5 class="text-primary fw-bolder">
                                        Description
                                    </h5>
                                </template>
                                <template #content>
                                    <p
                                        style="
                                            word-break: break-all;
                                            max-height: 450px;
                                            overflow-y: auto;
                                        "
                                    >
                                        {{ item.description }}
                                    </p>
                                </template>
                            </Card>
                        </div>

                        <div class="form-col-right">
                            <div class="row">
                                <div class="col-lg-6">
                                    <section class="info-section">
                                        <h3 class="text-primary fw-bolder">
                                            Warehouse & Tracking
                                        </h3>
                                        <dl class="info-list">
                                            <div class="info-item">
                                                <dt>Module:</dt>
                                                <dd>
                                                    {{ item.ProductModuleLoc }}
                                                </dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Warehouse Location:</dt>
                                                <dd>
                                                    {{ item.warehouselocation }}
                                                </dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Serial Number:</dt>
                                                <dd>
                                                    {{ item.serialnumber }}
                                                </dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Tracking Number:</dt>
                                                <dd>
                                                    {{ item.trackingnumber }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </section>

                                    <section class="info-section">
                                        <h3 class="text-primary fw-bolder">
                                            Product Identifiers
                                        </h3>
                                        <dl class="info-list">
                                            <div class="info-item">
                                                <dt>RT:</dt>
                                                <dd>
                                                    {{ item.ProductID }}
                                                </dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>ASIN:</dt>
                                                <dd>{{ item.ASIN }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>RPN:</dt>
                                                <dd>{{ item.RPN }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>PRD:</dt>
                                                <dd>{{ item.PRD }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>UPC:</dt>
                                                <dd>{{ item.UPC }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>EAN:</dt>
                                                <dd>{{ item.EAN }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>FNSKU:</dt>
                                                <dd>{{ item.FNSKU }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>SKU:</dt>
                                                <dd>{{ item.SKU }}</dd>
                                            </div>
                                        </dl>
                                    </section>

                                    <section class="info-section">
                                        <h3 class="text-primary fw-bolder">
                                            Order Information
                                        </h3>
                                        <dl class="info-list">
                                            <div class="info-item">
                                                <dt>Order Number:</dt>
                                                <dd>{{ item.rtid }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Item Number:</dt>
                                                <dd>
                                                    {{ item.itemnumber }}
                                                </dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Basket Number:</dt>
                                                <dd>
                                                    {{ item.basketnumber }}
                                                </dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Order Date:</dt>
                                                <dd>
                                                    {{ localOrderDate }}
                                                </dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Delivered Date:</dt>
                                                <dd>
                                                    {{ localDeliveredDate }}
                                                </dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Seller:</dt>
                                                <dd>{{ item.seller }}</dd>
                                            </div>
                                        </dl>
                                    </section>

                                    <section
                                        class="info-section"
                                        v-if="item.grading || item.notes"
                                    >
                                        <h3 class="text-primary fw-bolder">
                                            Additional Info
                                        </h3>
                                        <dl class="info-list">
                                            <div
                                                class="info-item"
                                                v-if="item.grading"
                                            >
                                                <dt>Grading:</dt>
                                                <dd>{{ item.grading }}</dd>
                                            </div>
                                            <div
                                                class="info-item"
                                                v-if="item.notes"
                                            >
                                                <dt>Notes:</dt>
                                                <dd>{{ item.notes }}</dd>
                                            </div>
                                        </dl>
                                    </section>
                                </div>

                                <div
                                    class="col-lg-6"
                                    v-show="showPricingSection"
                                >
                                    <section class="pricing-section">
                                        <h3 class="text-primary fw-bolder">
                                            Pricing
                                        </h3>
                                        <dl class="pricing-list">
                                            <div class="pricing-item">
                                                <dt>Unit Price:</dt>
                                                <dd>
                                                    {{
                                                        item.formattedUnitprice ||
                                                        "0.00"
                                                    }}
                                                </dd>
                                            </div>
                                            <div class="pricing-item">
                                                <dt>Quantity:</dt>
                                                <dd>
                                                    {{ item.quantity || 0 }}
                                                </dd>
                                            </div>
                                            <div
                                                class="pricing-item subtotal-line"
                                            >
                                                <dt>Subtotal:</dt>
                                                <dd>
                                                    {{ item.price || "0.00" }}
                                                </dd>
                                            </div>

                                            <div
                                                class="pricing-item"
                                                v-if="item.Discount"
                                            >
                                                <dt>Discount:</dt>
                                                <dd class="discount">
                                                    -{{ item.Discount }}
                                                </dd>
                                            </div>
                                            <div class="pricing-item">
                                                <dt>Tax:</dt>
                                                <dd>{{ item.tax }}</dd>
                                            </div>
                                            <div class="pricing-item">
                                                <dt>Shipping:</dt>
                                                <dd>
                                                    {{ item.priceshipping }}
                                                </dd>
                                            </div>

                                            <div
                                                class="pricing-item total-line"
                                            >
                                                <dt>Total Price:</dt>
                                                <dd class="total-amount">
                                                    {{ grandTotal }}
                                                </dd>
                                            </div>

                                            <div
                                                class="pricing-item refund-line"
                                                v-if="item.refund"
                                            >
                                                <dt>Refund:</dt>
                                                <dd class="refund">
                                                    {{ item.refund }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </section>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Dialog>

        <Dialog
            v-model:visible="showCleaningWorkLog"
            modal
            :header="`Cleaning Work Log — ${cleaningWorkLogItem?.rtcounter || ''}`"
            :style="{ width: '860px', maxWidth: '98vw' }"
        >
            <div v-if="cleaningWorkLogItem" class="cwl-wrapper">
                <!-- ── AUTO-FETCH: System Pre-filled Fields ─────────────── -->
                <div class="cwl-autofetch-section">
                    <div class="cwl-autofetch-header">
                        <span class="cwl-autofetch-badge">AUTO-FETCH</span>
                        <span class="cwl-autofetch-title"
                            >System Pre-filled Fields</span
                        >
                    </div>
                    <div class="cwl-autofetch-grid">
                        <div class="cwl-autofetch-card">
                            <span class="cwl-autofetch-label"
                                >Date Cleaned</span
                            >
                            <span class="cwl-autofetch-value">{{
                                cleaningDateTime
                            }}</span>
                        </div>
                        <div class="cwl-autofetch-card">
                            <span class="cwl-autofetch-label"
                                >Serial Number</span
                            >
                            <span class="cwl-autofetch-value">{{
                                cleaningWorkLogItem.serialnumber || "—"
                            }}</span>
                        </div>
                        <div class="cwl-autofetch-card">
                            <span class="cwl-autofetch-label">Cleaned By</span>
                            <span class="cwl-autofetch-value">{{
                                cleaningWorkLogItem.received_by ||
                                cleaningWorkLogItem.Username ||
                                currentUser ||
                                "—"
                            }}</span>
                        </div>
                        <div class="cwl-autofetch-card">
                            <span class="cwl-autofetch-label">ASIN</span>
                            <span class="cwl-autofetch-value">{{
                                cleaningWorkLogItem.ASINviewer ||
                                cleaningWorkLogItem.ASIN ||
                                "—"
                            }}</span>
                        </div>
                    </div>
                </div>

                <!-- ── ASIN-BASED: Cosmetic Work Categories ─────────────── -->
                <div class="cwl-asin-section">
                    <div class="cwl-asin-header">
                        <span class="cwl-asin-badge">ASIN-BASED</span>
                        <span class="cwl-asin-title"
                            >Cosmetic Work Categories (Auto-loaded from
                            ASIN)</span
                        >
                    </div>

                    <!-- No categories configured -->
                    <div
                        v-if="!cleaningWorkLogCategories.length"
                        class="cwl-asin-empty"
                    >
                        <div class="cwl-dynamic-hint">
                            <p class="cwl-hint-title">
                                <strong>Dynamic Checklist:</strong> Based on the
                                ASIN assigned during Labelling
                            </p>
                            <div class="cwl-hint-example">
                                <p>
                                    No cleaning categories configured for this
                                    ASIN.
                                </p>
                                <p>
                                    Set them up in
                                    <strong
                                        >ASIN Configuration → Cleaning
                                        Module</strong
                                    >.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic checklist hint when categories exist -->
                    <div v-else class="cwl-dynamic-hint">
                        <p class="cwl-hint-title">
                            <strong>Dynamic Checklist:</strong> Based on the
                            ASIN assigned during Labelling
                        </p>
                        <div class="cwl-hint-example">
                            <p>
                                Example for ASIN
                                <strong>{{
                                    cleaningWorkLogItem.ASINviewer ||
                                    cleaningWorkLogItem.ASIN
                                }}</strong
                                >:
                            </p>
                            <ul>
                                <li
                                    v-for="(
                                        cat, i
                                    ) in cleaningWorkLogCategories"
                                    :key="'hint-' + i"
                                >
                                    {{ cat.name }}
                                    <span
                                        v-if="cat._fromGlobal"
                                        class="cwl-global-badge"
                                        >Global</span
                                    >
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- ── Category cards ────────────────────────────────────── -->
                <div
                    v-if="cleaningWorkLogCategories.length"
                    class="cwl-categories"
                >
                    <div
                        v-for="(cat, ci) in cleaningWorkLogCategories"
                        :key="'cat-' + ci"
                        class="cwl-category-card"
                    >
                        <!-- Category name -->
                        <div class="cwl-category-header">
                            <span class="cwl-category-name">{{
                                cat.name
                            }}</span>
                            <span
                                v-if="cat._fromGlobal"
                                class="cwl-global-badge"
                                >Global</span
                            >
                        </div>

                        <!-- Status dropdown -->
                        <select
                            v-model="
                                cleaningWorkLogValues[cat.name + '__status']
                            "
                            class="cwl-select"
                            @change="onCleaningStatusChange(cat)"
                        >
                            <option value="">Select status</option>
                            <option value="Not Required">Not Required</option>
                            <option value="Done">Done</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Needs Attention">
                                Needs Attention
                            </option>
                        </select>

                        <!-- Notes textarea — auto-fills based on action notes -->
                        <textarea
                            v-model="
                                cleaningWorkLogValues[cat.name + '__notes']
                            "
                            class="cwl-textarea"
                            :placeholder="
                                getCleaningNotePlaceholder(
                                    cat,
                                    cleaningWorkLogValues[
                                        cat.name + '__status'
                                    ],
                                )
                            "
                            rows="3"
                        />

                        <!-- Actions checklist if configured -->
                        <div
                            v-if="cat.actions?.length"
                            class="cwl-actions-list"
                        >
                            <div
                                v-for="(action, ai) in cat.actions"
                                :key="'action-' + ci + '-' + ai"
                                class="cwl-action-item"
                            >
                                <input
                                    type="checkbox"
                                    :id="`cwl-action-${ci}-${ai}`"
                                    v-model="
                                        cleaningWorkLogValues[
                                            cat.name +
                                                '__action__' +
                                                action.title
                                        ]
                                    "
                                    class="cwl-checkbox"
                                />
                                <label
                                    :for="`cwl-action-${ci}-${ai}`"
                                    class="cwl-action-label"
                                >
                                    <span class="cwl-action-title">{{
                                        action.title
                                    }}</span>
                                    <span
                                        v-if="action.description"
                                        class="cwl-action-desc"
                                        >{{ action.description }}</span
                                    >
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── COMPLETION: Mark as Done ──────────────────────────── -->
                <div class="cwl-completion-section">
                    <div class="cwl-completion-header">
                        <span class="cwl-completion-badge">COMPLETION</span>
                        <span class="cwl-completion-title">Mark as Done</span>
                    </div>
                    <div class="cwl-completion-card">
                        <div class="cwl-completion-text">
                            <p class="cwl-completion-main">
                                All cleaning tasks completed?
                            </p>
                            <p class="cwl-completion-sub">
                                Item will proceed to Packaging module
                            </p>
                        </div>
                        <Button
                            label="Done Cleaning"
                            icon="pi pi-check"
                            class="cwl-done-btn"
                            :loading="savingCleaningWorkLog"
                            @click="saveCleaningWorkLog(true)"
                        />
                    </div>
                </div>
            </div>

            <template #footer>
                <Button
                    label="Cancel"
                    severity="secondary"
                    text
                    @click="showCleaningWorkLog = false"
                />
                <Button
                    label="Save Progress"
                    icon="pi pi-save"
                    severity="secondary"
                    outlined
                    :loading="savingCleaningWorkLog"
                    @click="saveCleaningWorkLog(false)"
                />
            </template>
        </Dialog>

        <ScrollTop />
    </div>
</template>

<script>
import { Button, Card, Dialog, ScrollTop, Select, Paginator } from "primevue";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import TableGallery from "../../components/Gallery/tableGallery.vue";
import Cleaning from "./cleaning.js";
import Gallery from "../../components/Gallery/gallery.vue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import ViewImageGalleryModal from "../../components/ViewImageGalleryModal/ViewImageGalleryModal.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";
import ReceiveReleaseConditionModal from "./modals/receiveAndReleaseCondition_modal.vue";
import { ROWS_PER_PAGE } from "../../constant.js";
import { showPricingForPH } from "../../utils/helpers.js";

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
        field: "ASIN",
        header: "ASIN",
        sortable: true,
        bodyStyle: "font-size: 14px;",
    },
    {
        field: "price",
        header: "Price",
        sortable: true,
        bodyStyle: "font-size: 14px;",
        visibility: showPricingForPH(),
    },
    {
        field: "serialNumber",
        header: "Serial Number",
        sortable: true,
        bodyStyle: "font-size: 14px;",
    },
    {
        field: "trackingnumber",
        header: "Tracking Number",
        sortable: true,
        bodyStyle: "font-size: 14px;",
    },
    {
        field: "datedelivered",
        header: "Date Delivered",
        sortable: true,
        bodyStyle: "font-size: 14px;",
    },
];

export default {
    mixins: [Cleaning],
    components: {
        XDataTable,
        TableGallery,
        Button,
        Gallery,
        Dialog,
        Card,
        ScrollTop,
        TitlePage,
        ViewImageGalleryModal,
        AnimateDiv,
        Select,
        ReceiveReleaseConditionModal,
        Paginator,
    },
    data() {
        return {
            columns: TABLE_COLUMNS,
            rowsPerPage: ROWS_PER_PAGE,
            showConditionModal: false,
            selectedItem: null,
            currentTimezone: "UTC",
            timezoneLabel: "Loading...",
            showPricingSection: showPricingForPH(),
        };
    },
    async mounted() {
        await this.loadUserTimezone();
        window.addEventListener("resize", this.updatePricingView);
    },
    computed: {
        visibleColumns() {
            return this.columns;
        },

        // ✅ ADD THESE COMPUTED PROPERTIES FOR DATE CONVERSION
        localOrderDate() {
            return this.convertToLocalDate(this.item.orderdate);
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
    methods: {
        openConditionModal(item) {
            this.selectedItem = item;
            this.showConditionModal = true;
        },

        handleConditionSaved(conditionData) {
            console.log("Release condition saved:", conditionData);

            if (typeof this.$swal !== "undefined") {
                this.$swal.fire({
                    icon: "success",
                    title: "Success!",
                    text: "Release condition saved successfully",
                    timer: 2000,
                    showConfirmButton: false,
                });
            }

            // Refresh inventory
            this.fetchInventory();
        },

        transformDataForGallery(data) {
            if (!data) {
                return {};
            }

            // If captured images exist, use them with full path
            if (data.capturedImages && data.capturedImages.capturedimg1) {
                const transformedData = { ...data };

                // Map capturedimg1-12 to img1-12 with full path
                for (let i = 1; i <= 12; i++) {
                    const capturedImg = data.capturedImages[`capturedimg${i}`];
                    if (capturedImg) {
                        // Add full path: /images/product_images/Airstaffs/
                        transformedData[`img${i}`] =
                            `/images/product_images/Airstaffs/${capturedImg}`;
                    } else {
                        transformedData[`img${i}`] = null;
                    }
                }

                // Clear img13-15 since captured images only go up to 12
                for (let i = 13; i <= 15; i++) {
                    transformedData[`img${i}`] = null;
                }

                return transformedData;
            }

            // Return original data if no captured images exist (fallback to product images)
            return data;
        },

        countAllImages(data) {
            // Safety check
            if (!data) {
                return 0;
            }

            // If captured images exist, count them
            if (data.capturedImages) {
                let count = 0;
                for (let i = 1; i <= 12; i++) {
                    if (data.capturedImages[`capturedimg${i}`]) {
                        count++;
                    }
                }
                // Return count if captured images exist
                if (count > 0) {
                    return count;
                }
            }

            // Otherwise count product images (fallback)
            let count = 0;
            for (let i = 1; i <= 15; i++) {
                if (data[`img${i}`]) {
                    count++;
                }
            }
            return count;
        },

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
        updatePricingView() {
            this.showPricingSection = showPricingForPH();
        },
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.updatePricingView);
    },
};
</script>

<style scoped src="./cleaning.css"></style>
