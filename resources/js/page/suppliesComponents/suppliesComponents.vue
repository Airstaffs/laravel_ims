<template>
    <div class="vue-container supplies-components-module">
        <!-- Header Section -->
        <div
            class="d-flex align-items-center justify-content-between flex-wrap mb-4"
        >
            <TitlePage
                title="Supplies & Components Module"
                subtitle="Track all supplies, components, and office equipment in inventory."
            />
            <div class="d-flex align-item-end gap-2 px-4">
                <Button
                    severity="secondary"
                    size="small"
                    label="SID List"
                    icon="pi pi-list"
                    icon-pos="left"
                    @click="openSidListModal"
                />
            </div>
        </div>

        <!-- Statistics Cards -->
        <AnimateDiv :delay="100" class="stats-container px-4">
            <div class="stat-card bg-primary-light">
                <div class="stat-icon bg-primary">
                    <i class="pi pi-box text-white"></i>
                </div>
                <div>
                    <p class="mb-0">Total Items</p>
                    <h5 class="mb-0">{{ stats.total || 0 }}</h5>
                </div>
            </div>
            <div class="stat-card bg-info-light">
                <div class="stat-icon bg-info">
                    <i class="pi pi-wrench text-white"></i>
                </div>
                <div>
                    <p class="mb-0">Components</p>
                    <h5 class="mb-0">{{ stats.components || 0 }}</h5>
                </div>
            </div>
            <div class="stat-card bg-success-light">
                <div class="stat-icon bg-success">
                    <i class="pi pi-shopping-bag text-white"></i>
                </div>
                <div>
                    <p class="mb-0">Supplies</p>
                    <h5 class="mb-0">{{ stats.supplies || 0 }}</h5>
                </div>
            </div>
            <div class="stat-card bg-warning-light">
                <div class="stat-icon bg-warning">
                    <i class="pi pi-desktop text-white"></i>
                </div>
                <div>
                    <p class="mb-0">Office Equipment</p>
                    <h5 class="mb-0">{{ stats.office_equipment || 0 }}</h5>
                </div>
            </div>
        </AnimateDiv>

        <!-- Filter -->
        <AnimateDiv :delay="200" class="px-4">
            <div class="search-container">
                <fieldset class="d-flex align-items-center gap-3">
                    <label>Category</label>
                    <Select
                        :options="categoryOptions"
                        v-model="selectedCategory"
                        optionLabel="label"
                        optionValue="value"
                        size="small"
                        class="select-form"
                        @change="changeCategory"
                        placeholder="Select category"
                    />
                </fieldset>
            </div>

            <!-- Desktop Table -->
            <XDataTable
                :value="items"
                :loading="loading"
                :columns="columns"
                :pagination="false"
                tableClass="desktop-view"
                dataKey="product_id"
                scrollable
                scrollHeight="600px"
                :key="'sc-table'"
            >
                <template #gallery="{ data }">
                    <div
                        class="d-flex justify-content-center align-items-center"
                    >
                        <!-- Captured images (priority) -->
                        <div
                            v-if="hasCapturedImages(data)"
                            class="gallery-thumbnail position-relative"
                            @click="openImageModal(data)"
                            style="cursor: pointer"
                        >
                            <img
                                :src="getFirstImage(data)"
                                :alt="data.product_title"
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
                        <!-- Fallback to img1..img5 -->
                        <TableGallery
                            v-else
                            :data="data"
                            :openImageModal="openImageModal"
                            :handleImageError="handleImageError"
                            :countAdditionalImages="countAdditionalImages"
                        />
                    </div>
                </template>

                <template #productName="{ data }">
                    <div class="d-flex flex-column gap-1">
                        <small class="text-muted"
                            >RT# {{ data.rt_counter || "N/A" }}</small
                        >
                        <span class="fw-bold">{{
                            data.product_title || "N/A"
                        }}</span>
                    </div>
                </template>

                <template #category="{ data }">
                    <span>{{ data.category || "N/A" }}</span>
                </template>

                <template #quantity="{ data }">
                    <span>{{ data.quantity || 1 }}</span>
                </template>

                <template #orderDate="{ data }">
                    <span>{{ convertToLocalDate(data.order_date) }}</span>
                </template>

                <template #deliveredDate="{ data }">
                    <span>{{ convertToLocalDate(data.delivered_date) }}</span>
                </template>

                <template #actions="{ data }">
                    <div class="d-flex flex-column align-items-start">
                        <Button
                            label="Move to Labeling"
                            icon="pi pi-tags"
                            size="small"
                            severity="contrast"
                            variant="text"
                            class="text-warning"
                            :loading="moveLabelingLoading"
                            :disabled="moveLabelingLoading"
                            @click="moveToLabeling(data)"
                        />
                        <Button
                            label="Setup SID"
                            icon="pi pi-link"
                            size="small"
                            severity="contrast"
                            variant="text"
                            class="text-info"
                            @click="openSetupSidModal(data)"
                        />
                    </div>
                </template>
            </XDataTable>
        </AnimateDiv>

        <!-- Mobile Cards View -->
        <div class="mobile-view px-3">
            <div v-if="loading" class="loading-spinner-mobile">
                <i class="pi pi-spin pi-spinner"></i> Loading items...
            </div>
            <div v-else-if="items.length === 0" class="no-data-mobile">
                No items found
            </div>
            <div v-else class="mobile-cards">
                <div
                    v-for="item in items"
                    :key="item.product_id"
                    class="mobile-card"
                >
                    <div class="mobile-card-header">
                        <!-- Mobile image -->
                        <div class="mobile-product-image clickable">
                            <div
                                v-if="hasCapturedImages(item)"
                                class="gallery-thumbnail position-relative"
                                @click="openImageModal(item)"
                                style="cursor: pointer"
                            >
                                <img
                                    :src="getFirstImage(item)"
                                    :alt="item.product_title"
                                    class="product-thumbnail clickable-image"
                                    @error="handleImageError"
                                />
                                <div
                                    class="image-count-badge"
                                    v-if="countAllImages(item) > 1"
                                >
                                    +{{ countAllImages(item) - 1 }}
                                </div>
                            </div>
                            <div
                                v-else
                                @click="openImageModal(item)"
                                style="cursor: pointer"
                            >
                                <img
                                    :src="
                                        item.img1
                                            ? '/images/thumbnails/' + item.img1
                                            : defaultImage
                                    "
                                    :alt="item.product_title"
                                    class="product-thumbnail clickable-image"
                                    @error="handleImageError"
                                />
                                <div
                                    class="image-count-badge"
                                    v-if="countAdditionalImages(item) > 0"
                                >
                                    +{{ countAdditionalImages(item) }}
                                </div>
                            </div>
                        </div>

                        <div class="mobile-product-info">
                            <h6 class="mobile-product-name">
                                <span style="font-size: 1rem"
                                    >RT# : {{ item.rt_counter || "N/A" }}</span
                                >
                                <span>{{ item.product_title || "N/A" }}</span>
                            </h6>
                        </div>
                    </div>

                    <Divider />

                    <div class="mobile-card-details">
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">Category:</span>
                            <span class="mobile-detal-value">{{
                                item.category || "N/A"
                            }}</span>
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">Quantity:</span>
                            <span class="mobile-detal-value">{{
                                item.quantity || 1
                            }}</span>
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">Order Date:</span>
                            <span class="mobile-detal-value">{{
                                formatDate(item.order_date)
                            }}</span>
                        </div>
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label"
                                >Delivered Date:</span
                            >
                            <span class="mobile-detal-value">{{
                                formatDate(item.delivered_date)
                            }}</span>
                        </div>
                    </div>

                    <Divider />

                    <div class="d-flex flex-nowrap overflow-auto gap-2 pb-3">
                        <div class="flex-shrink-0">
                            <Button
                                label="Move to Labeling"
                                icon="pi pi-tags"
                                size="small"
                                severity="warn"
                                :loading="moveLabelingLoading"
                                :disabled="moveLabelingLoading"
                                @click="moveToLabeling(item)"
                            />
                        </div>
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
            :ProductTitle="modalProductTitle"
            :regularImages="regularImages"
            :capturedImages="capturedImages"
            :handleImageError="handleImageError"
        />

        <!-- SID List Dialog -->
        <Dialog
            v-model:visible="showSidListModal"
            :modal="true"
            :draggable="false"
            :style="{ width: '75rem' }"
            :breakpoints="{ '1199px': '90vw', '767px': '95vw' }"
        >
            <!-- Custom Header -->
            <template #header>
                <div
                    class="d-flex align-items-center justify-content-between w-100"
                >
                    <span class="fw-semibold" style="font-size: 1rem"
                        >SID List</span
                    >
                    <div class="d-flex align-items-center gap-2 me-3">
                        <Button
                            icon="pi pi-refresh"
                            label="Refresh"
                            severity="secondary"
                            size="small"
                            :loading="sidListLoading"
                            @click="fetchSidList"
                        />
                        <Button
                            icon="pi pi-plus"
                            label="Add Data"
                            severity="primary"
                            size="small"
                            @click="openAddSidModal"
                        />
                    </div>
                </div>
            </template>

            <DataTable
                :value="sidListItems"
                :loading="sidListLoading"
                scrollable
                scroll-height="480px"
                striped-rows
                show-gridlines
                size="small"
            >
                <!-- Image Column -->
                <Column
                    header="Image"
                    style="min-width: 5rem; text-align: center"
                >
                    <template #body="{ data }">
                        <img
                            :src="
                                data.image_path
                                    ? `/images/sid/${data.image_path}`
                                    : defaultImage
                            "
                            alt="SID Image"
                            style="
                                width: 40px;
                                height: 40px;
                                object-fit: cover;
                                border-radius: 4px;
                                border: 1px solid #dee2e6;
                                cursor: pointer;
                            "
                            @error="handleSidImageError"
                            @click="openViewSidModal(data)"
                        />
                    </template>
                </Column>

                <Column
                    field="sid_number"
                    header="SID Number"
                    style="min-width: 10rem"
                />

                <Column field="alias" header="Alias" style="min-width: 14rem" />

                <Column field="price" header="Price" style="min-width: 8rem">
                    <template #body="{ data }">
                        {{
                            data.price != null
                                ? `$${Number(data.price).toFixed(2)}`
                                : "—"
                        }}
                    </template>
                </Column>

                <Column
                    field="quantity"
                    header="Quantity"
                    style="min-width: 7rem"
                />

                <Column
                    field="threshold"
                    header="Threshold"
                    style="min-width: 8rem"
                />

                <Column header="Action">
                    <template #body="{ data }">
                        <div class="d-flex flex-column align-items-start gap-1">
                            <Button
                                icon="pi pi-eye"
                                label="View"
                                severity="info"
                                size="small"
                                text
                                @click="openViewSidModal(data)"
                            />
                            <Button
                                icon="pi pi-pencil"
                                label="Edit"
                                severity="warning"
                                size="small"
                                text
                                @click="openEditSidModal(data)"
                            />
                            <Button
                                icon="pi pi-trash"
                                label="Delete"
                                severity="danger"
                                size="small"
                                text
                                rounded
                                @click="deleteSidEntry(data)"
                            />
                        </div>
                    </template>
                </Column>

                <template #empty>
                    <div class="text-center py-4 text-muted">
                        No SID entries found.
                    </div>
                </template>
            </DataTable>
        </Dialog>

        <!-- Add SID Dialog -->
        <Dialog
            v-model:visible="showAddSidModal"
            header="Add SID Entry"
            :modal="true"
            :draggable="false"
            :style="{ width: '30rem' }"
        >
            <div class="d-flex flex-column gap-3 pt-2">
                <div>
                    <label class="form-label fw-semibold"
                        >SID Number <span class="text-danger">*</span></label
                    >
                    <InputText
                        v-model="sidForm.sid_number"
                        class="w-100"
                        placeholder="Enter SID number"
                    />
                </div>
                <div>
                    <label class="form-label fw-semibold">Alias</label>
                    <InputText
                        v-model="sidForm.alias"
                        class="w-100"
                        placeholder="Enter alias"
                    />
                </div>
                <div>
                    <label class="form-label fw-semibold">Price</label>
                    <InputNumber
                        v-model="sidForm.price"
                        class="w-100"
                        mode="currency"
                        currency="USD"
                        locale="en-US"
                        placeholder="0.00"
                    />
                </div>
                <div>
                    <label class="form-label fw-semibold">Quantity</label>
                    <InputNumber
                        v-model="sidForm.quantity"
                        class="w-100"
                        :min="0"
                        placeholder="0"
                    />
                </div>
                <div>
                    <label class="form-label fw-semibold">Threshold</label>
                    <InputNumber
                        v-model="sidForm.threshold"
                        class="w-100"
                        :min="0"
                        placeholder="0"
                    />
                </div>
            </div>

            <template #footer>
                <Button
                    label="Cancel"
                    severity="secondary"
                    size="small"
                    @click="closeAddSidModal"
                />
                <Button
                    label="Save"
                    icon="pi pi-save"
                    severity="primary"
                    size="small"
                    :loading="addSidLoading"
                    @click="submitAddSid"
                />
            </template>
        </Dialog>

        <!-- View SID Dialog -->
        <Dialog
            v-model:visible="showViewSidModal"
            :modal="true"
            :draggable="false"
            :style="{ width: '50rem' }"
            :breakpoints="{ '1199px': '90vw', '767px': '95vw' }"
        >
            <template #header>
                <span class="fw-semibold" style="font-size: 1rem"
                    >View SID Entry</span
                >
            </template>

            <div v-if="viewSidItem" class="p-2">
                <!-- SID Info -->
                <div class="mb-3">
                    <div>
                        <div>
                            <strong
                                >SID#{{
                                    viewSidItem.sid_number.replace(/^SID/i, "")
                                }}</strong
                            >
                        </div>
                    </div>
                    <div>
                        <strong>ALIAS:</strong> {{ viewSidItem.alias || "—" }}
                    </div>
                    <div>
                        <strong>PRICE:</strong> ${{
                            Number(viewSidItem.price || 0).toFixed(2)
                        }}
                    </div>
                    <div>
                        <strong>QUANTITY:</strong>
                        {{ viewSidItem.quantity ?? 0 }}
                    </div>
                    <div>
                        <strong>THRESHOLD:</strong>
                        {{ viewSidItem.threshold ?? 0 }}
                    </div>
                </div>

                <Divider />

                <!-- Image Display -->
                <div class="mb-3">
                    <div
                        v-if="viewSidItem.image_path"
                        class="position-relative d-inline-block"
                    >
                        <img
                            :src="`/images/sid/${viewSidItem.image_path}`"
                            alt="SID Image"
                            style="
                                max-width: 100%;
                                max-height: 300px;
                                object-fit: contain;
                                border: 1px solid #dee2e6;
                                border-radius: 4px;
                            "
                            @error="handleSidImageError"
                        />
                        <Button
                            icon="pi pi-trash"
                            severity="danger"
                            size="small"
                            rounded
                            text
                            class="position-absolute"
                            style="bottom: 4px; right: 4px"
                            :loading="deleteSidImageLoading"
                            @click="deleteSidImage(viewSidItem)"
                        />
                    </div>

                    <!-- No image placeholder -->
                    <div
                        v-else
                        class="d-flex align-items-center justify-content-center bg-light"
                        style="
                            width: 100%;
                            height: 200px;
                            border: 1px dashed #ced4da;
                            border-radius: 4px;
                            color: #aaa;
                        "
                    >
                        <div class="text-center">
                            <i class="pi pi-image" style="font-size: 2rem"></i>
                            <div class="mt-1" style="font-size: 0.85rem">
                                No image uploaded
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upload -->
                <div class="d-flex align-items-center gap-2 mt-2">
                    <input
                        ref="sidImageInput"
                        type="file"
                        accept="image/*"
                        style="font-size: 0.875rem"
                        @change="onSidImageSelected"
                    />
                    <Button
                        label="Upload"
                        icon="pi pi-upload"
                        severity="primary"
                        size="small"
                        :loading="uploadSidImageLoading"
                        :disabled="!sidImageFile"
                        @click="uploadSidImage(viewSidItem)"
                    />
                </div>
            </div>
        </Dialog>

        <!-- Edit SID Dialog -->
        <Dialog
            v-model:visible="showEditSidModal"
            header="Edit SID Entry"
            :modal="true"
            :draggable="false"
            :style="{ width: '30rem' }"
            :breakpoints="{ '1199px': '90vw', '767px': '95vw' }"
        >
            <div class="d-flex flex-column gap-3 pt-2">
                <div>
                    <label class="form-label fw-semibold"
                        >SID Number <span class="text-danger">*</span></label
                    >
                    <InputText
                        v-model="editSidForm.sid_number"
                        class="w-100"
                        placeholder="Enter SID number"
                    />
                </div>
                <div>
                    <label class="form-label fw-semibold">Alias</label>
                    <InputText
                        v-model="editSidForm.alias"
                        class="w-100"
                        placeholder="Enter alias"
                    />
                </div>
                <div>
                    <label class="form-label fw-semibold">Price</label>
                    <InputNumber
                        v-model="editSidForm.price"
                        class="w-100"
                        mode="currency"
                        currency="USD"
                        locale="en-US"
                        placeholder="0.00"
                    />
                </div>
                <div>
                    <label class="form-label fw-semibold">Quantity</label>
                    <InputNumber
                        v-model="editSidForm.quantity"
                        class="w-100"
                        :min="0"
                        placeholder="0"
                    />
                </div>
                <div>
                    <label class="form-label fw-semibold">Threshold</label>
                    <InputNumber
                        v-model="editSidForm.threshold"
                        class="w-100"
                        :min="0"
                        placeholder="0"
                    />
                </div>
            </div>

            <template #footer>
                <Button
                    label="Cancel"
                    severity="secondary"
                    size="small"
                    @click="closeEditSidModal"
                />
                <Button
                    label="Save Changes"
                    icon="pi pi-check"
                    severity="primary"
                    size="small"
                    :loading="editSidLoading"
                    @click="submitEditSid"
                />
            </template>
        </Dialog>

        <!-- Setup SID Dialog -->
        <Dialog
            v-model:visible="showSetupSidModal"
            header="Setup SID"
            :modal="true"
            :draggable="false"
            :style="{ width: '35rem' }"
            :breakpoints="{ '1199px': '90vw', '767px': '95vw' }"
        >
            <div v-if="setupSidProduct" class="mb-3 p-2 bg-light rounded">
                <div class="fw-semibold" style="font-size: 0.85rem">
                    {{ setupSidProduct.product_title }}
                </div>
                <div class="text-muted" style="font-size: 0.8rem">
                    RT# {{ setupSidProduct.rt_counter }}
                </div>
            </div>

            <!-- Currently assigned SID -->
            <div v-if="setupSidCurrent" class="mb-3">
                <label class="form-label fw-semibold" style="font-size: 0.85rem"
                    >Currently Assigned</label
                >
                <div
                    class="d-flex align-items-center justify-content-between p-2 border rounded"
                >
                    <div>
                        <span class="fw-semibold"
                            >SID#{{
                                setupSidCurrent.sid_number.replace(/^SID/i, "")
                            }}</span
                        >
                        <span
                            class="text-muted ms-2"
                            style="font-size: 0.82rem"
                            >{{ setupSidCurrent.alias || "—" }}</span
                        >
                    </div>
                    <Button
                        icon="pi pi-times"
                        label="Unlink"
                        severity="danger"
                        size="small"
                        text
                        :loading="unlinkSidLoading"
                        @click="unlinkSid"
                    />
                </div>
            </div>

            <Divider v-if="setupSidCurrent" />

            <!-- Search & Select SID -->
            <div>
                <label class="form-label fw-semibold">
                    {{
                        setupSidCurrent
                            ? "Replace with another SID"
                            : "Select a SID to assign"
                    }}
                </label>
                <div class="d-flex gap-2 mb-2">
                    <InputText
                        v-model="sidSearchQuery"
                        class="w-100"
                        placeholder="Search by SID number or alias..."
                        @input="filterSidOptions"
                    />
                </div>

                <!-- SID Options List -->
                <div
                    style="
                        max-height: 220px;
                        overflow-y: auto;
                        border: 1px solid #dee2e6;
                        border-radius: 6px;
                    "
                >
                    <div
                        v-if="filteredSidOptions.length === 0"
                        class="text-center text-muted py-3"
                        style="font-size: 0.85rem"
                    >
                        No SID entries found.
                    </div>
                    <div
                        v-for="sid in filteredSidOptions"
                        :key="sid.id"
                        class="d-flex align-items-center justify-content-between px-3 py-2"
                        style="
                            cursor: pointer;
                            border-bottom: 1px solid #f1f1f1;
                            font-size: 0.875rem;
                        "
                        :class="
                            selectedSidId === sid.id
                                ? 'bg-primary text-white'
                                : 'hover-bg-light'
                        "
                        @click="selectedSidId = sid.id"
                    >
                        <div>
                            <span class="fw-semibold"
                                >SID#{{
                                    sid.sid_number.replace(/^SID/i, "")
                                }}</span
                            >
                            <span
                                class="ms-2"
                                :class="
                                    selectedSidId === sid.id
                                        ? 'text-white-50'
                                        : 'text-muted'
                                "
                                style="font-size: 0.8rem"
                            >
                                {{ sid.alias || "—" }}
                            </span>
                        </div>
                        <div
                            class="ms-2"
                            :class="
                                selectedSidId === sid.id
                                    ? 'text-white-50'
                                    : 'text-muted'
                            "
                            style="font-size: 0.8rem"
                        >
                            Qty: {{ sid.quantity }}
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button
                    label="Cancel"
                    severity="secondary"
                    size="small"
                    @click="closeSetupSidModal"
                />
                <Button
                    label="Assign SID"
                    icon="pi pi-check"
                    severity="primary"
                    size="small"
                    :disabled="!selectedSidId"
                    :loading="assignSidLoading"
                    @click="submitAssignSid"
                />
            </template>
        </Dialog>

        <ScrollTop />
    </div>
</template>

<script>
import {
    Badge,
    Button,
    Divider,
    ScrollTop,
    Select,
    Paginator,
    Dialog,
    DataTable,
    Column,
    InputText,
    InputNumber,
} from "primevue";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import TableGallery from "../../components/Gallery/tableGallery.vue";
import ViewImageGalleryModal from "../../components/ViewImageGalleryModal/ViewImageGalleryModal.vue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";
import suppliesComponentsModule from "./suppliesComponents.js";
import { ROWS_PER_PAGE } from "../../constant.js";

const TABLE_COLUMNS = [
    {
        header: "Gallery",
        slot: "gallery",
        style: { width: "4rem", minWidth: "4rem" },
    },
    {
        header: "Product Name",
        slot: "productName",
        bodyStyle: "font-size:14px",
        style: { minWidth: "22rem" },
    },
    {
        header: "Category",
        slot: "category",
        bodyStyle: "font-size:14px",
        style: { minWidth: "10rem" },
    },
    {
        header: "Quantity",
        slot: "quantity",
        bodyStyle: "font-size:14px",
        style: { minWidth: "8rem" },
    },
    {
        header: "Order Date",
        slot: "orderDate",
        bodyStyle: "font-size:14px",
        style: { minWidth: "12rem" },
    },
    {
        header: "Delivered Date",
        slot: "deliveredDate",
        bodyStyle: "font-size:14px",
        style: { minWidth: "12rem" },
    },
];

export default {
    mixins: [suppliesComponentsModule],
    components: {
        XDataTable,
        TableGallery,
        ViewImageGalleryModal,
        Button,
        Select,
        Badge,
        Divider,
        ScrollTop,
        TitlePage,
        AnimateDiv,
        Paginator,
        Dialog,
        DataTable,
        Column,
        InputText,
        InputNumber,
    },
    data() {
        return {
            columns: TABLE_COLUMNS,
            rowsPerPageOptions: ROWS_PER_PAGE,
            categoryOptions: [
                { label: "All Categories", value: "" },
                { label: "Components", value: "Components" },
                { label: "Supplies", value: "Supplies" },
                { label: "Office Equipment", value: "Office Equipment" },
            ],

            currentTimezone: "UTC",
            timezoneLabel: "Loading...",
        };
    },
    computed: {
        // ✅ ADD THESE COMPUTED PROPERTIES FOR DATE CONVERSION
        localOrderDate() {
            return this.convertToLocalDate(this.item.order_date);
        },
        localDeliveredDate: {
            get() {
                return this.convertToLocalDate(this.item.delivered_date);
            },
            set(value) {
                this.item.delivered_date = this.convertFromLocalDate(value);
            },
        },
    },
    methods: {
        getCategorySeverity(category) {
            switch (category) {
                case "Components":
                    return "info";
                case "Supplies":
                    return "success";
                case "Office Equipment":
                    return "warning";
                default:
                    return "secondary";
            }
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
    },
};
</script>

<style scoped>
@import "./suppliesComponents.css";
</style>
