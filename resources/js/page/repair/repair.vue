<template>
    <div class="vue-container repair-module">
        <TitlePage
            title="Repair Module"
            subtitle="Track and manage repair jobs — log issues, assign tasks, and monitor progress until units are cleared for the next stage."
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
                            label="Repair - Work Log"
                            icon="pi pi-hammer"
                            class="text-info"
                            @click="openRepairWorkLog(data)"
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
                        <div class="mobile-product-image clickable">
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
                            {{ getDisplayTitle(item) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
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

        <!-- Image Modal -->
        <ViewImageGalleryModal
            :showImageModal="showImageModal"
            :closeImageModal="closeImageModal"
            :ProductTitle="ProductTitle"
            :regularImages="regularImages"
            :capturedImages="capturedImages"
            :handleImageError="handleImageError"
        />

        <!-- Details Modal -->
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

        <!-- Repair Work Log Modal -->
        <Dialog
            v-model:visible="showRepairWorkLog"
            modal
            header="Repair - Work Log"
            style="width: 60rem"
            :pt="{ root: { class: 'mobile-fullscreen-dialog' } }"
        >
            <div class="repair-worklog-body">
                <!-- AUTO-FETCH: System Pre-filled Fields -->
                <div class="worklog-section-label">
                    <span class="worklog-badge badge-autofetch"
                        >AUTO-FETCH</span
                    >
                    <span class="worklog-section-title"
                        >System Pre-filled Fields</span
                    >
                </div>
                <div class="worklog-prefilled-grid">
                    <div class="worklog-prefilled-field">
                        <label>Date Repaired</label>
                        <input
                            type="text"
                            class="worklog-readonly-input"
                            :value="repairDateTime"
                            readonly
                        />
                    </div>
                    <div class="worklog-prefilled-field">
                        <label>Serial Number</label>
                        <input
                            type="text"
                            class="worklog-readonly-input"
                            :value="repairWorkLogItem?.serialnumber || '—'"
                            readonly
                        />
                    </div>
                    <div class="worklog-prefilled-field">
                        <label>Repaired By</label>
                        <input
                            type="text"
                            class="worklog-readonly-input"
                            :value="
                                repairWorkLogItem?.received_by ||
                                repairWorkLogItem?.Username ||
                                currentUser ||
                                '—'
                            "
                            readonly
                        />
                    </div>
                </div>

                <!-- FROM ASIN CONFIG: Repair Categories -->
                <div class="worklog-section-label mt-4">
                    <span class="worklog-badge badge-fromtesting"
                        >ASIN CONFIG</span
                    >
                    <span class="worklog-section-title"
                        >Repair Categories (Auto-loaded)</span
                    >
                </div>
                <div class="worklog-failed-box">
                    <p class="worklog-failed-header">
                        Categories configured for this ASIN:
                    </p>
                    <div
                        v-if="repairWorkLogCategories.length === 0"
                        class="worklog-failed-empty"
                    >
                        No repair categories configured for this ASIN. Set them
                        up in ASIN Configuration → Repair Module.
                    </div>
                    <div
                        v-for="cat in repairWorkLogCategories"
                        :key="cat.name"
                        class="worklog-failed-item"
                    >
                        <span class="worklog-failed-x">🔧</span>
                        {{ cat.name }}
                        <span
                            v-if="cat._fromGlobal"
                            style="
                                margin-left: auto;
                                font-size: 0.7rem;
                                color: #6b7280;
                                background: #e5e7eb;
                                padding: 1px 6px;
                                border-radius: 10px;
                            "
                        >
                            Global
                        </span>
                    </div>
                </div>

                <!-- REPAIR ACTIONS: What was done -->
                <div class="worklog-section-label mt-4">
                    <span class="worklog-badge badge-repairactions"
                        >REPAIR ACTIONS</span
                    >
                    <span class="worklog-section-title"
                        >What was done? (with Pre-typed Notes)</span
                    >
                </div>
                <div
                    v-for="cat in repairWorkLogCategories"
                    :key="'action-' + cat.name"
                    class="worklog-action-card"
                >
                    <p class="worklog-action-title">{{ cat.name }}</p>
                    <select
                        v-model="repairWorkLogValues[cat.name + '__status']"
                        class="worklog-select"
                        @change="onRepairStatusChange(cat)"
                    >
                        <option value="" disabled>
                            Select repair action...
                        </option>
                        <!-- ── Dynamic options from ASIN config actions ── -->
                        <option
                            v-if="cat.actions && cat.actions.length"
                            v-for="action in cat.actions"
                            :key="action.title"
                            :value="action.title"
                        >
                            {{ action.title }}
                        </option>
                        <!-- ── Fallback standard options when no actions configured ── -->
                        <template v-else>
                            <option value="Replaced">Replaced</option>
                            <option value="Repaired">Repaired</option>
                            <option value="Cleaned">Cleaned</option>
                            <option value="Tested & Passed">
                                Tested & Passed
                            </option>
                            <option value="Not Repairable">
                                Not Repairable
                            </option>
                            <option value="Not Required">Not Required</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Needs Attention">
                                Needs Attention
                            </option>
                        </template>
                    </select>
                    <textarea
                        v-model="repairWorkLogValues[cat.name + '__notes']"
                        class="worklog-textarea"
                        :placeholder="
                            getRepairNotePlaceholder(
                                cat,
                                repairWorkLogValues[cat.name + '__status'],
                            )
                        "
                        rows="3"
                    ></textarea>
                </div>

                <!-- COMPLETION: Mark as Done -->
                <div class="worklog-section-label mt-4">
                    <span class="worklog-badge badge-completion"
                        >COMPLETION</span
                    >
                    <span class="worklog-section-title">Mark as Done</span>
                </div>
                <div class="worklog-completion-bar">
                    <div>
                        <p class="worklog-completion-title">
                            All repairs completed?
                        </p>
                        <p class="worklog-completion-sub">
                            This will send the item back to Testing for re-test
                        </p>
                    </div>
                    <Button
                        label="Done Repair"
                        severity="success"
                        :loading="savingRepairWorkLog"
                        @click="saveRepairWorkLog(true)"
                    />
                </div>
            </div>

            <template #footer>
                <div class="d-flex justify-content-between w-100">
                    <Button
                        label="Save Progress"
                        severity="secondary"
                        outlined
                        icon="pi pi-save"
                        :loading="savingRepairWorkLog"
                        @click="saveRepairWorkLog(false)"
                    />
                    <Button
                        label="Cancel"
                        severity="contrast"
                        text
                        @click="showRepairWorkLog = false"
                    />
                </div>
            </template>
        </Dialog>

        <ScrollTop />
    </div>
</template>

<style scoped>
/* ── Work Log Modal ─────────────────────────────────────────── */
.repair-worklog-body {
    padding: 0.5rem 0.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.worklog-section-label {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 0.75rem;
}

.worklog-badge {
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    padding: 0.2rem 0.55rem;
    border-radius: 4px;
    white-space: nowrap;
}

.badge-autofetch {
    background: #e2e8f0;
    color: #475569;
}
.badge-fromtesting {
    background: #dbeafe;
    color: #1e40af;
}
.badge-repairactions {
    background: #fed7aa;
    color: #9a3412;
}
.badge-completion {
    background: #d1fae5;
    color: #065f46;
}

.worklog-section-title {
    font-size: 1rem;
    font-weight: 600;
    color: #1e293b;
}

/* Pre-filled grid */
.worklog-prefilled-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
}

@media (max-width: 640px) {
    .worklog-prefilled-grid {
        grid-template-columns: 1fr;
    }
}

.worklog-prefilled-field label {
    display: block;
    font-size: 0.75rem;
    color: #64748b;
    margin-bottom: 0.25rem;
}

.worklog-readonly-input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background: #f8fafc;
    font-size: 0.875rem;
    color: #334155;
    outline: none;
}

/* Failed items box */
.worklog-failed-box {
    border: 1px solid #fecaca;
    border-radius: 8px;
    background: #fff5f5;
    padding: 1rem;
}

.worklog-failed-header {
    font-weight: 600;
    color: #991b1b;
    margin-bottom: 0.6rem;
    font-size: 0.9rem;
}

.worklog-failed-empty {
    font-size: 0.85rem;
    color: #94a3b8;
    font-style: italic;
}

.worklog-failed-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.5rem 0.75rem;
    border: 1px solid #fca5a5;
    border-radius: 6px;
    background: #fff;
    font-size: 0.875rem;
    color: #374151;
    margin-bottom: 0.4rem;
}

.worklog-failed-x {
    color: #ef4444;
    font-weight: 700;
    font-size: 0.8rem;
}

/* Action cards */
.worklog-action-card {
    border: 1px solid #fed7aa;
    border-radius: 8px;
    background: #fffbf5;
    padding: 1rem;
    margin-bottom: 0.75rem;
}

.worklog-action-title {
    font-weight: 600;
    color: #92400e;
    font-size: 0.9rem;
    margin-bottom: 0.6rem;
}

.worklog-select {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: #fff;
    font-size: 0.875rem;
    color: #374151;
    margin-bottom: 0.5rem;
    outline: none;
    appearance: auto;
}

.worklog-textarea {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: #fff;
    font-size: 0.85rem;
    color: #374151;
    resize: vertical;
    outline: none;
}

.worklog-textarea::placeholder {
    color: #94a3b8;
    font-style: italic;
}

/* Completion bar */
.worklog-completion-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    padding: 1rem 1.25rem;
    gap: 1rem;
}

.worklog-completion-title {
    font-weight: 600;
    color: #166534;
    margin: 0;
    font-size: 0.95rem;
}

.worklog-completion-sub {
    color: #16a34a;
    font-size: 0.8rem;
    margin: 0.15rem 0 0;
}
</style>

<script>
import { Button, Card, Dialog, ScrollTop, Select, Paginator } from "primevue";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import TableGallery from "../../components/Gallery/tableGallery.vue";
import Repair from "./repair.js";
import Gallery from "../../components/Gallery/gallery.vue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import ViewImageGalleryModal from "../../components/ViewImageGalleryModal/ViewImageGalleryModal.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";
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
        field: "serialnumber",
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
        slot: "datedelivered",
    },
];

export default {
    mixins: [Repair],
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

        localOrderDate() {
            return this.convertToLocalDate(this.item?.orderdate);
        },
        localDeliveredDate: {
            get() {
                return this.convertToLocalDate(this.item?.datedelivered);
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

            this.fetchInventory();
        },

        countAllImages(data) {
            if (!data) return 0;

            if (data.capturedImages) {
                let count = 0;
                for (let i = 1; i <= 12; i++) {
                    if (data.capturedImages[`capturedimg${i}`]) count++;
                }
                if (count > 0) return count;
            }

            let count = 0;
            for (let i = 1; i <= 15; i++) {
                if (data[`img${i}`]) count++;
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

                if (isLATimezone) {
                    return dateString.split(" ")[0].split("T")[0];
                }

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
                const [year, month, day] = localDateString.split("-");
                const dateInUserTz = new Date(
                    `${year}-${month}-${day}T12:00:00`,
                );
                return dateInUserTz.toISOString().split("T")[0];
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

                    const date = new Date();
                    const utcDate = new Date(
                        date.toLocaleString("en-US", { timeZone: "UTC" }),
                    );
                    const userTzDate = new Date(
                        date.toLocaleString("en-US", {
                            timeZone: this.currentTimezone,
                        }),
                    );
                    const offsetMs = userTzDate - utcDate;
                    const offsetHours = Math.round(offsetMs / (1000 * 60 * 60));
                    const offsetSign = offsetHours >= 0 ? "+" : "-";
                    const gmtOffset = `GMT${offsetSign}${Math.abs(offsetHours)}`;

                    this.timezoneLabel = `(${gmtOffset})`;
                } else {
                    const browserTz =
                        Intl.DateTimeFormat().resolvedOptions().timeZone;
                    this.currentTimezone = browserTz;
                    this.timezoneLabel = browserTz
                        .split("/")
                        .pop()
                        .replace("_", " ");
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
