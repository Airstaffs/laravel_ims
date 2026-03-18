<template>
    <div class="vue-container orders-module">
        <div
            class="d-flex align-items-center justify-content-between flex-wrap mb-4"
        >
            <TitlePage
                title="Order Module"
                subtitle="View and manage all current and past shipment orders, including tracking information and status."
            />

           <div class="d-flex align-items-center gap-2 mx-4">
                <Button
                    @click="openIncomingCounter"
                    label="Incoming Order"
                    size="small"
                    icon="pi pi-calculator"
                    severity="info"
                    outlined
                />
                <Button
                    @click="showAddOrderModal = true"
                    label="Add Order"
                    size="small"
                    icon="pi pi-plus"
                    severity="success"
                    outlined
                />
           </div>
        </div>

        <!-- Date Range Filters - Toggle Version with Column Layout -->
        <div class="filter-section mb-3 px-4">
            <!-- Filter Toggle Button -->
            <div class="mb-2">
                <Button
                    @click="showFilters = !showFilters"
                    size="small"
                    severity="secondary"
                    outlined
                >
                    <i
                        :class="
                            showFilters ? 'pi pi-filter-slash' : 'pi pi-filter'
                        "
                        class="me-2"
                    ></i>
                    {{ showFilters ? "Hide Filters" : "Show Filters" }}
                    <span
                        v-if="hasActiveFilters"
                        class="badge bg-primary text-white ms-2"
                    >
                        {{ activeFilterCount }}
                    </span>
                </Button>
            </div>

            <!-- Filter Panel -->
            <transition name="filter-slide">
                <div v-show="showFilters" class="card p-3 filter-panel">
                    <div class="d-flex flex-column align-items-start gap-2">
                        <!-- Filter Icon & Title -->
                        <div
                            class="w-100 d-flex justify-content-between align-items-center"
                        >
                            <div class="filter-title">
                                <i class="pi pi-filter me-2"></i>
                                <span class="fw-semibold">Date Filters</span>
                            </div>
                            <Button
                                v-if="hasActiveFilters"
                                icon="pi pi-times"
                                size="small"
                                severity="secondary"
                                text
                                label="Clear filters"
                                rounded
                                @click="clearFilters"
                                v-tooltip.top="'Clear filters'"
                            />
                        </div>

                        <!-- Filters Container -->
                        <div class="d-flex flex-column gap-3 flex-grow-0">
                            <!-- Order Date Range -->
                            <div class="filter-row">
                                <div
                                    class="d-flex align-items-center gap-2"
                                    style="width: 100px"
                                >
                                    <i
                                        class="pi pi-calendar"
                                        style="font-size: 0.85rem"
                                    ></i>
                                    <small class="fw-semibold">Order:</small>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <input
                                        type="date"
                                        v-model="dateFilters.orderDateFrom"
                                        class="form-control form-control-sm"
                                        placeholder="From"
                                        :max="
                                            dateFilters.orderDateTo ||
                                            getCurrentDate()
                                        "
                                        style="width: 160px"
                                    />
                                    <span class="text-muted">-</span>
                                    <input
                                        type="date"
                                        v-model="dateFilters.orderDateTo"
                                        class="form-control form-control-sm"
                                        placeholder="To"
                                        :min="dateFilters.orderDateFrom"
                                        :max="getCurrentDate()"
                                        style="width: 160px"
                                    />
                                </div>
                            </div>

                            <!-- Delivery Date Range -->
                            <div class="filter-row">
                                <div
                                    class="d-flex align-items-center gap-2"
                                    style="width: 100px"
                                >
                                    <i
                                        class="pi pi-box"
                                        style="font-size: 0.85rem"
                                    ></i>
                                    <small class="fw-semibold">Delivery:</small>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <input
                                        type="date"
                                        v-model="dateFilters.deliveryDateFrom"
                                        class="form-control form-control-sm"
                                        placeholder="From"
                                        :max="
                                            dateFilters.deliveryDateTo ||
                                            getCurrentDate()
                                        "
                                        style="width: 160px"
                                    />
                                    <span class="text-muted">-</span>
                                    <input
                                        type="date"
                                        v-model="dateFilters.deliveryDateTo"
                                        class="form-control form-control-sm"
                                        placeholder="To"
                                        :min="dateFilters.deliveryDateFrom"
                                        style="width: 160px"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Clear Button & Count -->
                        <!-- <div
                            class="ms-auto d-flex flex-column align-items-end gap-2"
                        >
                            <Button
                                v-if="hasActiveFilters"
                                icon="pi pi-times"
                                size="small"
                                severity="secondary"
                                text
                                rounded
                                @click="clearFilters"
                                v-tooltip.top="'Clear filters'"
                            />
                            <small class="text-muted">
                                {{ totalFilteredCount }} /
                                {{ totalInventoryCount }} items
                            </small>
                        </div> -->
                    </div>
                </div>
            </transition>
        </div>

        <!-- Desktop Table Container -->
        <AnimateDiv :delay="200" class="px-4">
            <XDataTable
                :value="filteredAndSortedInventory"
                :loading="loading"
                :columns="columns"
                :paginator="false"
                selectionMode="multiple"
                selection="multiple"
                tableClass="desktop-view"
                dataKey="ProductID"
            >
                <!-- Gallery Column -->
                <template #gallery="{ data }">
                    <div
                        class="d-flex justify-content-center align-items-center"
                    >
                        <TableGallery
                            :data="data"
                            :openImageModal="openImageModal"
                            :handleImageError="handleImageError"
                            :countAdditionalImages="countAdditionalImages"
                        />
                    </div>
                </template>

                <!-- Product Title Column -->
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
                                RT# {{ data.rtcounter }}
                            </p>
                            <p class="fw-semibold">
                                {{ data.ProductTitle }}
                            </p>
                        </div>
                    </div>
                </template>

                <!-- ASIN Column -->
                <template #asin="{ data }">
                    <div class="asin-cell">
                        <span
                            v-if="data.display_asin || data.ASIN"
                            class="badge bg-primary"
                        >
                            {{ data.display_asin || data.ASIN }}
                        </span>
                        <span v-else class="text-muted small"> No ASIN </span>
                    </div>
                </template>

                <!-- Material Type Column with Inline Editing -->
                <template #materialtype="{ data }">
                    <div class="materialtype-cell">
                        <div
                            v-if="editingMaterialType === data.ProductID"
                            class="materialtype-edit"
                        >
                            <Select
                                v-model="tempMaterialType"
                                :options="materialOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Select Material"
                                size="small"
                                style="min-width: 140px"
                                @change="saveMaterialType(data)"
                                @blur="cancelMaterialTypeEdit"
                                :ref="`materialTypeSelect-${data.ProductID}`"
                            />
                        </div>
                        <div
                            v-else
                            class="materialtype-display"
                            @click="startMaterialTypeEdit(data)"
                        >
                            <span class="materialtype-value">{{
                                data.materialtype || "Not Set"
                            }}</span>
                            <i
                                class="pi pi-pencil text-muted ms-1"
                                style="font-size: 0.7rem"
                            ></i>
                        </div>
                    </div>
                </template>

                <!-- Quantity Column with Inline Editing -->
                <template #quantity="{ data }">
                    <div class="quantity-cell">
                        <div
                            v-if="editingQuantity === data.ProductID"
                            class="quantity-edit"
                        >
                            <InputText
                                v-model="tempQuantity"
                                type="number"
                                size="small"
                                style="width: 60px"
                                @keyup.enter="saveQuantity(data)"
                                @keyup.esc="cancelQuantityEdit"
                                @blur="saveQuantity(data)"
                                :ref="`quantityInput-${data.ProductID}`"
                            />
                        </div>
                        <div
                            v-else
                            class="quantity-display"
                            @click="startQuantityEdit(data)"
                        >
                            <span class="quantity-value">{{
                                data.quantity || 0
                            }}</span>
                            <i
                                class="pi pi-pencil text-muted ms-1"
                                style="font-size: 0.7rem"
                            ></i>
                        </div>
                    </div>
                </template>

                <template #tracking="{ data }">
                    <div class="tracking-cell">
                        <div
                            v-if="
                                data.tracking_info &&
                                data.tracking_info.length > 0
                            "
                            class="tracking-list"
                        >
                            <div
                                v-for="(tracking, idx) in data.tracking_info"
                                :key="idx"
                                class="tracking-item mb-2"
                            >
                                <!-- Tracking Number -->
                                <div
                                    class="tracking-number d-flex align-items-center"
                                >
                                    <i
                                        class="pi pi-box text-muted me-1"
                                        style="font-size: 0.7rem"
                                    ></i>
                                    <span
                                        class="fw-semibold"
                                        style="font-size: 0.85rem"
                                    >
                                        {{ tracking.number }}
                                    </span>
                                </div>

                                <!-- Tracking Status Badge ONLY — no delivered date here -->
                                <div class="tracking-status mt-1">
                                    <Badge
                                        :severity="
                                            getTrackingStatusSeverity(
                                                tracking.status,
                                                tracking.delivered_date,
                                                data.estimated_deliverydate,
                                            )
                                        "
                                        :value="tracking.status"
                                        size="small"
                                        :class="
                                            getOverdueBadgeClass(
                                                tracking.status,
                                                tracking.delivered_date,
                                                data.estimated_deliverydate,
                                            )
                                        "
                                    />
                                </div>
                            </div>

                            <!-- Last Checked Timestamp -->
                            <div
                                v-if="data.tracking_last_checked"
                                class="last-checked mt-2 pt-2 border-top"
                            >
                                <small class="text-muted">
                                    <i
                                        class="pi pi-clock me-1"
                                        style="font-size: 0.7rem"
                                    ></i>
                                    Updated:
                                    {{
                                        formatLastChecked(
                                            data.tracking_last_checked,
                                        )
                                    }}
                                </small>
                            </div>
                        </div>

                        <!-- No Tracking Available -->
                        <div v-else class="no-tracking">
                            <span class="text-muted small">No tracking</span>
                        </div>
                    </div>
                </template>

                <!-- Order Date Column -->
                <template #orderdate="{ data }">
                    {{ convertToLocalDate(data.orderdate) }}
                </template>

                <template #deliverydate="{ data }">
                    <div class="delivery-date-cell">
                        <!-- CASE 1: At least one tracking is Delivered → show actual delivered date -->
                        <div v-if="isAnyTrackingDelivered(data)">
                            <i
                                class="pi pi-check-circle text-success me-1"
                                style="font-size: 0.8rem"
                            ></i>
                            <span class="fw-semibold text-success">
                                {{ getLatestDeliveredDate(data) }}
                            </span>
                            <!-- Show if multiple deliveries -->
                            <div
                                v-if="hasMultipleDeliveries(data)"
                                class="mt-1"
                            >
                                <small class="text-info">
                                    <i
                                        class="pi pi-info-circle me-1"
                                        style="font-size: 0.7rem"
                                    ></i>
                                    Multiple deliveries
                                </small>
                            </div>
                        </div>

                        <!-- CASE 2: NOT delivered → show estimated delivery date -->
                        <div v-else-if="data.estimated_deliverydate">
                            <div class="d-flex align-items-center gap-1">
                                <i
                                    class="pi pi-clock me-1"
                                    :class="
                                        getOverdueIconClass(
                                            data.estimated_deliverydate,
                                        )
                                    "
                                    style="font-size: 0.8rem"
                                ></i>
                                <span
                                    :class="
                                        getOverdueDateClass(
                                            data.estimated_deliverydate,
                                        )
                                    "
                                >
                                    {{ data.estimated_deliverydate }}
                                </span>
                            </div>
                            <!-- Overdue Warning -->
                            <div
                                v-if="
                                    getOverdueText(data.estimated_deliverydate)
                                "
                                class="mt-1"
                            >
                                <small
                                    :class="
                                        getOverdueTextClass(
                                            data.estimated_deliverydate,
                                        )
                                    "
                                >
                                    <i
                                        class="pi pi-exclamation-triangle me-1"
                                        style="font-size: 0.7rem"
                                    ></i>
                                    {{
                                        getOverdueText(
                                            data.estimated_deliverydate,
                                        )
                                    }}
                                </small>
                            </div>
                        </div>

                        <!-- CASE 3: No date at all -->
                        <span v-else class="text-muted small">N/A</span>
                    </div>
                </template>

                <!-- Status Column -->
                <template #status="{ data }">
                    <Badge
                        :severity="
                            {
                                Working: 'success',
                                Pending: 'warning',
                            }[data.itemstatus] || 'secondary'
                        "
                        :value="data.itemstatus"
                    />
                </template>

                <!-- Actions Column -->
                <template #actions="{ data }">
                    <div class="action-buttons">
                        <!-- Edit Button (always visible) -->
                        <Button
                            size="small"
                            icon="pi pi-pencil"
                            @click="openEditModal(data)"
                            label="Edit"
                            severity="contrast"
                            text
                        />

                        <!-- Set ASIN Button (show when NO ASIN) -->
                        <Button
                            v-if="!data.ASINviewer && !data.display_asin"
                            size="small"
                            icon="pi pi-link"
                            @click="openSetAsinModal(data)"
                            label="Set ASIN"
                            severity="success"
                            text
                            class="set-asin-btn"
                        />

                        <!-- Remove ASIN Button (show when HAS ASIN) -->
                        <Button
                            v-if="data.ASINviewer || data.display_asin"
                            size="small"
                            icon="pi pi-unlink"
                            @click="removeAsin(data)"
                            label="Remove ASIN"
                            severity="danger"
                            text
                            class="remove-asin-btn"
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
                            <h5 class="mobile-product-name clickable">
                                <span style="font-size: 1rem"
                                    >RT# : {{ item.rtcounter }}</span
                                >
                                <span>{{ item.ProductTitle }}</span>
                            </h5>
                        </div>
                    </div>

                    <hr />
                    <div class="mobile-card-details">
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label"
                                >Seller Location:</span
                            >
                            <span class="mobile-detal-value">
                                {{ item.Ebay_seller_location }}</span
                            >
                        </div>

                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label"
                                >Tracking Number:</span
                            >
                            <div class="d-flex flex-column align-items-end">
                                <span class="mobile-detal-value">
                                    {{ item.trackingnumber || "No tracking" }}
                                </span>
                                <Badge
                                    v-if="item.delivery_status"
                                    :severity="
                                        getDeliveryStatusSeverity(
                                            item.delivery_status,
                                        )
                                    "
                                    :value="item.delivery_status"
                                    size="small"
                                    class="mt-1"
                                />
                            </div>
                        </div>

                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label"
                                >Ordered Condition:</span
                            >
                            <span class="mobile-detal-value">
                                {{ item.listedcondition }}</span
                            >
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label"
                                >Condition Status:</span
                            >
                            <span class="mobile-detal-value">
                                <Badge
                                    :severity="
                                        {
                                            Working: 'success',
                                            Pending: 'warning',
                                        }[item.itemstatus] || 'secondary'
                                    "
                                    :value="item.itemstatus"
                                />
                            </span>
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label"
                                >Ordered Date:</span
                            >
                            <span class="mobile-detal-value">
                                {{ convertToLocalDate(item.orderdate) }}
                            </span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label"
                                >Delivered Date:</span
                            >
                            <span class="mobile-detal-value">
                                {{
                                    getLatestDeliveredDate(item)
                                        ? convertToLocalDate(
                                              getLatestDeliveredDate(item),
                                          )
                                        : "N/A"
                                }}
                            </span>
                        </div>
                    </div>

                    <hr />

                    <div class="mobile-card-actions">
                        <Button
                            severity="info"
                            @click="openEditModal(item)"
                            icon="pi pi-pencil"
                            label="Edit"
                            size="small"
                            style="width: 100%"
                        />
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
        <ViewImageModal
            v-model:visible="showImageModal"
            :title="ProductTitle"
            :imageList="imageList"
            :basePath="basePath"
            :onImageErrorMain="onImageErrorMain"
            :onThumbnailError="onThumbnailError"
            @close="closeImageModal"
        />

        <Dialog
            v-model:visible="showEditModal"
            modal
            :style="{ width: '90%' }"
            :pt="{
                root: { class: 'mobile-fullscreen-dialog' },
            }"
        >
            <template #header>
                <h5>{{ `RT ${item.rtcounter} - ${item.ProductTitle}` }}</h5>
            </template>
            <div class="edit-order-container">
                <form method="POST" class="editOrderForm">
                    <div class="form-grid-wrapper">
                        <!-- LEFT: IMAGE + GENERAL INFO -->
                        <div class="form-col-left">
                            <div class="image-section" v-if="imageList.length">
                                <!-- Main Image -->
                                <div
                                    class="main-image"
                                    @click="openEditModalZoom"
                                >
                                    <img
                                        :src="activeImageUrl"
                                        alt="Main Product Image"
                                        loading="lazy"
                                        @error="onImageErrorMain"
                                    />

                                    <!-- Zoom Indicator -->
                                    <div class="zoom-indicator">
                                        <i class="pi pi-search-plus"></i>
                                        <span>Click to zoom</span>
                                    </div>
                                </div>

                                <!-- Thumbnails -->
                                <div class="thumbnail-carousel">
                                    <div
                                        v-for="(img, index) in imageList"
                                        :key="index"
                                        :class="[
                                            'thumbnail',
                                            {
                                                active: index === activeIndex,
                                            },
                                        ]"
                                        @click="activeIndex = index"
                                        @mouseenter="activeIndex = index"
                                    >
                                        <img
                                            :src="basePath + img"
                                            alt="Thumbnail"
                                            loading="lazy"
                                            @error="onThumbnailError($event)"
                                        />
                                    </div>
                                </div>
                            </div>
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
                                    <div>
                                        <fieldset>
                                            <label>External Title</label>
                                            <Textarea
                                                size="small"
                                                fluid
                                                v-model="item.ProductTitle"
                                                rows="5"
                                            />
                                        </fieldset>
                                        <fieldset>
                                            <label>RT:</label>
                                            <InputText
                                                size="small"
                                                v-model="item.rtcounter"
                                                fluid
                                            />
                                        </fieldset>
                                        <fieldset>
                                            <label>Order Number:</label>
                                            <InputText
                                                size="small"
                                                v-model="item.rtid"
                                                fluid
                                            />
                                        </fieldset>
                                        <fieldset>
                                            <label>Item Number:</label>
                                            <InputText
                                                size="small"
                                                v-model="item.itemnumber"
                                                fluid
                                            />
                                        </fieldset>
                                    </div>
                                </template>
                            </Card>
                        </div>

                        <!-- CENTER: ALL OTHER INFO EXCEPT PRICING -->
                        <div class="form-col-center">
                            <div
                                class="form-section other-section bg-white border-0"
                            >
                                <!-- SECTION: Dates -->
                                <div class="dates-section">
                                    <Card>
                                        <template #title>
                                            <div
                                                class="d-flex justify-content-between align-items-center"
                                            >
                                                <h6 class="text-primary mb-0">
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
                                            </div>
                                            <Divider />
                                        </template>

                                        <template #content>
                                            <div>
                                                <fieldset>
                                                    <label>Order Date:</label>
                                                    <input
                                                        type="date"
                                                        class="form-control"
                                                        v-model="localOrderDate"
                                                    />
                                                </fieldset>
                                                <fieldset>
                                                    <label>Payment Date:</label>
                                                    <input
                                                        type="date"
                                                        class="form-control"
                                                        v-model="
                                                            localPaymentDate
                                                        "
                                                    />
                                                </fieldset>
                                                <fieldset>
                                                    <label>Shipped Date:</label>
                                                    <input
                                                        type="date"
                                                        class="form-control"
                                                        v-model="localShipDate"
                                                    />
                                                </fieldset>
                                                <fieldset>
                                                    <label
                                                        >Delivered Date:</label
                                                    >
                                                    <input
                                                        type="date"
                                                        class="form-control"
                                                        v-model="
                                                            localDeliveredDate
                                                        "
                                                    />
                                                </fieldset>
                                            </div>
                                        </template>
                                    </Card>

                                    <Card class="mt-2">
                                        <template #content>
                                            <div>
                                                <fieldset>
                                                    <label>Description:</label>
                                                    <Textarea
                                                        ref="descriptionarea"
                                                        size="small"
                                                        fluid
                                                        v-model="
                                                            item.description
                                                        "
                                                        placeholder="Description"
                                                        rows="4"
                                                        @input="autoResize"
                                                        class="no-resize"
                                                    ></Textarea>
                                                </fieldset>
                                                <fieldset>
                                                    <label
                                                        >Supplier Notes:</label
                                                    >
                                                    <Textarea
                                                        ref="supplierNotesarea"
                                                        size="small"
                                                        fluid
                                                        v-model="
                                                            item.supplierNotes
                                                        "
                                                        placeholder="Supplier Notes"
                                                        rows="4"
                                                        @input="autoResize"
                                                        class="no-resize"
                                                    ></Textarea>
                                                </fieldset>
                                            </div>
                                        </template>
                                    </Card>
                                </div>

                                <!-- SECTION: Serial & Tracking -->
                                <div class="serial-tracking-section">
                                    <Card>
                                        <template #title>
                                            <div>
                                                <h6 class="text-primary">
                                                    Serial & Tracking
                                                </h6>
                                                <Divider />
                                            </div>
                                        </template>
                                        <template #content>
                                            <!-- Serial Numbers -->
                                            <template v-if="serialKeys.length">
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
                                                        size="small"
                                                        fluid
                                                        v-model="item[key]"
                                                    />
                                                </fieldset>
                                            </template>

                                            <Divider
                                                v-if="
                                                    serialKeys.length &&
                                                    trackingKeys.length
                                                "
                                            />

                                            <!-- Tracking Numbers - EDITABLE, Status READ-ONLY -->
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
                                                        class="d-flex align-items-center justify-content-between"
                                                    >
                                                        <span
                                                            >Tracking Number
                                                            {{
                                                                index + 1
                                                            }}:</span
                                                        >
                                                        <!-- Show status badge if exists - READ ONLY -->
                                                        <Badge
                                                            v-if="
                                                                item[
                                                                    `tracking${index + 1}_status`
                                                                ]
                                                            "
                                                            :severity="
                                                                getTrackingStatusSeverity(
                                                                    item[
                                                                        `tracking${index + 1}_status`
                                                                    ],
                                                                )
                                                            "
                                                            :value="
                                                                item[
                                                                    `tracking${index + 1}_status`
                                                                ]
                                                            "
                                                            size="small"
                                                        />
                                                    </label>
                                                    <InputText
                                                        size="small"
                                                        fluid
                                                        v-model="item[key]"
                                                    />

                                                    <!-- Show delivered date if exists - READ ONLY -->
                                                    <div
                                                        v-if="
                                                            item[
                                                                `tracking${index + 1}_delivered_date`
                                                            ]
                                                        "
                                                        class="mt-1"
                                                    >
                                                        <small
                                                            class="text-success"
                                                        >
                                                            <i
                                                                class="pi pi-check-circle me-1"
                                                            ></i>
                                                            Delivered:
                                                            {{
                                                                formatDeliveryDate(
                                                                    item[
                                                                        `tracking${index + 1}_delivered_date`
                                                                    ],
                                                                )
                                                            }}
                                                        </small>
                                                    </div>
                                                </fieldset>
                                            </template>

                                            <!-- Last Checked Info - READ ONLY -->
                                            <div
                                                v-if="
                                                    item.tracking_last_checked
                                                "
                                                class="mt-3 pt-3 border-top"
                                            >
                                                <small class="text-muted">
                                                    <i
                                                        class="pi pi-clock me-1"
                                                    ></i>
                                                    Last checked:
                                                    {{
                                                        formatLastChecked(
                                                            item.tracking_last_checked,
                                                        )
                                                    }}
                                                </small>
                                            </div>
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
                                                    <label
                                                        >Supplier
                                                        ID/Name:</label
                                                    >
                                                    <InputText
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
                                                        placeholder="Select Material Type"
                                                        fluid
                                                        size="small"
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
                                                        placeholder="Select Source Type"
                                                        fluid
                                                        size="small"
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
                                                        fluid
                                                        size="small"
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
                                                        fluid
                                                        size="small"
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
                                                        fluid
                                                        size="small"
                                                    />
                                                </fieldset>
                                            </div>
                                        </template>
                                    </Card>

                                    <Card class="mt-2">
                                        <template #content>
                                            <div>
                                                <fieldset>
                                                    <label
                                                        >Supplier Notes:</label
                                                    >
                                                    <Textarea
                                                        ref="employeeNotesarea"
                                                        size="small"
                                                        fluid
                                                        v-model="item.notes"
                                                        placeholder="Supplier Notes"
                                                        rows="4"
                                                        @input="autoResize"
                                                        class="no-resize"
                                                    ></Textarea>
                                                </fieldset>
                                            </div>
                                        </template>
                                    </Card>
                                </div>
                            </div>
                        </div>

                        <!-- RIGHT: PRICING -->
                        <div class="form-col-right" v-show="showPricingSection">
                            <Card>
                                <template #title>
                                    <div>
                                        <h4 class="text-primary">Pricing</h4>
                                        <Divider />

                                        <fieldset>
                                            <label>Quantity</label>
                                            <InputText
                                                type="number"
                                                class="text-end"
                                                fluid
                                                v-model="item.quantity"
                                                size="small"
                                            />
                                        </fieldset>
                                        <fieldset>
                                            <label>Total Price</label>
                                            <InputText
                                                type="number"
                                                class="text-end"
                                                fluid
                                                :value="item.price"
                                                size="small"
                                            />
                                        </fieldset>

                                        <fieldset>
                                            <label>Discount</label>
                                            <InputText
                                                type="number"
                                                class="text-end"
                                                fluid
                                                v-model="item.Discount"
                                                size="small"
                                            />
                                        </fieldset>
                                        <fieldset>
                                            <label>Tax</label>
                                            <InputText
                                                type="number"
                                                class="text-end"
                                                fluid
                                                v-model="item.tax"
                                                size="small"
                                            />
                                        </fieldset>
                                        <fieldset>
                                            <label>Shipping</label>
                                            <InputText
                                                type="number"
                                                class="text-end"
                                                fluid
                                                v-model="item.priceshipping"
                                                size="small"
                                            />
                                        </fieldset>
                                        <fieldset>
                                            <label>Refund</label>
                                            <InputText
                                                type="number"
                                                class="ftext-end"
                                                fluid
                                                v-model="item.refund"
                                                size="small"
                                            />
                                        </fieldset>
                                        <Divider />
                                        <fieldset>
                                            <label>Unit Price</label>
                                            <InputText
                                                type="text"
                                                class="text-end"
                                                fluid
                                                :value="formattedUnitprice"
                                                size="small"
                                                readonly
                                            />
                                        </fieldset>
                                        <fieldset>
                                            <label>Grand Total</label>
                                            <InputText
                                                type="text"
                                                class="text-end bg-light fw-bold text-success"
                                                fluid
                                                :value="grandTotal"
                                                size="small"
                                                readonly
                                            />
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
                    <Button
                        label="Save"
                        severity="info"
                        size="small"
                        @click="saveEditModal"
                        icon="pi pi-save"
                    />
                </div>
            </template>
        </Dialog>

        <ScrollTop />

        <!-- Set ASIN Modal -->
        <SetASINModal
            v-model:visible="showSetAsinModal"
            :productId="selectedItem?.ProductID"
            :rtCounter="selectedItem?.rtcounter"
            :productTitle="selectedItem?.ProductTitle"
            @asin-selected="handleAsinSelected"
        />

        <!-- Incoming Counter Modal -->
        <IncomingCountItem
            v-model:visible="showIncomingCounter"
            @close="showIncomingCounter = false"
        />

    <!-- Manual Add Order Modal -->
       <AddOrderModal
        v-model:visible="showAddOrderModal"
        @order-added="fetchInventory"
    />


    </div>

    <ZoomImageModal
        v-model:visible="showEditZoomModal"
        :images="imagesWithPathList"
        :initialIndex="activeIndex"
        :title="item.ProductTitle"
    />
</template>

<script>
import Orders from "./orders.js";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import TableGallery from "../../components/Gallery/tableGallery.vue";
import {
    Badge,
    Button,
    Card,
    Dialog,
    Divider,
    InputText,
    Textarea,
    DatePicker,
    Select,
    ScrollTop,
    Tag,
    Paginator,
} from "primevue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import ViewImageModal from "../../components/ViewImageModal/ViewImageModal.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";
import SetASINModal from "./modals/setASIN.vue";
import IncomingCountItem from "./modals/IncomingCountItem.vue";
import AddOrderModal from "./modals/AddOrderModal.vue";
import { ROWS_PER_PAGE } from "../../constant.js";
import { showPricingForPH } from "../../utils/helpers.js";
import ZoomImageModal from "../../components/ZoomImageModal/ZoomImageModal.vue";
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
        field: "display_asin",
        header: "ASIN",
        sortable: true,
        headerStyle: "font-size: 16px;",
        slot: "asin",
        style: { fontSize: "14px", minWidth: "120px" },
    },
    {
        field: "Ebay_seller_location",
        header: "Seller Location",
        sortable: true,
        headerStyle: "font-size: 16px;",
        style: { fontSize: "14px" },
    },
    {
        field: "trackingnumber",
        header: "Tracking & Status",
        sortable: true,
        headerStyle: "font-size: 16px;",
        slot: "tracking",
        style: { fontSize: "14px", minWidth: "180px" },
    },
    {
        field: "listedcondition",
        header: "Ordered Condition",
        sortable: true,
        headerStyle: "font-size: 16px;",
        style: { fontSize: "14px", textAlign: "center" },
    },
    {
        field: "itemstatus",
        header: "Condition Status",
        slot: "status",
        sortable: true,
        headerStyle: "font-size: 16px;",
        style: { textAlign: "center" },
    },

    {
        field: "materialtype",
        header: "Material Type",
        sortable: true,
        headerStyle: "font-size: 16px;",
        slot: "materialtype",
        style: { fontSize: "14px", minWidth: "150px", textAlign: "center" },
    },

    {
        field: "quantity",
        header: "Qty",
        sortable: true,
        headerStyle: "font-size: 16px;",
        slot: "quantity",
        style: { fontSize: "14px", minWidth: "100px", textAlign: "center" },
    },
    {
        field: "orderdate",
        header: "Ordered Date",
        sortable: true,
        headerStyle: "font-size: 16px;",
        slot: "orderdate",
        style: { fontSize: "14px", textAlign: "center" },
    },
    {
        field: "delivery_sort_date", // ✅ Sort by computed field
        header: "Delivery Date",
        sortable: true, // ✅ Sorting enabled
        headerStyle: "font-size: 16px;",
        slot: "deliverydate", // Still use custom slot for display
        style: { fontSize: "14px", minWidth: "180px", textAlign: "center" },
    },
];
export default {
    mixins: [Orders],
    components: {
        XDataTable,
        TableGallery,
        Button,
        Badge,
        Dialog,
        Divider,
        InputText,
        Textarea,
        Card,
        DatePicker,
        Select,
        ScrollTop,
        TitlePage,
        ViewImageModal,
        AnimateDiv,
        Tag,
        SetASINModal,
        IncomingCountItem,
        AddOrderModal,
        ZoomImageModal,
        Paginator,
    },
    data() {
        return {
            selectedRows: [],
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

            editingMaterialType: null,
            tempMaterialType: null,
            rowsPerPageOptions: ROWS_PER_PAGE,

            currentTimezone: "UTC",
            timezoneLabel: "Loading...",
            showPricingSection: showPricingForPH(),
            showSetAsinModal: false,
            selectedItem: null,
            showIncomingCounter: false,
            showAddOrderModal: false,

            showFilters: false,
            showEditZoomModal: false,
            imagesWithPathList: [],

            dateFilters: {
                orderDateFrom: null,
                orderDateTo: null,
                deliveryDateFrom: null,
                deliveryDateTo: null,
            },
        };
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.updatePricingView);
    },
    async mounted() {
        await this.loadUserTimezone();
        window.addEventListener("resize", this.updatePricingView);
    },
    computed: {
        courierOptions() {
            return this.carrierOptions.map((carrier) => ({
                value: carrier,
                label: carrier,
            }));
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

        filteredAndSortedInventory() {
            let filtered = [...this.sortedInventory]; // Use your existing sortedInventory

            // Filter by Order Date
            if (this.dateFilters.orderDateFrom) {
                filtered = filtered.filter((item) => {
                    if (!item.orderdate) return false;
                    const orderDate = this.convertToLocalDate(item.orderdate);
                    return orderDate >= this.dateFilters.orderDateFrom;
                });
            }

            if (this.dateFilters.orderDateTo) {
                filtered = filtered.filter((item) => {
                    if (!item.orderdate) return false;
                    const orderDate = this.convertToLocalDate(item.orderdate);
                    return orderDate <= this.dateFilters.orderDateTo;
                });
            }

            // Filter by Delivery Date
            if (this.dateFilters.deliveryDateFrom) {
                filtered = filtered.filter((item) => {
                    // Check both datedelivered and estimated_deliverydate
                    const deliveryDate = this.getDeliveryDateForFilter(item);
                    if (!deliveryDate) return false;
                    return deliveryDate >= this.dateFilters.deliveryDateFrom;
                });
            }

            if (this.dateFilters.deliveryDateTo) {
                filtered = filtered.filter((item) => {
                    // Check both datedelivered and estimated_deliverydate
                    const deliveryDate = this.getDeliveryDateForFilter(item);
                    if (!deliveryDate) return false;
                    return deliveryDate <= this.dateFilters.deliveryDateTo;
                });
            }

            return filtered;
        },

        hasActiveFilters() {
            return !!(
                this.dateFilters.orderDateFrom ||
                this.dateFilters.orderDateTo ||
                this.dateFilters.deliveryDateFrom ||
                this.dateFilters.deliveryDateTo
            );
        },

        activeFilterCount() {
            let count = 0;
            if (this.dateFilters.orderDateFrom) count++;
            if (this.dateFilters.orderDateTo) count++;
            if (this.dateFilters.deliveryDateFrom) count++;
            if (this.dateFilters.deliveryDateTo) count++;
            return count;
        },

        totalFilteredCount() {
            return this.filteredAndSortedInventory.length;
        },

        totalInventoryCount() {
            // Replace 'inventory' with your actual data source property name
            // This should be the unfiltered, unpaginated full dataset
            return this.inventory
                ? this.inventory.length
                : this.sortedInventory.length;
        },
    },
    methods: {
        openEditModalZoom() {
            if (this.activeImageUrl) {
                //add path for the images
                this.imagesWithPathList = this.imageList.map(
                    (img) => this.basePath + img,
                );
                this.showEditZoomModal = true;
            }
        },

        selectEditThumbnail(index) {
            this.activeIndex = index;
            // Also open zoom when clicking thumbnail in edit modal
            this.openEditModalZoom();
        },
        formatDeliveryDate(dateString) {
            if (
                !dateString ||
                dateString === "0000-00-00" ||
                dateString === "0000-00-00 00:00:00"
            ) {
                return "N/A";
            }

            try {
                const date = new Date(dateString);
                return date.toLocaleDateString("en-US", {
                    year: "numeric",
                    month: "short",
                    day: "numeric",
                    timeZone: this.currentTimezone || "UTC",
                });
            } catch (error) {
                console.error("Error formatting delivery date:", error);
                return dateString;
            }
        },

        isAnyTrackingDelivered(item) {
            if (!item.tracking_info || item.tracking_info.length === 0) {
                // Fallback: check old delivery_status field
                return item.delivery_status === "Delivered";
            }
            return item.tracking_info.some((t) => t.status === "Delivered");
        },

        getLatestDeliveredDate(item) {
            const userTimezone = this.currentTimezone;
            const isLATimezone =
                userTimezone === "America/Los_Angeles" ||
                userTimezone === "America/Pacific" ||
                !userTimezone;

            console.log("=== getLatestDeliveredDate ===");
            console.log("User Timezone        :", userTimezone);
            console.log("Is LA Timezone?      :", isLATimezone);

            let rawDate = null;

            if (!item.tracking_info || item.tracking_info.length === 0) {
                // Fallback to old datedelivered field
                if (
                    item.datedelivered &&
                    item.datedelivered !== "0000-00-00" &&
                    item.datedelivered !== "0000-00-00 00:00:00"
                ) {
                    rawDate = item.datedelivered;
                    console.log("Fallback datedelivered:", rawDate);
                } else {
                    console.log("No valid date found, returning null");
                    return null;
                }
            } else {
                const dates = item.tracking_info
                    .filter(
                        (t) =>
                            t.status === "Delivered" &&
                            t.delivered_date &&
                            t.delivered_date !== "0000-00-00" &&
                            t.delivered_date !== "0000-00-00 00:00:00",
                    )
                    .map((t) => t.delivered_date);

                console.log("Filtered delivered dates:", dates);

                if (dates.length === 0) {
                    console.log("No delivered dates found, returning null");
                    return null;
                }

                rawDate = dates.sort().pop();
                console.log("Latest delivered date (raw):", rawDate);
            }

            // LA user — no conversion needed, extract date part directly
            if (isLATimezone) {
                const result = rawDate.split(" ")[0].split("T")[0];
                console.log(
                    "LA Timezone — skipping conversion. Result:",
                    result,
                );
                console.log("=============================");
                return result;
            }

            // Non-LA user — convert from LA time to user's timezone
            try {
                const isRawFormat =
                    !rawDate.includes("T") &&
                    !rawDate.includes("Z") &&
                    !rawDate.includes("+");

                let date;
                if (isRawFormat) {
                    const isoLike = rawDate.replace(" ", "T");
                    const tempDate = new Date(isoLike);
                    const laWallClock = new Date(
                        new Date(isoLike).toLocaleString("en-US", {
                            timeZone: "America/Los_Angeles",
                        }),
                    );
                    const diff = tempDate - laWallClock;
                    date = new Date(tempDate.getTime() + diff);
                    console.log(
                        "Raw format — interpreted as LA:",
                        laWallClock.toString(),
                    );
                    console.log(
                        "Adjusted UTC date            :",
                        date.toString(),
                    );
                } else {
                    date = new Date(rawDate);
                    console.log(
                        "ISO format parsed            :",
                        date.toString(),
                    );
                }

                const formatter = new Intl.DateTimeFormat("en-CA", {
                    timeZone: userTimezone,
                    year: "numeric",
                    month: "2-digit",
                    day: "2-digit",
                });

                const converted = formatter.format(date);
                console.log("Converted to user TZ         :", converted);
                console.log("=============================");
                return converted;
            } catch (error) {
                console.error("Error converting delivered date:", error);
                return rawDate;
            }
        },

        formatDeliveryDate(dateString) {
            if (
                !dateString ||
                dateString === "0000-00-00" ||
                dateString === "0000-00-00 00:00:00"
            ) {
                return "N/A";
            }
            try {
                // DB stores LA time already — just extract date part, no conversion
                if (
                    !dateString.includes("T") &&
                    !dateString.includes("+") &&
                    !dateString.includes("Z")
                ) {
                    const datePart = dateString.split(" ")[0]; // "2026-02-18"
                    const [year, month, day] = datePart.split("-");
                    const months = [
                        "Jan",
                        "Feb",
                        "Mar",
                        "Apr",
                        "May",
                        "Jun",
                        "Jul",
                        "Aug",
                        "Sep",
                        "Oct",
                        "Nov",
                        "Dec",
                    ];
                    return `${months[parseInt(month) - 1]} ${parseInt(day)}, ${year}`;
                }
                // Has timezone info — safe to parse normally
                const date = new Date(dateString);
                return date.toLocaleDateString("en-US", {
                    year: "numeric",
                    month: "short",
                    day: "numeric",
                    timeZone: this.currentTimezone || "UTC",
                });
            } catch (error) {
                return dateString;
            }
        },
        getDeliveryStatusSeverity(status) {
            const statusMap = {
                Delivered: "success",
                "In Transit": "info",
                "Awaiting Shipment": "warning",
                "Payment Pending": "secondary",
                "Delivery Exception": "danger",
                Cancelled: "danger",
                Refunded: "danger",
                "Not Found": "secondary",
                Unknown: "secondary",
                Active: "info",
                "Delivered (Estimated)": "success",
            };

            return statusMap[status] || "secondary";
        },

        openIncomingCounter() {
            this.showIncomingCounter = true;
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

        clearFilters() {
            this.dateFilters = {
                orderDateFrom: null,
                orderDateTo: null,
                deliveryDateFrom: null,
                deliveryDateTo: null,
            };
        },

        getCurrentDate() {
            return new Date().toISOString().split("T")[0];
        },

        getDeliveryDateForFilter(item) {
            // Try multiple possible delivery date fields
            let dateToUse = null;

            // Check delivery_sort_date first (seems to be in your data based on column config)
            if (
                item.delivery_sort_date &&
                item.delivery_sort_date !== "0000-00-00" &&
                item.delivery_sort_date !== "0000-00-00 00:00:00"
            ) {
                dateToUse = item.delivery_sort_date;
            }
            // Then check datedelivered
            else if (
                item.datedelivered &&
                item.datedelivered !== "0000-00-00" &&
                item.datedelivered !== "0000-00-00 00:00:00"
            ) {
                dateToUse = item.datedelivered;
            }

            if (!dateToUse) return null;

            try {
                // If it's already in YYYY-MM-DD format, return as is
                if (/^\d{4}-\d{2}-\d{2}$/.test(dateToUse)) {
                    return dateToUse;
                }

                // Otherwise convert using timezone
                const date = new Date(dateToUse);
                const options = {
                    timeZone: this.currentTimezone || "UTC",
                    year: "numeric",
                    month: "2-digit",
                    day: "2-digit",
                };
                const formatter = new Intl.DateTimeFormat("en-CA", options);
                return formatter.format(date);
            } catch (error) {
                console.error(
                    "Error converting delivery date for filter:",
                    error,
                    dateToUse,
                );
                return null;
            }
        },
    },
};
</script>

<style scoped>
/* Vertical stacked layout */
.action-buttons-vertical {
    display: flex;
    flex-direction: column; /* Stack vertically */
    gap: 0.25rem;
    align-items: flex-start; /* Align to left */
}

/* Make buttons full width of container */
.action-buttons-vertical .p-button {
    width: 100%;
    justify-content: flex-start;
}

/* Fix button visibility - remove fade effect */
.set-asin-btn,
.remove-asin-btn {
    opacity: 1 !important;
}

/* Ensure buttons are visible on hover */
.set-asin-btn:hover {
    background-color: rgba(34, 197, 94, 0.1) !important;
}

.remove-asin-btn:hover {
    background-color: rgba(239, 68, 68, 0.1) !important;
}

/* Make sure text buttons have proper color */
:deep(.set-asin-btn .p-button-label) {
    color: #22c55e !important;
}

:deep(.remove-asin-btn .p-button-label) {
    color: #ef4444 !important;
}

.search-field :deep(.p-input-icon-left) {
    width: 100%;
}

.search-field :deep(.p-input-icon-left > i) {
    left: 0.75rem;
    color: #6c757d;
}

.search-field :deep(.p-input-icon-left > .p-inputtext) {
    padding-left: 2.5rem;
}

.filter-section {
    margin-bottom: 1rem;
}

.filter-panel {
    width: fit-content;
    max-width: 100%;
}

.filter-row {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    color: #495057;
}

.cursor-pointer {
    cursor: pointer;
}

.form-control[type="date"] {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
}

.form-control-sm[type="date"] {
    height: 32px;
}

/* Filter slide animation */
.filter-slide-enter-active,
.filter-slide-leave-active {
    transition: all 0.3s ease;
}

.filter-slide-enter-from {
    opacity: 0;
    transform: translateY(-10px);
}

.filter-slide-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}

.badge {
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    border-radius: 10px;
}

/* Main Image Cursor */
.main-image {
    cursor: pointer;
    position: relative;
}

.main-image img {
    transition: transform 0.3s ease;
}

.main-image:hover img {
    transform: scale(1.05);
}

/* Zoom Indicator */
.zoom-indicator {
    position: absolute;
    bottom: 1rem;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.75);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    opacity: 0;
    transition: opacity 0.3s;
    pointer-events: none;
    backdrop-filter: blur(4px);
    z-index: 5;
}

.main-image:hover .zoom-indicator {
    opacity: 1;
}

.zoom-indicator i {
    font-size: 1rem;
}

/* Thumbnail Zoom Icon */
.thumbnail {
    position: relative;
}

.thumbnail-zoom-icon {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.thumbnail:hover .thumbnail-zoom-icon {
    opacity: 1;
}

.thumbnail-zoom-icon i {
    color: white;
    font-size: 1.25rem;
}

/* Mobile - Hide zoom indicators */
@media (max-width: 768px) {
    .zoom-indicator {
        font-size: 0.75rem;
        padding: 0.4rem 0.8rem;
    }

    .zoom-indicator i {
        font-size: 0.875rem;
    }

    .thumbnail-zoom-icon {
        display: none;
    }
}
</style>
