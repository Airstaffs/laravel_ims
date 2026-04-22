<template>
    <div class="vue-container asin-viewer-module">
        <!-- <h2 class="module-title">ASIN List Manager</h2> -->

        <div
            class="d-flex align-items-center justify-content-between flex-wrap pe-4"
        >
            <TitlePage title="ASIN List Manager" />
            <div class="d-flex align-item-end gap-2">
                <Button
                    severity="secondary"
                    size="small"
                    label="Global ASIN Configuration"
                    icon="pi pi-globe"
                    icon-pos="left"
                    @click="openGlobalConfig"
                />
                <Button
                    severity="success"
                    size="small"
                    @click="openBulkInstructionCardModal"
                    title="Bulk upload instruction cards for multiple ASINs"
                    label="Instruction Card Bulk "
                    icon="pi pi-upload"
                />
            </div>
        </div>
        <div class="px-4">
            <div class="search-container">
                <fieldset class="d-flex align-items-center gap-1">
                    <label>Store:</label>
                    <Select
                        class="select-form"
                        size="small"
                        v-model="selectedStore"
                        @change="changeStore"
                        :options="storeOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Select a store"
                    />
                </fieldset>
            </div>
            <XDataTable
                :value="sortedAsinData"
                :columns="columns"
                :loading="loading"
                :paginator="false"
                tableClass="desktop-view"
                selectionMode="multiple"
                dataKey="ASIN"
            >
                <template #image="{ data }">
                    <div
                        class="product-image-container clickable"
                        @click="viewAsinDetails(data)"
                    >
                        <img
                            :src="
                                data.useDefaultImage
                                    ? defaultImagePath
                                    : getImagePath(data.ASIN)
                            "
                            :alt="data.AStitle"
                            class="product-thumbnail"
                            @error="handleImageError($event, data)"
                        />
                    </div>
                </template>

                <template #productName="{ data }">
                    <div
                        style="
                            word-break: break-word;
                            white-space: normal;
                            overflow-wrap: break-word;
                            flex: 1;
                        "
                    >
                        <!-- Show system_title if available, otherwise show AStitle -->
                        <h6>{{ data.system_title || data.AStitle }}</h6>
                        <h6 v-if="data.metakeyword">{{ data.metakeyword }}</h6>
                    </div>
                </template>

                <template #EANUPC="{ data }">
                    <div class="codes-container">
                        <div v-if="data.EAN" class="code-item">
                            <span class="code-label">EAN:</span>
                            <span class="code-value">{{ data.EAN }}</span>
                        </div>
                        <div v-if="data.UPC" class="code-item">
                            <span class="code-label">UPC:</span>
                            <span class="code-value">{{ data.UPC }}</span>
                        </div>
                        <div v-if="!data.EAN && !data.UPC" class="no-codes">
                            -
                        </div>
                    </div>
                </template>

                <template #relatedAsins="{ data }">
                    <div class="related-asins">
                        <div v-if="data.ParentAsin" class="related-item">
                            <span class="related-label">Parent:</span>
                            <span class="related-value">{{
                                data.ParentAsin
                            }}</span>
                        </div>
                        <div v-if="data.CousinASIN" class="related-item">
                            <span class="related-label">Cousin:</span>
                            <span class="related-value">{{
                                data.CousinASIN
                            }}</span>
                        </div>
                        <div v-if="data.UpgradeASIN" class="related-item">
                            <span class="related-label">Upgrade:</span>
                            <span class="related-value">{{
                                data.UpgradeASIN
                            }}</span>
                        </div>
                        <div v-if="data.GrandASIN" class="related-item">
                            <span class="related-label">Grand:</span>
                            <span class="related-value">{{
                                data.GrandASIN
                            }}</span>
                        </div>
                        <div
                            v-if="
                                !data.ParentAsin &&
                                !data.CousinASIN &&
                                !data.UpgradeASIN &&
                                !data.GrandASIN
                            "
                            class="no-related"
                        >
                            -
                        </div>
                    </div>
                </template>

                <!-- Color Column -->
                <template #color="{ data }">
                    <div class="color-cell">
                        <Select
                            v-model="data.color"
                            :options="colorOptions"
                            @change="updateColor(data)"
                            :disabled="savingColorFor === data.ASIN"
                            :placeholder="data.color || 'Set color'"
                            :pt="{
                                root: {
                                    style: 'width: 120px; font-size: 14px;',
                                },
                            }"
                        />
                        <i
                            v-if="savingColorFor === data.ASIN"
                            class="pi pi-spin pi-spinner"
                            style="margin-left: 8px; color: #007bff"
                        ></i>
                    </div>
                </template>

                <!-- ADD THIS NEW TEMPLATE SLOT -->
                <template #quantityInside="{ data }">
                    <div class="quantity-inside-cell">
                        <Select
                            v-model="data.QuantityInside"
                            :options="[1, 2, 3, 4]"
                            @change="updateQuantityInside(data)"
                            :disabled="savingQuantityFor === data.ASIN"
                            placeholder="-"
                            :pt="{
                                root: {
                                    style: 'width: 80px; font-size: 14px;',
                                },
                            }"
                        />
                        <i
                            v-if="savingQuantityFor === data.ASIN"
                            class="pi pi-spin pi-spinner"
                            style="margin-left: 8px; color: #007bff"
                        ></i>
                    </div>
                </template>

                <template #fnsku_count="{ data }">
                    <div class="fnsku-count">{{ data.fnsku_count }} FNSKUs</div>
                </template>
                <template #actions="{ data }">
                    <div class="d-flex flex-column align-items-start">
                        <!-- <Button :severity="expandedRows[index] ? 'secondary' : 'primary'" outlined size="small"
                            variant="text" :icon="expandedRows[index] ? 'pi pi-eye-slash' : 'pi pi-eye'"
                            :label="expandedRows[index] ? 'Hide FNSKUs' : 'Show FNSKUs'"
                            @click="toggleDetails(index)" /> -->

                        <Button
                            severity="info"
                            size="small"
                            label="Full Details"
                            icon="pi pi-info-circle"
                            icon-pos="left"
                            @click="viewAsinDetails(data)"
                            variant="text"
                        />

                        <Button
                            size="small"
                            label="ASIN Configuration"
                            icon="pi pi-cog"
                            icon-pos="left"
                            @click="openASINConfig(data)"
                            variant="text"
                        />
                    </div>
                </template>
            </XDataTable>
        </div>
        <!-- Desktop Table Container -->

        <!-- Mobile Cards View -->
        <div class="mobile-view">
            <div class="mobile-cards">
                <div v-if="loading" class="loading-spinner-mobile">
                    <i class="fas fa-spinner fa-spin"></i>
                    Loading...
                </div>
                <div
                    v-else-if="sortedAsinData.length === 0"
                    class="no-data-mobile"
                >
                    No data found
                </div>
                <div
                    class="mobile-card"
                    v-else
                    v-for="(item, index) in sortedAsinData"
                    :key="item.ASIN"
                >
                    <div class="mobile-card-header">
                        <div class="mobile-product-image">
                            <img
                                :src="
                                    item.useDefaultImage
                                        ? defaultImagePath
                                        : getImagePath(item.ASIN)
                                "
                                :alt="item.AStitle"
                                class="product-thumbnail-mobile"
                                @error="handleImageError($event, item)"
                            />
                        </div>
                        <div class="mobile-product-info">
                            <h6 class="mobile-product-name">
                                {{ item.system_title || item.AStitle }}
                            </h6>
                        </div>
                    </div>

                    <hr />

                    <div
                        class="mobile-card-details d-flex flex-column gap-2"
                        style="font-size: 14px"
                    >
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">ASIN:</span>
                            <span class="mobile-detail-value text-secondary">{{
                                item.ASIN
                            }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">EAN:</span>
                            <span class="mobile-detail-value text-secondary">{{
                                item.EAN || "-"
                            }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">UPC:</span>
                            <span class="mobile-detail-value text-secondary">{{
                                item.UPC || "-"
                            }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">FNSKUs:</span>
                            <span class="mobile-detail-value text-secondary">{{
                                item.fnsku_count
                            }}</span>
                        </div>
                    </div>

                    <hr />

                    <div class="mobile-card-actions">
                        <button
                            class="btn btn-expand"
                            @click="toggleDetails(index)"
                        >
                            <i class="fas fa-list"></i>
                            {{ expandedRows[index] ? "Hide" : "FNSKUs" }}
                        </button>
                        <button
                            class="btn btn-details"
                            @click="viewAsinDetails(item)"
                        >
                            <i class="fas fa-info-circle"></i> Details
                        </button>
                    </div>

                    <hr v-if="expandedRows[index]" />

                    <div
                        v-if="expandedRows[index]"
                        class="mobile-expanded-content"
                    >
                        <div class="mobile-section">
                            <h4>FNSKUs:</h4>
                            <div class="mobile-fnsku-list">
                                <div
                                    v-for="fnsku in item.fnskus"
                                    :key="fnsku.FNSKU"
                                    class="mobile-fnsku-item"
                                >
                                    <div class="mobile-fnsku-detail">
                                        <span class="mobile-fnsku-label"
                                            >FNSKU:</span
                                        >
                                        <span
                                            class="mobile-fnsku-value fnsku-code"
                                            >{{ fnsku.FNSKU }}</span
                                        >
                                    </div>
                                    <div class="mobile-fnsku-detail">
                                        <span class="mobile-fnsku-label"
                                            >MSKU:</span
                                        >
                                        <span class="mobile-fnsku-value">{{
                                            fnsku.MSKU || "-"
                                        }}</span>
                                    </div>
                                    <div class="mobile-fnsku-detail">
                                        <span class="mobile-fnsku-label"
                                            >Store:</span
                                        >
                                        <span class="mobile-fnsku-value">{{
                                            fnsku.storename
                                        }}</span>
                                    </div>
                                    <div class="mobile-fnsku-detail">
                                        <span class="mobile-fnsku-label"
                                            >Units:</span
                                        >
                                        <span class="mobile-fnsku-value">{{
                                            fnsku.Units || 0
                                        }}</span>
                                    </div>
                                    <div class="mobile-fnsku-detail">
                                        <span class="mobile-fnsku-label"
                                            >Grade:</span
                                        >
                                        <span class="mobile-fnsku-value">{{
                                            fnsku.grading || "-"
                                        }}</span>
                                    </div>
                                </div>
                                <div
                                    v-if="
                                        !item.fnskus || item.fnskus.length === 0
                                    "
                                    class="mobile-empty"
                                >
                                    No FNSKUs found
                                </div>
                            </div>
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

        <!-- ASIN Details Modal -->
        <Dialog
            v-model:visible="showAsinDetailsModal"
            header="ASIN Details"
            :style="{ width: '95%' }"
            modal
            :pt="{
                root: { class: 'mobile-fullscreen-dialog' },
            }"
        >
            <div class="row">
                <div class="col-md-4">
                    <div class="images-section">
                        <!-- ASIN Images Container -->
                        <div class="image-container">
                            <div
                                class="asin-images-main clickable"
                                @click="openAsinImageModal"
                            >
                                <img
                                    :src="
                                        getMainAsinImagePath(selectedAsin.ASIN)
                                    "
                                    :alt="`ASIN images for ${selectedAsin.ASIN}`"
                                    class="asin-images-main-thumbnail"
                                />

                                <!-- Small thumbnails overlay -->
                                <div class="asin-images-thumbnails">
                                    <div
                                        class="small-thumb asin-thumb"
                                        :class="{
                                            'has-image':
                                                getImagePath(
                                                    selectedAsin.ASIN,
                                                ) !== defaultImagePath,
                                        }"
                                    >
                                        <img
                                            :src="
                                                getImagePath(selectedAsin.ASIN)
                                            "
                                            class="small-thumb-img"
                                        />
                                        <span class="thumb-label">IMG</span>
                                    </div>
                                    <div
                                        class="small-thumb vector-thumb"
                                        :class="{
                                            'has-image': hasVectorImage(
                                                selectedAsin.ASIN,
                                            ),
                                        }"
                                    >
                                        <img
                                            :src="
                                                getMainVectorImagePath(
                                                    selectedAsin.ASIN,
                                                )
                                            "
                                            class="small-thumb-img"
                                        />
                                        <span class="thumb-label">VEC</span>
                                    </div>
                                </div>
                            </div>
                            <div class="image-label">ASIN Images</div>
                        </div>

                        <!-- Instruction Card Container -->
                        <div class="image-container">
                            <div
                                class="instruction-card-main clickable"
                                @click="openInstructionCardModal"
                            >
                                <img
                                    :src="
                                        getMainInstructionCardPath(
                                            selectedAsin.ASIN,
                                        )
                                    "
                                    :alt="`Instruction cards for ${selectedAsin.ASIN}`"
                                    class="instruction-card-main-thumbnail"
                                />

                                <!-- Small thumbnails overlay -->
                                <div class="instruction-card-thumbnails">
                                    <div
                                        class="small-thumb"
                                        :class="{
                                            'has-image': hasInstructionCard(
                                                selectedAsin.ASIN,
                                                1,
                                            ),
                                        }"
                                    >
                                        <img
                                            :src="
                                                getInstructionCardPath(
                                                    selectedAsin.ASIN,
                                                    1,
                                                )
                                            "
                                            class="small-thumb-img"
                                        />
                                        <span class="thumb-number">1</span>
                                    </div>
                                    <div
                                        class="small-thumb"
                                        :class="{
                                            'has-image': hasInstructionCard(
                                                selectedAsin.ASIN,
                                                2,
                                            ),
                                        }"
                                    >
                                        <img
                                            :src="
                                                getInstructionCardPath(
                                                    selectedAsin.ASIN,
                                                    2,
                                                )
                                            "
                                            class="small-thumb-img"
                                        />
                                        <span class="thumb-number">2</span>
                                    </div>
                                    <div
                                        class="small-thumb"
                                        :class="{
                                            'has-image': hasInstructionCard(
                                                selectedAsin.ASIN,
                                                3,
                                            ),
                                        }"
                                    >
                                        <img
                                            :src="
                                                getInstructionCardPath(
                                                    selectedAsin.ASIN,
                                                    3,
                                                )
                                            "
                                            class="small-thumb-img"
                                        />
                                        <span class="thumb-number">3</span>
                                    </div>
                                </div>
                            </div>
                            <div class="image-label">Instruction Cards</div>
                        </div>

                        <!-- User Manual Container -->
                        <div class="image-container">
                            <div
                                class="user-manual-container"
                                :class="{
                                    'has-manual': hasUserManual(
                                        selectedAsin.ASIN,
                                    ),
                                }"
                            >
                                <div
                                    class="user-manual-icon"
                                    v-if="hasUserManual(selectedAsin.ASIN)"
                                >
                                    <a
                                        :href="
                                            getUserManualPath(selectedAsin.ASIN)
                                        "
                                        target="_blank"
                                        class="user-manual-link"
                                    >
                                        <i class="fas fa-file-pdf"></i>
                                        <span>View Manual</span>
                                    </a>
                                </div>
                                <div class="user-manual-icon no-manual" v-else>
                                    <i class="fas fa-file-pdf"></i>
                                    <span>No Manual</span>
                                </div>

                                <!-- Upload section for edit mode -->
                                <div v-if="editMode" class="user-manual-upload">
                                    <input
                                        type="file"
                                        ref="userManualUpload"
                                        @change="handleUserManualUpload"
                                        accept="application/pdf"
                                        style="display: none"
                                    />
                                    <button
                                        class="btn-upload-manual"
                                        @click="$refs.userManualUpload.click()"
                                        :disabled="userManualUploading"
                                    >
                                        <i class="fas fa-upload"></i>
                                        {{
                                            userManualUploading
                                                ? "Uploading..."
                                                : "Upload PDF"
                                        }}
                                    </button>
                                </div>
                            </div>
                            <div class="image-label">User Manual</div>
                        </div>
                    </div>
                    <div class="">
                        <h3 class="asin-details-title">
                            {{ selectedAsin.AStitle }}
                        </h3>

                        <!-- Amazon Dimensions Section (Read-only) -->
                        <div class="details-section amazon-dimensions">
                            <h4 class="section-title">
                                Amazon Dimensions (Read-only)
                            </h4>
                            <div class="dimensions-grid">
                                <div class="dimension-item">
                                    <div class="dimension-label">
                                        AMZN Length:
                                    </div>
                                    <div class="dimension-value">
                                        {{
                                            selectedAsin.dimension_length || "-"
                                        }}
                                    </div>
                                </div>
                                <div class="dimension-item">
                                    <div class="dimension-label">
                                        AMZN Width:
                                    </div>
                                    <div class="dimension-value">
                                        {{
                                            selectedAsin.dimension_width || "-"
                                        }}
                                    </div>
                                </div>
                                <div class="dimension-item">
                                    <div class="dimension-label">
                                        AMZN Height:
                                    </div>
                                    <div class="dimension-value">
                                        {{
                                            selectedAsin.dimension_height || "-"
                                        }}
                                    </div>
                                </div>
                                <div class="dimension-item">
                                    <div class="dimension-label">
                                        AMZN Weight:
                                    </div>
                                    <div class="dimension-value">
                                        {{
                                            selectedAsin.weight_value
                                                ? `${
                                                      selectedAsin.weight_value
                                                  } ${
                                                      selectedAsin.weight_unit ||
                                                      ""
                                                  }`
                                                : "-"
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Stores Section -->
                        <div
                            class="asin-details-stores-section"
                            v-if="
                                getUniqueStores(selectedAsin.fnskus).length > 0
                            "
                        >
                            <h4>Stores</h4>
                            <div class="stores-list">
                                <div
                                    v-for="store in getUniqueStores(
                                        selectedAsin.fnskus,
                                    )"
                                    :key="store"
                                    class="store-item"
                                >
                                    {{ store }}
                                </div>
                            </div>
                        </div>

                        <!-- Basic Information Section -->
                        <div class="details-section mt-4">
                            <h4 class="section-title">Basic Information</h4>
                            <div class="asin-details-row">
                                <span class="asin-details-label">ASIN:</span>
                                <span class="asin-details-value">{{
                                    selectedAsin.ASIN
                                }}</span>
                            </div>

                            <!-- NEW: System Title Field -->
                            <div class="asin-details-row">
                                <span class="asin-details-label"
                                    >System Title:</span
                                >
                                <textarea
                                    v-if="editMode"
                                    v-model="editedAsin.system_title"
                                    class="details-textarea"
                                    placeholder="Enter custom system title (overrides product name)"
                                    rows="2"
                                ></textarea>
                                <span v-else class="asin-details-value">
                                    {{ selectedAsin.system_title || "-" }}
                                </span>
                            </div>

                            <!-- Amazon Title (read-only reference) -->
                            <div class="asin-details-row">
                                <span class="asin-details-label"
                                    >Amazon Title:</span
                                >
                                <span
                                    class="asin-details-value"
                                    style="font-size: 11px; color: #6c757d"
                                >
                                    {{ selectedAsin.AStitle }}
                                </span>
                            </div>

                            <div class="asin-details-row">
                                <span class="asin-details-label"
                                    >Meta Keyword:</span
                                >
                                <textarea
                                    v-if="editMode"
                                    v-model="editedAsin.metakeyword"
                                    class="details-textarea"
                                    placeholder="Enter meta keywords"
                                    rows="2"
                                ></textarea>
                                <span v-else class="asin-details-value">{{
                                    selectedAsin.metakeyword || "-"
                                }}</span>
                            </div>
                            <div class="asin-details-row">
                                <span class="asin-details-label">EAN:</span>
                                <input
                                    v-if="editMode"
                                    v-model="editedAsin.EAN"
                                    class="details-input"
                                    placeholder="Enter EAN"
                                />
                                <span v-else class="asin-details-value">{{
                                    selectedAsin.EAN || "-"
                                }}</span>
                            </div>
                            <div class="asin-details-row">
                                <span class="asin-details-label">UPC:</span>
                                <input
                                    v-if="editMode"
                                    v-model="editedAsin.UPC"
                                    class="details-input"
                                    placeholder="Enter UPC"
                                />
                                <span v-else class="asin-details-value">{{
                                    selectedAsin.UPC || "-"
                                }}</span>
                            </div>
                            <div class="asin-details-row">
                                <span class="asin-details-label"
                                    >Instruction Link:</span
                                >
                                <input
                                    v-if="editMode"
                                    v-model="editedAsin.instructionlink"
                                    class="details-input instruction-link-input"
                                    placeholder="Enter instruction link URL"
                                    type="text"
                                />
                                <span v-else class="asin-details-value">
                                    <a
                                        v-if="selectedAsin.instructionlink"
                                        :href="selectedAsin.instructionlink"
                                        target="_blank"
                                        class="instruction-link"
                                    >
                                        <i class="fas fa-external-link-alt"></i>
                                        View Instructions
                                    </a>
                                    <span v-else>-</span>
                                </span>
                            </div>
                            <div class="asin-details-row">
                                <span class="asin-details-label"
                                    >Transparency QR:</span
                                >
                                <textarea
                                    v-if="editMode"
                                    v-model="editedAsin.TRANSPARENCY_QR_STATUS"
                                    class="details-textarea"
                                    placeholder="Enter transparency QR status"
                                    rows="3"
                                ></textarea>
                                <span v-else class="asin-details-value">{{
                                    selectedAsin.TRANSPARENCY_QR_STATUS || "-"
                                }}</span>
                            </div>
                            <div class="asin-details-row">
                                <span class="asin-details-label"
                                    >User Manual:</span
                                >
                                <span class="asin-details-value">
                                    <a
                                        v-if="hasUserManual(selectedAsin.ASIN)"
                                        :href="
                                            getUserManualPath(selectedAsin.ASIN)
                                        "
                                        target="_blank"
                                        class="user-manual-link-text"
                                    >
                                        <i class="fas fa-file-pdf"></i>
                                        View PDF Manual
                                    </a>
                                    <span v-else>-</span>
                                </span>
                            </div>
                            <div class="asin-details-row">
                                <span class="asin-details-label"
                                    >Total FNSKUs:</span
                                >
                                <span class="asin-details-value">{{
                                    selectedAsin.fnsku_count
                                }}</span>
                            </div>
                            <div class="asin-details-row">
                                <span class="asin-details-label"
                                    >FNSKU Usage Limit:</span
                                >

                                <span class="asin-details-value">
                                    <span
                                        class="d-flex justify-content-end align-items-center"
                                    >
                                        <!-- Editable input -->

                                        <input
                                            v-if="editMode"
                                            type="number"
                                            v-model="editedAsin.asin_limit"
                                            class="details-input"
                                            style="width: 60px"
                                        />
                                        <span v-else>{{
                                            selectedAsin.asin_limit || 0
                                        }}</span>
                                    </span>
                                </span>
                            </div>

                            <!-- Save button for ASIN details -->
                            <div v-if="editMode" class="asin-details-actions">
                                <button
                                    class="btn-save-asin-details"
                                    @click="saveAsinDetails"
                                    :disabled="savingAsinDetails"
                                >
                                    <i class="fas fa-save"></i>
                                    {{
                                        savingAsinDetails
                                            ? "Saving..."
                                            : "Save Basic Details"
                                    }}
                                </button>
                            </div>
                        </div>

                        <!-- Amazon Dimensions Section (Read-only) -->
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="row">
                        <div class="col-md-6">
                            <!-- Default Dimensions Section (Editable) -->
                            <div class="details-section default-dimensions">
                                <h4 class="section-title">
                                    Default Dimensions (Editable)
                                </h4>
                                <div class="dimensions-grid">
                                    <div class="dimension-item">
                                        <div class="dimension-label">
                                            Def Length:
                                        </div>
                                        <div class="dimension-value">
                                            <input
                                                v-if="editMode"
                                                v-model="editedAsin.def_length"
                                                class="dimension-input"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                placeholder="0.00"
                                            />
                                            <span v-else>{{
                                                selectedAsin.white_length || "-"
                                            }}</span>
                                        </div>
                                    </div>
                                    <div class="dimension-item">
                                        <div class="dimension-label">
                                            Def Width:
                                        </div>
                                        <div class="dimension-value">
                                            <input
                                                v-if="editMode"
                                                v-model="editedAsin.def_width"
                                                class="dimension-input"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                placeholder="0.00"
                                            />
                                            <span v-else>{{
                                                selectedAsin.white_width || "-"
                                            }}</span>
                                        </div>
                                    </div>
                                    <div class="dimension-item">
                                        <div class="dimension-label">
                                            Def Height:
                                        </div>
                                        <div class="dimension-value">
                                            <input
                                                v-if="editMode"
                                                v-model="editedAsin.def_height"
                                                class="dimension-input"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                placeholder="0.00"
                                            />
                                            <span v-else>{{
                                                selectedAsin.white_height || "-"
                                            }}</span>
                                        </div>
                                    </div>
                                    <div class="dimension-item">
                                        <div class="dimension-label">
                                            Def Weight:
                                        </div>
                                        <div class="dimension-value">
                                            <div
                                                v-if="editMode"
                                                class="weight-input-group"
                                            >
                                                <input
                                                    v-model="
                                                        editedAsin.def_weight
                                                    "
                                                    class="dimension-input weight-value"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    placeholder="0.00"
                                                />
                                                <select
                                                    v-model="
                                                        editedAsin.def_weight_unit
                                                    "
                                                    class="weight-unit-select"
                                                >
                                                    <option value="">
                                                        Unit
                                                    </option>
                                                    <option value="kg">
                                                        kg
                                                    </option>
                                                    <option value="lbs">
                                                        lbs
                                                    </option>
                                                    <option value="g">g</option>
                                                    <option value="oz">
                                                        oz
                                                    </option>
                                                </select>
                                            </div>
                                            <span v-else>
                                                {{
                                                    selectedAsin.white_value
                                                        ? `${
                                                              selectedAsin.white_value
                                                          } ${
                                                              selectedAsin.white_unit ||
                                                              ""
                                                          }`
                                                        : "-"
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Save button for default dimensions -->
                                <div v-if="editMode" class="dimensions-actions">
                                    <button
                                        class="btn-save-dimensions"
                                        @click="saveDefaultDimensions"
                                        :disabled="savingDefaultDimensions"
                                    >
                                        <i class="fas fa-save"></i>
                                        {{
                                            savingDefaultDimensions
                                                ? "Saving..."
                                                : "Save Dimensions"
                                        }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <!-- Related ASINs Section -->
                            <div class="asin-details-related-section">
                                <div class="related-asins-details">
                                    <h5
                                        class="related-dimensions section-title mt-1"
                                    >
                                        Related ASINs
                                    </h5>
                                    <div class="related-asin-item">
                                        <span class="related-asin-label"
                                            >Parent ASIN:</span
                                        >
                                        <input
                                            v-if="editMode"
                                            v-model="editedAsin.ParentAsin"
                                            class="related-asin-input"
                                            placeholder="Enter Parent ASIN"
                                        />
                                        <span
                                            v-else
                                            class="related-asin-value"
                                            >{{
                                                selectedAsin.ParentAsin || "-"
                                            }}</span
                                        >
                                    </div>
                                    <div class="related-asin-item">
                                        <span class="related-asin-label"
                                            >Cousin ASIN:</span
                                        >
                                        <input
                                            v-if="editMode"
                                            v-model="editedAsin.CousinASIN"
                                            class="related-asin-input"
                                            placeholder="Enter Cousin ASIN"
                                        />
                                        <span
                                            v-else
                                            class="related-asin-value"
                                            >{{
                                                selectedAsin.CousinASIN || "-"
                                            }}</span
                                        >
                                    </div>
                                    <div class="related-asin-item">
                                        <span class="related-asin-label"
                                            >Upgrade ASIN:</span
                                        >
                                        <input
                                            v-if="editMode"
                                            v-model="editedAsin.UpgradeASIN"
                                            class="related-asin-input"
                                            placeholder="Enter Upgrade ASIN"
                                        />
                                        <span
                                            v-else
                                            class="related-asin-value"
                                            >{{
                                                selectedAsin.UpgradeASIN || "-"
                                            }}</span
                                        >
                                    </div>
                                    <div class="related-asin-item">
                                        <span class="related-asin-label"
                                            >Grand ASIN:</span
                                        >
                                        <input
                                            v-if="editMode"
                                            v-model="editedAsin.GrandASIN"
                                            class="related-asin-input"
                                            placeholder="Enter Grand ASIN"
                                        />
                                        <span
                                            v-else
                                            class="related-asin-value"
                                            >{{
                                                selectedAsin.GrandASIN || "-"
                                            }}</span
                                        >
                                    </div>
                                </div>

                                <!-- Save button for related ASINs -->
                                <div
                                    v-if="editMode"
                                    class="related-asins-actions"
                                >
                                    <button
                                        class="btn-save-related"
                                        @click="saveRelatedAsins"
                                        :disabled="savingRelatedAsins"
                                    >
                                        <i class="fas fa-save"></i>
                                        {{
                                            savingRelatedAsins
                                                ? "Saving..."
                                                : "Save Related ASINs"
                                        }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h5 class="text-primary">FNSKU Details</h5>
                        <XDataTable
                            :value="selectedAsin.fnskus"
                            :columns="fnsku_columns"
                            :paginator="false"
                            scrollable
                            scrollHeight="600px"
                        >
                            <template #MSKU="{ data }">
                                <p>{{ data.MSKU || "-" }}</p>
                            </template>
                            <template #Units="{ data }">
                                <p class="text-primary">
                                    {{ data.Units || 0 }}
                                </p>
                            </template>
                            <template #grading="{ data }">
                                <p>{{ data.grading || "-" }}</p>
                            </template>
                            <template #timesused="{ data }">
                                <p
                                    :class="
                                        effectiveLimit(data) > 0 &&
                                        30 - data.Units >= effectiveLimit(data)
                                            ? 'text-danger'
                                            : 'text-primary'
                                    "
                                >
                                    {{
                                        `${30 - data.Units} / ${effectiveLimit(data) || 0}`
                                    }}
                                </p>
                            </template>

                            <template #fnskuLimit="{ data }">
                                <div class="d-flex align-items-center gap-1">
                                    <input
                                        v-if="editMode"
                                        type="number"
                                        v-model.number="data.fnsku_limit"
                                        min="0"
                                        max="100"
                                        style="width: 60px; font-size: 13px"
                                        class="form-control form-control-sm"
                                        @change="updateFnskuLimit(data)"
                                    />
                                    <span v-else class="text-primary fw-bold">
                                        {{ data.fnsku_limit ?? "-" }}
                                    </span>
                                    <i
                                        v-if="
                                            savingFnskuLimitFor === data.FNSKU
                                        "
                                        class="pi pi-spin pi-spinner"
                                        style="color: #007bff; font-size: 12px"
                                    ></i>
                                </div>
                            </template>
                        </XDataTable>
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="py-2">
                    <Button
                        :severity="editMode ? 'danger' : 'info'"
                        size="small"
                        @click="toggleEditMode"
                        :class="{ active: editMode }"
                        :label="editMode ? 'Cancel Edit' : 'Edit'"
                        :icon="editMode ? 'pi pi-times' : 'pi pi-pencil'"
                    />
                </div>
            </template>
        </Dialog>

        <div v-if="false" class="asin-details-modal">
            <div class="asin-details-content">
                <div class="asin-details-header">
                    <h2>ASIN Details</h2>
                    <div class="header-actions">
                        <button
                            class="btn-edit"
                            @click="toggleEditMode"
                            :class="{ active: editMode }"
                        >
                            <i class="fas fa-edit"></i>
                            {{ editMode ? "Cancel Edit" : "Edit" }}
                        </button>
                        <button
                            class="asin-details-close"
                            @click="closeAsinDetailsModal"
                        >
                            &times;
                        </button>
                    </div>
                </div>

                <div class="asin-details-body" v-if="selectedAsin">
                    <div class="asin-details-layout">
                        <!-- Left Column: Images and Basic Info -->
                        <div class="asin-details-left">
                            <!-- Images Section -->
                            <div class="images-section">
                                <!-- ASIN Images Container -->
                                <div class="image-container">
                                    <div
                                        class="asin-images-main clickable"
                                        @click="openAsinImageModal"
                                    >
                                        <img
                                            :src="
                                                getMainAsinImagePath(
                                                    selectedAsin.ASIN,
                                                )
                                            "
                                            :alt="`ASIN images for ${selectedAsin.ASIN}`"
                                            class="asin-images-main-thumbnail"
                                        />

                                        <!-- Small thumbnails overlay -->
                                        <div class="asin-images-thumbnails">
                                            <div
                                                class="small-thumb asin-thumb"
                                                :class="{
                                                    'has-image':
                                                        getImagePath(
                                                            selectedAsin.ASIN,
                                                        ) !== defaultImagePath,
                                                }"
                                            >
                                                <img
                                                    :src="
                                                        getImagePath(
                                                            selectedAsin.ASIN,
                                                        )
                                                    "
                                                    class="small-thumb-img"
                                                />
                                                <span class="thumb-label"
                                                    >IMG</span
                                                >
                                            </div>
                                            <div
                                                class="small-thumb vector-thumb"
                                                :class="{
                                                    'has-image': hasVectorImage(
                                                        selectedAsin.ASIN,
                                                    ),
                                                }"
                                            >
                                                <img
                                                    :src="
                                                        getMainVectorImagePath(
                                                            selectedAsin.ASIN,
                                                        )
                                                    "
                                                    class="small-thumb-img"
                                                />
                                                <span class="thumb-label"
                                                    >VEC</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                    <div class="image-label">ASIN Images</div>
                                </div>

                                <!-- Instruction Card Container -->
                                <div class="image-container">
                                    <div
                                        class="instruction-card-main clickable"
                                        @click="openInstructionCardModal"
                                    >
                                        <img
                                            :src="
                                                getMainInstructionCardPath(
                                                    selectedAsin.ASIN,
                                                )
                                            "
                                            :alt="`Instruction cards for ${selectedAsin.ASIN}`"
                                            class="instruction-card-main-thumbnail"
                                        />

                                        <!-- Small thumbnails overlay -->
                                        <div
                                            class="instruction-card-thumbnails"
                                        >
                                            <div
                                                class="small-thumb"
                                                :class="{
                                                    'has-image':
                                                        hasInstructionCard(
                                                            selectedAsin.ASIN,
                                                            1,
                                                        ),
                                                }"
                                            >
                                                <img
                                                    :src="
                                                        getInstructionCardPath(
                                                            selectedAsin.ASIN,
                                                            1,
                                                        )
                                                    "
                                                    class="small-thumb-img"
                                                />
                                                <span class="thumb-number"
                                                    >1</span
                                                >
                                            </div>
                                            <div
                                                class="small-thumb"
                                                :class="{
                                                    'has-image':
                                                        hasInstructionCard(
                                                            selectedAsin.ASIN,
                                                            2,
                                                        ),
                                                }"
                                            >
                                                <img
                                                    :src="
                                                        getInstructionCardPath(
                                                            selectedAsin.ASIN,
                                                            2,
                                                        )
                                                    "
                                                    class="small-thumb-img"
                                                />
                                                <span class="thumb-number"
                                                    >2</span
                                                >
                                            </div>
                                            <div
                                                class="small-thumb"
                                                :class="{
                                                    'has-image':
                                                        hasInstructionCard(
                                                            selectedAsin.ASIN,
                                                            3,
                                                        ),
                                                }"
                                            >
                                                <img
                                                    :src="
                                                        getInstructionCardPath(
                                                            selectedAsin.ASIN,
                                                            3,
                                                        )
                                                    "
                                                    class="small-thumb-img"
                                                />
                                                <span class="thumb-number"
                                                    >3</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                    <div class="image-label">
                                        Instruction Cards
                                    </div>
                                </div>

                                <!-- User Manual Container -->
                                <div class="image-container">
                                    <div
                                        class="user-manual-container"
                                        :class="{
                                            'has-manual': hasUserManual(
                                                selectedAsin.ASIN,
                                            ),
                                        }"
                                    >
                                        <div
                                            class="user-manual-icon"
                                            v-if="
                                                hasUserManual(selectedAsin.ASIN)
                                            "
                                        >
                                            <a
                                                :href="
                                                    getUserManualPath(
                                                        selectedAsin.ASIN,
                                                    )
                                                "
                                                target="_blank"
                                                class="user-manual-link"
                                            >
                                                <i class="fas fa-file-pdf"></i>
                                                <span>View Manual</span>
                                            </a>
                                        </div>
                                        <div
                                            class="user-manual-icon no-manual"
                                            v-else
                                        >
                                            <i class="fas fa-file-pdf"></i>
                                            <span>No Manual</span>
                                        </div>

                                        <!-- Upload section for edit mode -->
                                        <div
                                            v-if="editMode"
                                            class="user-manual-upload"
                                        >
                                            <input
                                                type="file"
                                                ref="userManualUpload"
                                                @change="handleUserManualUpload"
                                                accept="application/pdf"
                                                style="display: none"
                                            />
                                            <button
                                                class="btn-upload-manual"
                                                @click="
                                                    $refs.userManualUpload.click()
                                                "
                                                :disabled="userManualUploading"
                                            >
                                                <i class="fas fa-upload"></i>
                                                {{
                                                    userManualUploading
                                                        ? "Uploading..."
                                                        : "Upload PDF"
                                                }}
                                            </button>
                                        </div>
                                    </div>
                                    <div class="image-label">User Manual</div>
                                </div>
                            </div>

                            <div class="asin-details-info">
                                <h3 class="asin-details-title">
                                    {{ selectedAsin.AStitle }}
                                </h3>

                                <!-- Basic Information Section -->
                                <div class="details-section">
                                    <h4 class="section-title">
                                        Basic Information
                                    </h4>
                                    <div class="asin-details-row">
                                        <span class="asin-details-label"
                                            >ASIN:</span
                                        >
                                        <span class="asin-details-value">{{
                                            selectedAsin.ASIN
                                        }}</span>
                                    </div>
                                    <div class="asin-details-row">
                                        <span class="asin-details-label"
                                            >Meta Keyword:</span
                                        >
                                        <textarea
                                            v-if="editMode"
                                            v-model="editedAsin.metakeyword"
                                            class="details-textarea"
                                            placeholder="Enter meta keywords"
                                            rows="2"
                                        ></textarea>
                                        <span
                                            v-else
                                            class="asin-details-value"
                                            >{{
                                                selectedAsin.metakeyword || "-"
                                            }}</span
                                        >
                                    </div>
                                    <div class="asin-details-row">
                                        <span class="asin-details-label"
                                            >EAN:</span
                                        >
                                        <input
                                            v-if="editMode"
                                            v-model="editedAsin.EAN"
                                            class="details-input"
                                            placeholder="Enter EAN"
                                        />
                                        <span
                                            v-else
                                            class="asin-details-value"
                                            >{{ selectedAsin.EAN || "-" }}</span
                                        >
                                    </div>
                                    <div class="asin-details-row">
                                        <span class="asin-details-label"
                                            >UPC:</span
                                        >
                                        <input
                                            v-if="editMode"
                                            v-model="editedAsin.UPC"
                                            class="details-input"
                                            placeholder="Enter UPC"
                                        />
                                        <span
                                            v-else
                                            class="asin-details-value"
                                            >{{ selectedAsin.UPC || "-" }}</span
                                        >
                                    </div>
                                    <div class="asin-details-row">
                                        <span class="asin-details-label"
                                            >Instruction Link:</span
                                        >
                                        <input
                                            v-if="editMode"
                                            v-model="editedAsin.instructionlink"
                                            class="details-input instruction-link-input"
                                            placeholder="Enter instruction link URL"
                                            type="text"
                                        />
                                        <span v-else class="asin-details-value">
                                            <a
                                                v-if="
                                                    selectedAsin.instructionlink
                                                "
                                                :href="
                                                    selectedAsin.instructionlink
                                                "
                                                target="_blank"
                                                class="instruction-link"
                                            >
                                                <i
                                                    class="fas fa-external-link-alt"
                                                ></i>
                                                View Instructions
                                            </a>
                                            <span v-else>-</span>
                                        </span>
                                    </div>
                                    <div class="asin-details-row">
                                        <span class="asin-details-label"
                                            >Transparency QR:</span
                                        >
                                        <textarea
                                            v-if="editMode"
                                            v-model="
                                                editedAsin.TRANSPARENCY_QR_STATUS
                                            "
                                            class="details-textarea"
                                            placeholder="Enter transparency QR status"
                                            rows="3"
                                        ></textarea>
                                        <span
                                            v-else
                                            class="asin-details-value"
                                            >{{
                                                selectedAsin.TRANSPARENCY_QR_STATUS ||
                                                "-"
                                            }}</span
                                        >
                                    </div>
                                    <div class="asin-details-row">
                                        <span class="asin-details-label"
                                            >User Manual:</span
                                        >
                                        <span class="asin-details-value">
                                            <a
                                                v-if="
                                                    hasUserManual(
                                                        selectedAsin.ASIN,
                                                    )
                                                "
                                                :href="
                                                    getUserManualPath(
                                                        selectedAsin.ASIN,
                                                    )
                                                "
                                                target="_blank"
                                                class="user-manual-link-text"
                                            >
                                                <i class="fas fa-file-pdf"></i>
                                                View PDF Manual
                                            </a>
                                            <span v-else>-</span>
                                        </span>
                                    </div>
                                    <div class="asin-details-row">
                                        <span class="asin-details-label"
                                            >Total FNSKUs:</span
                                        >
                                        <span class="asin-details-value">{{
                                            selectedAsin.fnsku_count
                                        }}</span>
                                    </div>

                                    <!-- Save button for ASIN details -->
                                    <div
                                        v-if="editMode"
                                        class="asin-details-actions"
                                    >
                                        <button
                                            class="btn-save-asin-details"
                                            @click="saveAsinDetails"
                                            :disabled="savingAsinDetails"
                                        >
                                            <i class="fas fa-save"></i>
                                            {{
                                                savingAsinDetails
                                                    ? "Saving..."
                                                    : "Save Basic Details"
                                            }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Default Dimensions Section (Editable) -->
                                <div class="details-section default-dimensions">
                                    <h4 class="section-title">
                                        Default Dimensions (Editable)
                                    </h4>
                                    <div class="dimensions-grid">
                                        <div class="dimension-item">
                                            <div class="dimension-label">
                                                Def Length:
                                            </div>
                                            <div class="dimension-value">
                                                <input
                                                    v-if="editMode"
                                                    v-model="
                                                        editedAsin.def_length
                                                    "
                                                    class="dimension-input"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    placeholder="0.00"
                                                />
                                                <span v-else>{{
                                                    selectedAsin.white_length ||
                                                    "-"
                                                }}</span>
                                            </div>
                                        </div>
                                        <div class="dimension-item">
                                            <div class="dimension-label">
                                                Def Width:
                                            </div>
                                            <div class="dimension-value">
                                                <input
                                                    v-if="editMode"
                                                    v-model="
                                                        editedAsin.def_width
                                                    "
                                                    class="dimension-input"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    placeholder="0.00"
                                                />
                                                <span v-else>{{
                                                    selectedAsin.white_width ||
                                                    "-"
                                                }}</span>
                                            </div>
                                        </div>
                                        <div class="dimension-item">
                                            <div class="dimension-label">
                                                Def Height:
                                            </div>
                                            <div class="dimension-value">
                                                <input
                                                    v-if="editMode"
                                                    v-model="
                                                        editedAsin.def_height
                                                    "
                                                    class="dimension-input"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    placeholder="0.00"
                                                />
                                                <span v-else>{{
                                                    selectedAsin.white_height ||
                                                    "-"
                                                }}</span>
                                            </div>
                                        </div>
                                        <div class="dimension-item">
                                            <div class="dimension-label">
                                                Def Weight:
                                            </div>
                                            <div class="dimension-value">
                                                <div
                                                    v-if="editMode"
                                                    class="weight-input-group"
                                                >
                                                    <input
                                                        v-model="
                                                            editedAsin.def_weight
                                                        "
                                                        class="dimension-input weight-value"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        placeholder="0.00"
                                                    />
                                                    <select
                                                        v-model="
                                                            editedAsin.def_weight_unit
                                                        "
                                                        class="weight-unit-select"
                                                    >
                                                        <option value="">
                                                            Unit
                                                        </option>
                                                        <option value="kg">
                                                            kg
                                                        </option>
                                                        <option value="lbs">
                                                            lbs
                                                        </option>
                                                        <option value="g">
                                                            g
                                                        </option>
                                                        <option value="oz">
                                                            oz
                                                        </option>
                                                    </select>
                                                </div>
                                                <span v-else>
                                                    {{
                                                        selectedAsin.white_value
                                                            ? `${
                                                                  selectedAsin.white_value
                                                              } ${
                                                                  selectedAsin.white_unit ||
                                                                  ""
                                                              }`
                                                            : "-"
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Save button for default dimensions -->
                                    <div
                                        v-if="editMode"
                                        class="dimensions-actions"
                                    >
                                        <button
                                            class="btn-save-dimensions"
                                            @click="saveDefaultDimensions"
                                            :disabled="savingDefaultDimensions"
                                        >
                                            <i class="fas fa-save"></i>
                                            {{
                                                savingDefaultDimensions
                                                    ? "Saving..."
                                                    : "Save Dimensions"
                                            }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Stores Section -->
                                <div
                                    class="asin-details-stores-section"
                                    v-if="
                                        getUniqueStores(selectedAsin.fnskus)
                                            .length > 0
                                    "
                                >
                                    <h4>Stores</h4>
                                    <div class="stores-list">
                                        <div
                                            v-for="store in getUniqueStores(
                                                selectedAsin.fnskus,
                                            )"
                                            :key="store"
                                            class="store-item"
                                        >
                                            {{ store }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Related ASINs Section -->
                                <div class="asin-details-related-section">
                                    <h4>Related ASINs</h4>
                                    <div class="related-asins-details">
                                        <div class="related-asin-item">
                                            <span class="related-asin-label"
                                                >Parent ASIN:</span
                                            >
                                            <input
                                                v-if="editMode"
                                                v-model="editedAsin.ParentAsin"
                                                class="related-asin-input"
                                                placeholder="Enter Parent ASIN"
                                            />
                                            <span
                                                v-else
                                                class="related-asin-value"
                                                >{{
                                                    selectedAsin.ParentAsin ||
                                                    "-"
                                                }}</span
                                            >
                                        </div>
                                        <div class="related-asin-item">
                                            <span class="related-asin-label"
                                                >Cousin ASIN:</span
                                            >
                                            <input
                                                v-if="editMode"
                                                v-model="editedAsin.CousinASIN"
                                                class="related-asin-input"
                                                placeholder="Enter Cousin ASIN"
                                            />
                                            <span
                                                v-else
                                                class="related-asin-value"
                                                >{{
                                                    selectedAsin.CousinASIN ||
                                                    "-"
                                                }}</span
                                            >
                                        </div>
                                        <div class="related-asin-item">
                                            <span class="related-asin-label"
                                                >Upgrade ASIN:</span
                                            >
                                            <input
                                                v-if="editMode"
                                                v-model="editedAsin.UpgradeASIN"
                                                class="related-asin-input"
                                                placeholder="Enter Upgrade ASIN"
                                            />
                                            <span
                                                v-else
                                                class="related-asin-value"
                                                >{{
                                                    selectedAsin.UpgradeASIN ||
                                                    "-"
                                                }}</span
                                            >
                                        </div>
                                        <div class="related-asin-item">
                                            <span class="related-asin-label"
                                                >Grand ASIN:</span
                                            >
                                            <input
                                                v-if="editMode"
                                                v-model="editedAsin.GrandASIN"
                                                class="related-asin-input"
                                                placeholder="Enter Grand ASIN"
                                            />
                                            <span
                                                v-else
                                                class="related-asin-value"
                                                >{{
                                                    selectedAsin.GrandASIN ||
                                                    "-"
                                                }}</span
                                            >
                                        </div>
                                    </div>

                                    <!-- Save button for related ASINs -->
                                    <div
                                        v-if="editMode"
                                        class="related-asins-actions"
                                    >
                                        <button
                                            class="btn-save-related"
                                            @click="saveRelatedAsins"
                                            :disabled="savingRelatedAsins"
                                        >
                                            <i class="fas fa-save"></i>
                                            {{
                                                savingRelatedAsins
                                                    ? "Saving..."
                                                    : "Save Related ASINs"
                                            }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: FNSKU Details -->
                        <div class="asin-details-right">
                            <div class="asin-details-section fnsku-section">
                                <h4>FNSKU Details</h4>
                                <div class="asin-details-fnskus">
                                    <div class="responsive-table-container">
                                        <table class="asin-details-table">
                                            <thead>
                                                <tr>
                                                    <th>FNSKU</th>
                                                    <th>MSKU</th>
                                                    <th>Units</th>
                                                    <th>Grade</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="fnsku in selectedAsin.fnskus"
                                                    :key="fnsku.FNSKU"
                                                >
                                                    <td class="fnsku-code">
                                                        {{ fnsku.FNSKU }}
                                                    </td>
                                                    <td>
                                                        {{ fnsku.MSKU || "-" }}
                                                    </td>
                                                    <td class="units-cell">
                                                        {{ fnsku.Units || 0 }}
                                                    </td>
                                                    <td class="grade-cell">
                                                        {{
                                                            fnsku.grading || "-"
                                                        }}
                                                    </td>
                                                </tr>
                                                <tr
                                                    v-if="
                                                        !selectedAsin.fnskus ||
                                                        selectedAsin.fnskus
                                                            .length === 0
                                                    "
                                                >
                                                    <td
                                                        colspan="4"
                                                        class="text-center"
                                                    >
                                                        No FNSKUs found
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="asin-details-footer">
                    <button
                        class="btn-close-details"
                        @click="closeAsinDetailsModal"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- ASIN Image Management Modal -->
        <Dialog
            v-model:visible="showAsinImageModal"
            modal
            :header="`Manage ASIN Images - ${selectedAsin?.ASIN || ''}`"
        >
            <div class="image-management-layout">
                <!-- ASIN Image -->
                <div class="image-slot">
                    <div class="image-slot-header">
                        <h4>Main ASIN Image</h4>
                    </div>
                    <div class="image-slot-image">
                        <img
                            :src="getImagePath(selectedAsin?.ASIN)"
                            :alt="`ASIN image for ${selectedAsin?.ASIN}`"
                            class="image-slot-thumbnail"
                            @error="handleImageError($event, null)"
                        />
                    </div>
                    <div class="image-slot-actions">
                        <input
                            type="file"
                            ref="asinImageUpload"
                            @change="handleAsinImageUpload"
                            accept="image/*"
                            style="display: none"
                        />
                        <button
                            class="btn-upload-image"
                            @click="$refs.asinImageUpload.click()"
                            :disabled="asinImageUploading"
                        >
                            <i class="fas fa-upload"></i>
                            {{
                                asinImageUploading
                                    ? "Uploading..."
                                    : "Upload/Update"
                            }}
                        </button>
                    </div>
                </div>

                <div class="image-slot">
                    <div class="image-slot-header">
                        <h4>Vector Image</h4>
                    </div>
                    <div class="image-slot-image">
                        <img
                            :src="
                                hasVectorImage(selectedAsin?.ASIN)
                                    ? getVectorImagePath(selectedAsin?.ASIN)
                                    : createDefaultVectorSVG()
                            "
                            :alt="`Vector image for ${selectedAsin?.ASIN}`"
                            class="image-slot-thumbnail"
                            @error="handleImageError($event, null)"
                        />
                    </div>
                    <div class="image-slot-actions">
                        <input
                            type="file"
                            ref="vectorImageUpload"
                            @change="handleVectorImageUpload"
                            accept="image/png,image/jpg,image/jpeg"
                            style="display: none"
                        />
                        <button
                            class="btn-upload-vector"
                            @click="$refs.vectorImageUpload.click()"
                            :disabled="vectorImageUploading"
                        >
                            <i class="fas fa-upload"></i>
                            {{
                                vectorImageUploading
                                    ? "Uploading..."
                                    : "Upload/Update"
                            }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- <div class="asin-image-modal-footer">
                <button class="btn-close-modal" @click="closeAsinImageModal">
                    Close
                </button>
            </div> -->
        </Dialog>

        <!-- Instruction Card Management Modal -->
        <Dialog
            v-model:visible="showInstructionCardModal"
            modal
            :header="`Manage Instruction Cards - ${selectedAsin?.ASIN}`"
        >
            <div class="card-management-layout">
                <!-- Card 1 -->
                <div class="card-slot">
                    <div class="card-slot-header">
                        <h4>Instruction Card 1</h4>
                    </div>
                    <div class="card-slot-image">
                        <img
                            :src="getInstructionCardPath(selectedAsin?.ASIN, 1)"
                            :alt="`Instruction card 1 for ${selectedAsin?.ASIN}`"
                            class="card-slot-thumbnail"
                            @error="handleInstructionCardError($event, 1)"
                        />
                    </div>
                    <div class="card-slot-actions">
                        <input
                            type="file"
                            :ref="`cardUpload1`"
                            @change="(e) => handleInstructionCardUpload(e, 1)"
                            accept="image/*"
                            style="display: none"
                        />
                        <Button
                            class="btn-upload-card"
                            @click="$refs.cardUpload1.click()"
                            :disabled="instructionCardUploading === 1"
                            :label="
                                instructionCardUploading === 1
                                    ? 'Uploading...'
                                    : 'Upload/Update'
                            "
                            size="small"
                            icon="pi pi-upload"
                        />
                    </div>
                </div>

                <!-- Card 2 -->
                <div class="card-slot">
                    <div class="card-slot-header">
                        <h4>Instruction Card 2</h4>
                    </div>
                    <div class="card-slot-image">
                        <img
                            :src="getInstructionCardPath(selectedAsin?.ASIN, 2)"
                            :alt="`Instruction card 2 for ${selectedAsin?.ASIN}`"
                            class="card-slot-thumbnail"
                            @error="handleInstructionCardError($event, 2)"
                        />
                    </div>
                    <div class="card-slot-actions">
                        <input
                            type="file"
                            :ref="`cardUpload2`"
                            @change="(e) => handleInstructionCardUpload(e, 2)"
                            accept="image/*"
                            style="display: none"
                        />
                        <Button
                            class="btn-upload-card"
                            @click="$refs.cardUpload2.click()"
                            :disabled="instructionCardUploading === 2"
                            :label="
                                instructionCardUploading === 2
                                    ? 'Uploading...'
                                    : 'Upload/Update'
                            "
                            size="small"
                            icon="pi pi-upload"
                        />
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="card-slot">
                    <div class="card-slot-header">
                        <h4>Instruction Card 3</h4>
                    </div>
                    <div class="card-slot-image">
                        <img
                            :src="getInstructionCardPath(selectedAsin?.ASIN, 3)"
                            :alt="`Instruction card 3 for ${selectedAsin?.ASIN}`"
                            class="card-slot-thumbnail"
                            @error="handleInstructionCardError($event, 3)"
                        />
                    </div>
                    <div class="card-slot-actions">
                        <input
                            type="file"
                            :ref="`cardUpload3`"
                            @change="(e) => handleInstructionCardUpload(e, 3)"
                            accept="image/*"
                            style="display: none"
                        />
                        <Button
                            class="btn-upload-card"
                            @click="$refs.cardUpload3.click()"
                            :disabled="instructionCardUploading === 3"
                            :label="
                                instructionCardUploading === 3
                                    ? 'Uploading...'
                                    : 'Upload/Update'
                            "
                            size="small"
                            icon="pi pi-upload"
                        />
                    </div>
                </div>
            </div>
        </Dialog>

        <div v-if="false" class="instruction-card-modal">
            <div class="instruction-card-modal-content">
                <div class="instruction-card-modal-header">
                    <h3>Manage Instruction Cards - {{ selectedAsin?.ASIN }}</h3>
                    <button
                        class="modal-close"
                        @click="closeInstructionCardModal"
                    >
                        &times;
                    </button>
                </div>

                <div class="instruction-card-modal-body">
                    <div class="card-management-layout">
                        <!-- Card 1 -->
                        <div class="card-slot">
                            <div class="card-slot-header">
                                <h4>Instruction Card 1</h4>
                            </div>
                            <div class="card-slot-image">
                                <img
                                    :src="
                                        getInstructionCardPath(
                                            selectedAsin?.ASIN,
                                            1,
                                        )
                                    "
                                    :alt="`Instruction card 1 for ${selectedAsin?.ASIN}`"
                                    class="card-slot-thumbnail"
                                    @error="
                                        handleInstructionCardError($event, 1)
                                    "
                                />
                            </div>
                            <div class="card-slot-actions">
                                <input
                                    type="file"
                                    :ref="`cardUpload1`"
                                    @change="
                                        (e) => handleInstructionCardUpload(e, 1)
                                    "
                                    accept="image/*"
                                    style="display: none"
                                />
                                <button
                                    class="btn-upload-card"
                                    @click="$refs.cardUpload1.click()"
                                    :disabled="instructionCardUploading === 1"
                                    :label="
                                        instructionCardUploading === 1
                                            ? 'Uploading...'
                                            : 'Upload/Update'
                                    "
                                    icon="pi pi-upload"
                                />
                            </div>
                        </div>

                        <!-- Card 2 -->
                        <div class="card-slot">
                            <div class="card-slot-header">
                                <h4>Instruction Card 2</h4>
                            </div>
                            <div class="card-slot-image">
                                <img
                                    :src="
                                        getInstructionCardPath(
                                            selectedAsin?.ASIN,
                                            2,
                                        )
                                    "
                                    :alt="`Instruction card 2 for ${selectedAsin?.ASIN}`"
                                    class="card-slot-thumbnail"
                                    @error="
                                        handleInstructionCardError($event, 2)
                                    "
                                />
                            </div>
                            <div class="card-slot-actions">
                                <input
                                    type="file"
                                    :ref="`cardUpload2`"
                                    @change="
                                        (e) => handleInstructionCardUpload(e, 2)
                                    "
                                    accept="image/*"
                                    style="display: none"
                                />
                                <button
                                    class="btn-upload-card"
                                    @click="$refs.cardUpload2.click()"
                                    :disabled="instructionCardUploading === 2"
                                >
                                    <i class="fas fa-upload"></i>
                                    {{
                                        instructionCardUploading === 2
                                            ? "Uploading..."
                                            : "Upload/Update"
                                    }}
                                </button>
                            </div>
                        </div>

                        <!-- Card 3 -->
                        <div class="card-slot">
                            <div class="card-slot-header">
                                <h4>Instruction Card 3</h4>
                            </div>
                            <div class="card-slot-image">
                                <img
                                    :src="
                                        getInstructionCardPath(
                                            selectedAsin?.ASIN,
                                            3,
                                        )
                                    "
                                    :alt="`Instruction card 3 for ${selectedAsin?.ASIN}`"
                                    class="card-slot-thumbnail"
                                    @error="
                                        handleInstructionCardError($event, 3)
                                    "
                                />
                            </div>
                            <div class="card-slot-actions">
                                <input
                                    type="file"
                                    :ref="`cardUpload3`"
                                    @change="
                                        (e) => handleInstructionCardUpload(e, 3)
                                    "
                                    accept="image/*"
                                    style="display: none"
                                />
                                <button
                                    class="btn-upload-card"
                                    @click="$refs.cardUpload3.click()"
                                    :disabled="instructionCardUploading === 3"
                                >
                                    <i class="fas fa-upload"></i>
                                    {{
                                        instructionCardUploading === 3
                                            ? "Uploading..."
                                            : "Upload/Update"
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="instruction-card-modal-footer">
                    <button
                        class="btn-close-modal"
                        @click="closeInstructionCardModal"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- Bulk Instruction Card Upload Modal -->
        <div
            v-if="showBulkInstructionCardModal"
            class="bulk-instruction-card-modal"
        >
            <div class="bulk-instruction-card-modal-content">
                <div class="bulk-instruction-card-modal-header">
                    <h3>
                        <i class="fas fa-upload"></i> Bulk Instruction Card
                        Upload
                    </h3>
                    <button
                        class="modal-close"
                        @click="closeBulkInstructionCardModal"
                    >
                        &times;
                    </button>
                </div>

                <div class="bulk-instruction-card-modal-body">
                    <div class="bulk-upload-form">
                        <!-- ASIN List Input -->
                        <div class="form-group">
                            <label for="bulk-asin-list">
                                <i class="fas fa-list"></i> ASIN List (comma
                                separated):
                            </label>
                            <textarea
                                id="bulk-asin-list"
                                v-model="bulkUploadData.asinList"
                                class="bulk-asin-textarea"
                                placeholder="Enter ASINs separated by commas, e.g.: B07XYZ123, B08ABC456, B09DEF789"
                                rows="4"
                                :disabled="bulkUploadData.uploading"
                            ></textarea>
                            <div class="asin-count-info">
                                ASINs to process: {{ parseAsinList().length }}
                                <span
                                    v-if="parseAsinList().length > 50"
                                    class="error-text"
                                >
                                    (Maximum 50 ASINs allowed)
                                </span>
                            </div>
                        </div>

                        <!-- File Upload Cards with Preview -->
                        <div class="form-group">
                            <label>
                                <i class="fas fa-file-image"></i> Select
                                Instruction Card Images:
                            </label>

                            <div class="bulk-cards-grid">
                                <!-- Card 1 Upload -->
                                <div class="bulk-card-upload-slot">
                                    <div class="bulk-card-header">
                                        <h4>Instruction Card 1</h4>
                                        <span class="optional-badge"
                                            >Optional</span
                                        >
                                    </div>

                                    <div
                                        class="bulk-card-preview"
                                        :class="{
                                            'has-image':
                                                bulkUploadData.files.card1,
                                        }"
                                    >
                                        <img
                                            v-if="bulkUploadData.files.card1"
                                            :src="
                                                getFilePreviewUrl(
                                                    bulkUploadData.files.card1,
                                                )
                                            "
                                            alt="Card 1 Preview"
                                            class="bulk-card-preview-image"
                                        />
                                        <div
                                            v-else
                                            class="bulk-card-placeholder"
                                        >
                                            <i class="fas fa-image"></i>
                                            <span>No image selected</span>
                                        </div>
                                    </div>

                                    <div class="bulk-card-actions">
                                        <input
                                            type="file"
                                            ref="bulkFileUploadCard1"
                                            @change="
                                                (e) =>
                                                    handleBulkFileSelect(
                                                        e,
                                                        'card1',
                                                    )
                                            "
                                            accept="image/*"
                                            class="bulk-file-input-hidden"
                                            :disabled="bulkUploadData.uploading"
                                        />
                                        <button
                                            class="btn-select-file"
                                            @click="
                                                $refs.bulkFileUploadCard1.click()
                                            "
                                            :disabled="bulkUploadData.uploading"
                                        >
                                            <i class="fas fa-folder-open"></i>
                                            {{
                                                bulkUploadData.files.card1
                                                    ? "Change"
                                                    : "Select"
                                            }}
                                        </button>
                                        <button
                                            v-if="bulkUploadData.files.card1"
                                            class="btn-remove-file"
                                            @click="removeBulkFile('card1')"
                                            :disabled="bulkUploadData.uploading"
                                        >
                                            <i class="fas fa-times"></i>
                                            Remove
                                        </button>
                                    </div>

                                    <div
                                        v-if="bulkUploadData.files.card1"
                                        class="file-info"
                                    >
                                        <div class="file-name">
                                            {{
                                                bulkUploadData.files.card1.name
                                            }}
                                        </div>
                                        <div class="file-size">
                                            {{
                                                formatFileSize(
                                                    bulkUploadData.files.card1
                                                        .size,
                                                )
                                            }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Card 2 Upload -->
                                <div class="bulk-card-upload-slot">
                                    <div class="bulk-card-header">
                                        <h4>Instruction Card 2</h4>
                                        <span class="optional-badge"
                                            >Optional</span
                                        >
                                    </div>

                                    <div
                                        class="bulk-card-preview"
                                        :class="{
                                            'has-image':
                                                bulkUploadData.files.card2,
                                        }"
                                    >
                                        <img
                                            v-if="bulkUploadData.files.card2"
                                            :src="
                                                getFilePreviewUrl(
                                                    bulkUploadData.files.card2,
                                                )
                                            "
                                            alt="Card 2 Preview"
                                            class="bulk-card-preview-image"
                                        />
                                        <div
                                            v-else
                                            class="bulk-card-placeholder"
                                        >
                                            <i class="fas fa-image"></i>
                                            <span>No image selected</span>
                                        </div>
                                    </div>

                                    <div class="bulk-card-actions">
                                        <input
                                            type="file"
                                            ref="bulkFileUploadCard2"
                                            @change="
                                                (e) =>
                                                    handleBulkFileSelect(
                                                        e,
                                                        'card2',
                                                    )
                                            "
                                            accept="image/*"
                                            class="bulk-file-input-hidden"
                                            :disabled="bulkUploadData.uploading"
                                        />
                                        <button
                                            class="btn-select-file"
                                            @click="
                                                $refs.bulkFileUploadCard2.click()
                                            "
                                            :disabled="bulkUploadData.uploading"
                                        >
                                            <i class="fas fa-folder-open"></i>
                                            {{
                                                bulkUploadData.files.card2
                                                    ? "Change"
                                                    : "Select"
                                            }}
                                        </button>
                                        <button
                                            v-if="bulkUploadData.files.card2"
                                            class="btn-remove-file"
                                            @click="removeBulkFile('card2')"
                                            :disabled="bulkUploadData.uploading"
                                        >
                                            <i class="fas fa-times"></i>
                                            Remove
                                        </button>
                                    </div>

                                    <div
                                        v-if="bulkUploadData.files.card2"
                                        class="file-info"
                                    >
                                        <div class="file-name">
                                            {{
                                                bulkUploadData.files.card2.name
                                            }}
                                        </div>
                                        <div class="file-size">
                                            {{
                                                formatFileSize(
                                                    bulkUploadData.files.card2
                                                        .size,
                                                )
                                            }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Card 3 Upload -->
                                <div class="bulk-card-upload-slot">
                                    <div class="bulk-card-header">
                                        <h4>Instruction Card 3</h4>
                                        <span class="optional-badge"
                                            >Optional</span
                                        >
                                    </div>

                                    <div
                                        class="bulk-card-preview"
                                        :class="{
                                            'has-image':
                                                bulkUploadData.files.card3,
                                        }"
                                    >
                                        <img
                                            v-if="bulkUploadData.files.card3"
                                            :src="
                                                getFilePreviewUrl(
                                                    bulkUploadData.files.card3,
                                                )
                                            "
                                            alt="Card 3 Preview"
                                            class="bulk-card-preview-image"
                                        />
                                        <div
                                            v-else
                                            class="bulk-card-placeholder"
                                        >
                                            <i class="fas fa-image"></i>
                                            <span>No image selected</span>
                                        </div>
                                    </div>

                                    <div class="bulk-card-actions">
                                        <input
                                            type="file"
                                            ref="bulkFileUploadCard3"
                                            @change="
                                                (e) =>
                                                    handleBulkFileSelect(
                                                        e,
                                                        'card3',
                                                    )
                                            "
                                            accept="image/*"
                                            class="bulk-file-input-hidden"
                                            :disabled="bulkUploadData.uploading"
                                        />
                                        <button
                                            class="btn-select-file"
                                            @click="
                                                $refs.bulkFileUploadCard3.click()
                                            "
                                            :disabled="bulkUploadData.uploading"
                                        >
                                            <i class="fas fa-folder-open"></i>
                                            {{
                                                bulkUploadData.files.card3
                                                    ? "Change"
                                                    : "Select"
                                            }}
                                        </button>
                                        <button
                                            v-if="bulkUploadData.files.card3"
                                            class="btn-remove-file"
                                            @click="removeBulkFile('card3')"
                                            :disabled="bulkUploadData.uploading"
                                        >
                                            <i class="fas fa-times"></i>
                                            Remove
                                        </button>
                                    </div>

                                    <div
                                        v-if="bulkUploadData.files.card3"
                                        class="file-info"
                                    >
                                        <div class="file-name">
                                            {{
                                                bulkUploadData.files.card3.name
                                            }}
                                        </div>
                                        <div class="file-size">
                                            {{
                                                formatFileSize(
                                                    bulkUploadData.files.card3
                                                        .size,
                                                )
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="cards-selected-summary">
                                <i class="fas fa-info-circle"></i>
                                <strong>{{ getSelectedCardCount() }}</strong>
                                card{{
                                    getSelectedCardCount() !== 1 ? "s" : ""
                                }}
                                selected
                                <span
                                    v-if="getSelectedCardCount() === 0"
                                    class="error-text"
                                >
                                    - Please select at least one card
                                </span>
                            </div>
                        </div>

                        <!-- Upload Instructions -->
                        <div class="bulk-upload-instructions">
                            <h4>
                                <i class="fas fa-info-circle"></i> Instructions:
                            </h4>
                            <ul>
                                <li>
                                    Enter ASINs separated by commas (maximum 50
                                    ASINs per bulk upload)
                                </li>
                                <li>
                                    Select 1, 2, or all 3 instruction card
                                    images
                                </li>
                                <li>
                                    Each image file must be less than 5MB (JPG,
                                    PNG, GIF supported)
                                </li>
                                <li>
                                    Selected images will be uploaded to ALL
                                    ASINs in the list
                                </li>
                                <li>Non-existent ASINs will be skipped</li>
                                <li>
                                    Existing instruction cards will be replaced
                                </li>
                            </ul>
                        </div>

                        <!-- Upload Progress -->
                        <div
                            v-if="bulkUploadData.uploading"
                            class="bulk-upload-progress"
                        >
                            <div class="progress-spinner">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                            <div class="progress-text">
                                Uploading instruction cards...
                            </div>
                            <div class="progress-details">
                                Processing {{ parseAsinList().length }} ASINs
                                with {{ getSelectedCardCount() }} card{{
                                    getSelectedCardCount() !== 1 ? "s" : ""
                                }}
                            </div>
                        </div>

                        <!-- Upload Results Summary -->
                        <div
                            v-if="
                                !bulkUploadData.uploading &&
                                (bulkUploadData.uploadResults.success.length >
                                    0 ||
                                    bulkUploadData.uploadResults.failed.length >
                                        0 ||
                                    bulkUploadData.uploadResults.skipped
                                        .length > 0)
                            "
                            class="bulk-upload-results"
                        >
                            <h4>
                                <i class="fas fa-chart-bar"></i> Upload Results:
                            </h4>

                            <div class="results-summary">
                                <div
                                    class="result-item success"
                                    v-if="
                                        bulkUploadData.uploadResults.success
                                            .length > 0
                                    "
                                >
                                    <i class="fas fa-check-circle"></i>
                                    <span
                                        >{{
                                            bulkUploadData.uploadResults.success
                                                .length
                                        }}
                                        successful</span
                                    >
                                </div>

                                <div
                                    class="result-item failed"
                                    v-if="
                                        bulkUploadData.uploadResults.failed
                                            .length > 0
                                    "
                                >
                                    <i class="fas fa-times-circle"></i>
                                    <span
                                        >{{
                                            bulkUploadData.uploadResults.failed
                                                .length
                                        }}
                                        failed</span
                                    >
                                </div>

                                <div
                                    class="result-item skipped"
                                    v-if="
                                        bulkUploadData.uploadResults.skipped
                                            .length > 0
                                    "
                                >
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span
                                        >{{
                                            bulkUploadData.uploadResults.skipped
                                                .length
                                        }}
                                        skipped</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bulk-instruction-card-modal-footer">
                    <button
                        class="btn-cancel-bulk"
                        @click="closeBulkInstructionCardModal"
                        :disabled="bulkUploadData.uploading"
                    >
                        {{
                            bulkUploadData.uploading ? "Uploading..." : "Cancel"
                        }}
                    </button>
                    <button
                        class="btn-upload-bulk"
                        @click="processBulkInstructionCardUpload"
                        :disabled="
                            bulkUploadData.uploading ||
                            !bulkUploadData.asinList.trim() ||
                            getSelectedCardCount() === 0 ||
                            parseAsinList().length > 50
                        "
                    >
                        <i class="fas fa-upload"></i>
                        {{
                            bulkUploadData.uploading
                                ? "Uploading..."
                                : `Upload ${getSelectedCardCount()}
                        Card${getSelectedCardCount() > 1 ? "s" : ""} to ${parseAsinList().length}
                        ASIN${parseAsinList().length > 1 ? "s" : ""}`
                        }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ASIN Configuration -->
        <Dialog
            v-model:visible="showASINConfig"
            modal
            :header="`Configure ASIN: ${selectedConfig.ASIN} — ${selectedConfig.system_title || selectedConfig.AStitle}`"
            :style="{ width: '1260px', maxWidth: '98vw' }"
        >
            <div class="d-flex flex-column gap-3">
                <!-- ── LABELING MODULE ───────────────────────────────────────── -->
                <div
                    class="mb-0 px-2 py-2 rounded d-flex align-items-center justify-content-between"
                    style="
                        background: #f3effe;
                        border-left: 4px solid #6f42c1;
                        cursor: pointer;
                    "
                    @click="labelingCollapsed = !labelingCollapsed"
                >
                    <div class="d-flex align-items-center gap-2">
                        <i class="pi pi-tag" style="color: #6f42c1"></i>
                        <strong style="color: #6f42c1">Labeling Module</strong>
                        <span class="text-muted" style="font-size: 12px">
                            ({{ labelingFields.length }} field{{
                                labelingFields.length !== 1 ? "s" : ""
                            }}
                            <template
                                v-if="labelingFields.some((f) => f._fromGlobal)"
                            >
                                —
                                {{
                                    labelingFields.filter((f) => f._fromGlobal)
                                        .length
                                }}
                                inherited </template
                            >)
                        </span>
                    </div>
                    <i
                        :class="
                            labelingCollapsed
                                ? 'pi pi-chevron-down'
                                : 'pi pi-chevron-up'
                        "
                        style="color: #6f42c1"
                    ></i>
                </div>

                <div v-if="!labelingCollapsed" class="border rounded p-3">
                    <div class="d-flex flex-column gap-3">
                        <!-- Inherited global labeling fields (editable, marked with globe badge) -->
                        <template v-if="globalLabelingFields.length">
                            <div class="gc-module-inherited-header">
                                <i class="pi pi-globe"></i>
                                <span
                                    >Fields from Global Config — editable per
                                    ASIN</span
                                >
                            </div>
                        </template>

                        <!-- ALL labeling fields (global-origin ones shown first, flagged) -->
                        <div
                            v-for="(field, index) in labelingFields"
                            :key="'lab-' + index"
                            class="p-3 border rounded"
                            :class="{
                                'gc-from-global-field': field._fromGlobal,
                            }"
                        >
                            <div
                                class="d-flex justify-content-between align-items-center mb-2"
                            >
                                <div class="d-flex align-items-center gap-2">
                                    <i
                                        class="pi pi-bars"
                                        style="cursor: grab; color: #aaa"
                                    />
                                    <InputText
                                        v-model="field.label"
                                        placeholder="Field label"
                                        size="small"
                                        style="
                                            font-weight: 600;
                                            border: none;
                                            border-bottom: 1px solid #ccc;
                                            background: transparent;
                                            padding: 2px 4px;
                                        "
                                    />
                                    <span
                                        v-if="field._fromGlobal"
                                        class="gc-from-global-badge"
                                    >
                                        <i
                                            class="pi pi-globe"
                                            style="font-size: 10px"
                                        ></i>
                                        Global
                                    </span>
                                </div>
                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    text
                                    size="small"
                                    @click="labelingFields.splice(index, 1)"
                                />
                            </div>
                            <div
                                class="d-flex align-items-center gap-3 flex-wrap mb-2"
                            >
                                <Select
                                    v-model="field.type"
                                    :options="fieldTypeOptions"
                                    placeholder="Field type"
                                    size="small"
                                    style="min-width: 160px"
                                    @change="onFieldTypeChange(field)"
                                />
                                <InputText
                                    v-model="field.defaultValue"
                                    placeholder="Default value"
                                    size="small"
                                    style="min-width: 140px"
                                />
                                <label
                                    class="d-flex align-items-center gap-1"
                                    style="font-size: 13px; cursor: pointer"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="field.required"
                                    />
                                    Required
                                </label>
                                <label
                                    class="d-flex align-items-center gap-1"
                                    style="font-size: 13px; cursor: pointer"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="field.preTypedNotes"
                                    />
                                    Pre-typed Notes
                                </label>
                                <label
                                    v-if="field.type === 'Dropdown/Select'"
                                    class="d-flex align-items-center gap-1"
                                    style="font-size: 13px; cursor: pointer"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="field.hasOptions"
                                    />
                                    Has Options
                                </label>
                            </div>
                            <div
                                v-if="
                                    field.hasOptions &&
                                    field.type === 'Dropdown/Select'
                                "
                                class="ms-3 mt-2"
                            >
                                <div
                                    v-for="(opt, oi) in field.options"
                                    :key="'lopt-' + oi"
                                    class="d-flex align-items-center gap-2 mb-1"
                                >
                                    <InputText
                                        v-model="opt.value"
                                        placeholder="Option value"
                                        size="small"
                                        style="flex: 1"
                                    />
                                    <label
                                        v-if="field.preTypedNotes"
                                        class="d-flex align-items-center gap-1"
                                        style="font-size: 12px; cursor: pointer"
                                    >
                                        <input
                                            type="checkbox"
                                            v-model="opt.hasNote"
                                            @change="toggleHasNote(field, oi)"
                                        />
                                        Has Note
                                    </label>
                                    <Button
                                        icon="pi pi-trash"
                                        severity="danger"
                                        text
                                        size="small"
                                        @click="removeOption(field, oi)"
                                    />
                                </div>
                                <Button
                                    label="Add Option"
                                    icon="pi pi-plus"
                                    size="small"
                                    text
                                    @click="addOption(field)"
                                />
                            </div>
                            <div
                                v-if="
                                    field.preTypedNotes &&
                                    field.options.some((o) => o.hasNote)
                                "
                                class="mt-2 p-2 rounded"
                                style="
                                    background: #f9f9f9;
                                    border: 1px dashed #ccc;
                                "
                            >
                                <strong style="font-size: 12px; color: #555"
                                    >Pre-typed Notes Configuration</strong
                                >
                                <div
                                    v-for="(opt, oi) in field.options.filter(
                                        (o) => o.hasNote,
                                    )"
                                    :key="'pnote-' + oi"
                                    class="d-flex align-items-center gap-2 mt-1"
                                >
                                    <span
                                        style="
                                            font-size: 12px;
                                            min-width: 100px;
                                        "
                                        >{{ opt.value || "(empty)" }}:</span
                                    >
                                    <InputText
                                        v-model="opt.note"
                                        placeholder="Pre-typed note text"
                                        size="small"
                                        style="flex: 1"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <Button
                        label="Add Labeling Field"
                        icon="pi pi-plus"
                        size="small"
                        text
                        class="mt-2"
                        @click="addLabelingField"
                    />
                </div>

                <!-- ── TESTING MODULE ────────────────────────────────────────── -->
                <div
                    class="mb-0 px-2 py-2 rounded d-flex align-items-center justify-content-between"
                    style="
                        background: #e8f4fd;
                        border-left: 4px solid #0d6efd;
                        cursor: pointer;
                    "
                    @click="testingCollapsed = !testingCollapsed"
                >
                    <div class="d-flex align-items-center gap-2">
                        <i
                            class="pi pi-check-circle"
                            style="color: #0d6efd"
                        ></i>
                        <strong style="color: #0d6efd">Testing Module</strong>
                        <span class="text-muted" style="font-size: 12px">
                            ({{ testingFields.length }} field{{
                                testingFields.length !== 1 ? "s" : ""
                            }}
                            <template
                                v-if="testingFields.some((f) => f._fromGlobal)"
                            >
                                —
                                {{
                                    testingFields.filter((f) => f._fromGlobal)
                                        .length
                                }}
                                inherited </template
                            >)
                        </span>
                    </div>
                    <i
                        :class="
                            testingCollapsed
                                ? 'pi pi-chevron-down'
                                : 'pi pi-chevron-up'
                        "
                        style="color: #0d6efd"
                    ></i>
                </div>

                <div v-if="!testingCollapsed" class="border rounded p-3">
                    <div class="d-flex flex-column gap-3">
                        <!-- Inherited global testing fields (editable, marked with globe badge) -->
                        <template v-if="globalTestingFields.length">
                            <div class="gc-module-inherited-header">
                                <i class="pi pi-globe"></i>
                                <span
                                    >Fields from Global Config — editable per
                                    ASIN</span
                                >
                            </div>
                        </template>

                        <!-- ALL testing fields -->
                        <div
                            v-for="(field, index) in testingFields"
                            :key="'test-' + index"
                            class="p-3 border rounded"
                            :class="{
                                'gc-from-global-field': field._fromGlobal,
                            }"
                        >
                            <div
                                class="d-flex justify-content-between align-items-center mb-2"
                            >
                                <div class="d-flex align-items-center gap-2">
                                    <i
                                        class="pi pi-bars"
                                        style="cursor: grab; color: #aaa"
                                    />
                                    <InputText
                                        v-model="field.label"
                                        placeholder="Field label"
                                        size="small"
                                        style="
                                            font-weight: 600;
                                            border: none;
                                            border-bottom: 1px solid #ccc;
                                            background: transparent;
                                            padding: 2px 4px;
                                        "
                                    />
                                    <span
                                        v-if="field._fromGlobal"
                                        class="gc-from-global-badge"
                                    >
                                        <i
                                            class="pi pi-globe"
                                            style="font-size: 10px"
                                        ></i>
                                        Global
                                    </span>
                                </div>
                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    text
                                    size="small"
                                    @click="testingFields.splice(index, 1)"
                                />
                            </div>
                            <div
                                class="d-flex align-items-center gap-3 flex-wrap mb-2"
                            >
                                <Select
                                    v-model="field.type"
                                    :options="fieldTypeOptions"
                                    placeholder="Field type"
                                    size="small"
                                    style="min-width: 160px"
                                    @change="onFieldTypeChange(field)"
                                />
                                <InputText
                                    v-model="field.defaultValue"
                                    placeholder="Default value"
                                    size="small"
                                    style="min-width: 140px"
                                />
                                <label
                                    class="d-flex align-items-center gap-1"
                                    style="font-size: 13px; cursor: pointer"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="field.required"
                                    />
                                    Required
                                </label>
                                <label
                                    class="d-flex align-items-center gap-1"
                                    style="font-size: 13px; cursor: pointer"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="field.preTypedNotes"
                                    />
                                    Pre-typed Notes
                                </label>
                                <label
                                    v-if="field.type === 'Dropdown/Select'"
                                    class="d-flex align-items-center gap-1"
                                    style="font-size: 13px; cursor: pointer"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="field.hasOptions"
                                    />
                                    Has Options
                                </label>
                            </div>
                            <div
                                v-if="
                                    field.hasOptions &&
                                    field.type === 'Dropdown/Select'
                                "
                                class="ms-3 mt-2"
                            >
                                <div
                                    v-for="(opt, oi) in field.options"
                                    :key="'topt-' + oi"
                                    class="d-flex align-items-center gap-2 mb-1"
                                >
                                    <InputText
                                        v-model="opt.value"
                                        placeholder="Option value"
                                        size="small"
                                        style="flex: 1"
                                    />
                                    <label
                                        v-if="field.preTypedNotes"
                                        class="d-flex align-items-center gap-1"
                                        style="font-size: 12px; cursor: pointer"
                                    >
                                        <input
                                            type="checkbox"
                                            v-model="opt.hasNote"
                                            @change="toggleHasNote(field, oi)"
                                        />
                                        Has Note
                                    </label>
                                    <Button
                                        icon="pi pi-trash"
                                        severity="danger"
                                        text
                                        size="small"
                                        @click="removeOption(field, oi)"
                                    />
                                </div>
                                <Button
                                    label="Add Option"
                                    icon="pi pi-plus"
                                    size="small"
                                    text
                                    @click="addOption(field)"
                                />
                            </div>
                            <div
                                v-if="
                                    field.preTypedNotes &&
                                    field.options.some((o) => o.hasNote)
                                "
                                class="mt-2 p-2 rounded"
                                style="
                                    background: #f9f9f9;
                                    border: 1px dashed #ccc;
                                "
                            >
                                <strong style="font-size: 12px; color: #555"
                                    >Pre-typed Notes Configuration</strong
                                >
                                <div
                                    v-for="(opt, oi) in field.options.filter(
                                        (o) => o.hasNote,
                                    )"
                                    :key="'tpnote-' + oi"
                                    class="d-flex align-items-center gap-2 mt-1"
                                >
                                    <span
                                        style="
                                            font-size: 12px;
                                            min-width: 100px;
                                        "
                                        >{{ opt.value || "(empty)" }}:</span
                                    >
                                    <InputText
                                        v-model="opt.note"
                                        placeholder="Pre-typed note text"
                                        size="small"
                                        style="flex: 1"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <Button
                        label="Add Testing Field"
                        icon="pi pi-plus"
                        size="small"
                        text
                        class="mt-2"
                        @click="addTestingField"
                    />
                </div>

                <!-- ── REPAIR MODULE ─────────────────────────────────────────── -->
                <div
                    class="mb-0 px-2 py-2 rounded d-flex align-items-center justify-content-between"
                    style="
                        background: #fff3e0;
                        border-left: 4px solid #e65100;
                        cursor: pointer;
                    "
                    @click="repairCollapsed = !repairCollapsed"
                >
                    <div class="d-flex align-items-center gap-2">
                        <i class="pi pi-wrench" style="color: #e65100"></i>
                        <strong style="color: #e65100">Repair Module</strong>
                        <span class="text-muted" style="font-size: 12px">
                            ({{ repairFields.length }} categor{{
                                repairFields.length !== 1 ? "ies" : "y"
                            }}
                            <template
                                v-if="repairFields.some((f) => f._fromGlobal)"
                            >
                                —
                                {{
                                    repairFields.filter((f) => f._fromGlobal)
                                        .length
                                }}
                                inherited </template
                            >)
                        </span>
                    </div>
                    <i
                        :class="
                            repairCollapsed
                                ? 'pi pi-chevron-down'
                                : 'pi pi-chevron-up'
                        "
                        style="color: #e65100"
                    ></i>
                </div>

                <div v-if="!repairCollapsed" class="border rounded p-3">
                    <div class="d-flex flex-column gap-3">
                        <!-- Inherited global repair categories (editable) -->
                        <template v-if="globalRepairFields.length">
                            <div class="gc-module-inherited-header">
                                <i class="pi pi-globe"></i>
                                <span
                                    >Categories from Global Config — editable
                                    per ASIN</span
                                >
                            </div>
                        </template>

                        <!-- ALL repair categories -->
                        <div
                            v-for="(category, ci) in repairFields"
                            :key="'rep-' + ci"
                            class="p-3 border rounded"
                            :class="{
                                'gc-from-global-field': category._fromGlobal,
                            }"
                            style="border-left: 3px solid #e65100 !important"
                        >
                            <div
                                class="d-flex justify-content-between align-items-center mb-2"
                            >
                                <div
                                    class="d-flex align-items-center gap-2"
                                    style="flex: 1"
                                >
                                    <InputText
                                        v-model="category.name"
                                        placeholder="Category name (e.g. Screen Damage)"
                                        size="small"
                                        style="flex: 1; font-weight: 600"
                                    />
                                    <span
                                        v-if="category._fromGlobal"
                                        class="gc-from-global-badge"
                                    >
                                        <i
                                            class="pi pi-globe"
                                            style="font-size: 10px"
                                        ></i>
                                        Global
                                    </span>
                                </div>
                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    text
                                    size="small"
                                    @click="repairFields.splice(ci, 1)"
                                />
                            </div>
                            <div class="d-flex flex-column gap-2 ms-2">
                                <div
                                    v-for="(action, ai) in category.actions"
                                    :key="'repa-' + ai"
                                    class="d-flex align-items-center gap-2"
                                >
                                    <InputText
                                        v-model="action.title"
                                        placeholder="Action title"
                                        size="small"
                                        style="flex: 1"
                                    />
                                    <InputText
                                        v-model="action.description"
                                        placeholder="Description"
                                        size="small"
                                        style="flex: 2"
                                    />
                                    <Button
                                        icon="pi pi-trash"
                                        severity="danger"
                                        text
                                        size="small"
                                        @click="category.actions.splice(ai, 1)"
                                    />
                                </div>
                                <Button
                                    label="Add Action"
                                    icon="pi pi-plus"
                                    size="small"
                                    text
                                    @click="addRepairAction(category)"
                                />
                            </div>
                        </div>
                    </div>

                    <Button
                        label="Add Repair Category"
                        icon="pi pi-plus"
                        size="small"
                        text
                        class="mt-2"
                        @click="addRepairField"
                    />
                </div>

                <!-- ── CLEANING MODULE ───────────────────────────────────────── -->
                <div
                    class="mb-0 px-2 py-2 rounded d-flex align-items-center justify-content-between"
                    style="
                        background: #e0f7fa;
                        border-left: 4px solid #00bcd4;
                        cursor: pointer;
                    "
                    @click="cleaningCollapsed = !cleaningCollapsed"
                >
                    <div class="d-flex align-items-center gap-2">
                        <i class="pi pi-sparkles" style="color: #006064"></i>
                        <strong style="color: #006064">Cleaning Module</strong>
                        <span class="text-muted" style="font-size: 12px">
                            ({{ cleaningFields.length }} categor{{
                                cleaningFields.length !== 1 ? "ies" : "y"
                            }}
                            <template
                                v-if="cleaningFields.some((f) => f._fromGlobal)"
                            >
                                —
                                {{
                                    cleaningFields.filter((f) => f._fromGlobal)
                                        .length
                                }}
                                inherited </template
                            >)
                        </span>
                    </div>
                    <i
                        :class="
                            cleaningCollapsed
                                ? 'pi pi-chevron-down'
                                : 'pi pi-chevron-up'
                        "
                        style="color: #006064"
                    ></i>
                </div>

                <div v-if="!cleaningCollapsed" class="border rounded p-3">
                    <div class="d-flex flex-column gap-3">
                        <!-- Inherited global cleaning categories (editable) -->
                        <template v-if="globalCleaningFields.length">
                            <div class="gc-module-inherited-header">
                                <i class="pi pi-globe"></i>
                                <span
                                    >Categories from Global Config — editable
                                    per ASIN</span
                                >
                            </div>
                        </template>

                        <!-- ALL cleaning categories -->
                        <div
                            v-for="(category, ci) in cleaningFields"
                            :key="'cln-' + ci"
                            class="p-3 border rounded"
                            :class="{
                                'gc-from-global-field': category._fromGlobal,
                            }"
                            style="border-left: 3px solid #00bcd4 !important"
                        >
                            <div
                                class="d-flex justify-content-between align-items-center mb-2"
                            >
                                <div
                                    class="d-flex align-items-center gap-2"
                                    style="flex: 1"
                                >
                                    <InputText
                                        v-model="category.name"
                                        placeholder="Category name (e.g. Surface Cleaning)"
                                        size="small"
                                        style="flex: 1; font-weight: 600"
                                    />
                                    <span
                                        v-if="category._fromGlobal"
                                        class="gc-from-global-badge"
                                    >
                                        <i
                                            class="pi pi-globe"
                                            style="font-size: 10px"
                                        ></i>
                                        Global
                                    </span>
                                </div>
                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    text
                                    size="small"
                                    @click="cleaningFields.splice(ci, 1)"
                                />
                            </div>
                            <div class="d-flex flex-column gap-2 ms-2">
                                <div
                                    v-for="(action, ai) in category.actions"
                                    :key="'clna-' + ai"
                                    class="d-flex align-items-center gap-2"
                                >
                                    <InputText
                                        v-model="action.title"
                                        placeholder="Action title"
                                        size="small"
                                        style="flex: 1"
                                    />
                                    <InputText
                                        v-model="action.description"
                                        placeholder="Description"
                                        size="small"
                                        style="flex: 2"
                                    />
                                    <Button
                                        icon="pi pi-trash"
                                        severity="danger"
                                        text
                                        size="small"
                                        @click="category.actions.splice(ai, 1)"
                                    />
                                </div>
                                <Button
                                    label="Add Action"
                                    icon="pi pi-plus"
                                    size="small"
                                    text
                                    @click="addCleaningAction(category)"
                                />
                            </div>
                        </div>
                    </div>

                    <Button
                        label="Add Cleaning Category"
                        icon="pi pi-plus"
                        size="small"
                        text
                        class="mt-2"
                        @click="addCleaningField"
                    />
                </div>

                <!-- ── PACKAGING MODULE ──────────────────────────────────────── -->
                <div
                    class="mb-0 px-2 py-2 rounded d-flex align-items-center justify-content-between"
                    style="
                        background: #e8f5e9;
                        border-left: 4px solid #2e7d32;
                        cursor: pointer;
                    "
                    @click="packagingCollapsed = !packagingCollapsed"
                >
                    <div class="d-flex align-items-center gap-2">
                        <i class="pi pi-box" style="color: #2e7d32"></i>
                        <strong style="color: #2e7d32">Packaging Module</strong>
                        <span class="text-muted" style="font-size: 12px">
                            ({{ packagingComponents.length }} component{{
                                packagingComponents.length !== 1 ? "s" : ""
                            }}
                            <template
                                v-if="
                                    packagingComponents.some(
                                        (c) => c._fromGlobal,
                                    )
                                "
                            >
                                —
                                {{
                                    packagingComponents.filter(
                                        (c) => c._fromGlobal,
                                    ).length
                                }}
                                inherited </template
                            >)
                        </span>
                    </div>
                    <i
                        :class="
                            packagingCollapsed
                                ? 'pi pi-chevron-down'
                                : 'pi pi-chevron-up'
                        "
                        style="color: #2e7d32"
                    ></i>
                </div>

                <div
                    v-if="!packagingCollapsed"
                    class="d-flex flex-column gap-3 pt-1"
                >
                    <!-- ── Product Image (Visual Guide) card ── -->
                    <div class="pkg-card">
                        <div class="pkg-card-header">
                            <i class="pi pi-image"></i>
                            <span>Product Image (Visual Guide)</span>
                        </div>
                        <div
                            class="pkg-card-body d-flex align-items-center gap-4"
                        >
                            <!-- Thumbnail -->
                            <div class="pkg-image-thumb">
                                <img
                                    v-if="packagingImage"
                                    :src="packagingImage"
                                    alt="Packaging"
                                    style="
                                        width: 100%;
                                        height: 100%;
                                        object-fit: contain;
                                    "
                                />
                                <i
                                    v-else
                                    class="pi pi-volume-up"
                                    style="font-size: 2.5rem; color: #bbb"
                                ></i>
                            </div>
                            <!-- Upload controls -->
                            <div class="d-flex flex-column gap-1">
                                <label class="pkg-upload-btn">
                                    <i class="pi pi-camera"></i> Upload Product
                                    Image
                                    <input
                                        type="file"
                                        accept="image/*"
                                        style="display: none"
                                        @change="onPackagingImageUpload"
                                    />
                                </label>
                                <span style="font-size: 12px; color: #e91e8c"
                                    >Recommended: 500x500px or higher</span
                                >
                                <Button
                                    v-if="packagingImage"
                                    label="Remove"
                                    icon="pi pi-trash"
                                    severity="danger"
                                    size="small"
                                    text
                                    @click="packagingImage = null"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- ── Required Components card ── -->
                    <div class="pkg-card">
                        <div class="pkg-card-header">
                            <i class="pi pi-cog"></i>
                            <span>Required Components (Seeds)</span>
                        </div>
                        <div class="pkg-card-body d-flex flex-column gap-2">
                            <!-- ── Catalog search bar ── -->
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <InputText
                                    v-model="suppliesCatalogSearch"
                                    placeholder="Search supplies catalog by name or SKU…"
                                    size="small"
                                    class="flex-grow-1"
                                />
                                <span
                                    v-if="suppliesCatalogLoading"
                                    class="text-muted"
                                    style="font-size: 12px"
                                >
                                    <i class="pi pi-spin pi-spinner"></i>
                                    Loading…
                                </span>
                            </div>

                            <!-- ── Catalog picker (scrollable dropdown list) ── -->
                            <div
                                v-if="
                                    !suppliesCatalogLoading &&
                                    filteredSuppliesCatalog.length
                                "
                                class="border rounded mb-2"
                                style="
                                    max-height: 180px;
                                    overflow-y: auto;
                                    background: #fafafa;
                                "
                            >
                                <div
                                    v-for="item in filteredSuppliesCatalog"
                                    :key="'cat-' + item.id"
                                    class="d-flex align-items-center gap-2 px-2 py-1"
                                    style="
                                        cursor: pointer;
                                        border-bottom: 1px solid #eee;
                                        font-size: 13px;
                                    "
                                    :style="
                                        isAlreadyAdded(item)
                                            ? 'opacity:.45; pointer-events:none;'
                                            : ''
                                    "
                                    @click="addComponentFromCatalog(item)"
                                >
                                    <!-- Thumbnail -->
                                    <img
                                        v-if="item.img"
                                        :src="resolveSupplyThumb(item)"
                                        style="
                                            width: 32px;
                                            height: 32px;
                                            object-fit: contain;
                                            border-radius: 4px;
                                            border: 1px solid #eee;
                                        "
                                        @error="
                                            $event.target.style.display = 'none'
                                        "
                                    />
                                    <div
                                        v-else
                                        style="
                                            width: 32px;
                                            height: 32px;
                                            background: #eee;
                                            border-radius: 4px;
                                            display: flex;
                                            align-items: center;
                                            justify-content: center;
                                            flex-shrink: 0;
                                        "
                                    >
                                        <i
                                            class="pi pi-box"
                                            style="color: #bbb; font-size: 14px"
                                        ></i>
                                    </div>

                                    <div
                                        class="flex-grow-1"
                                        style="min-width: 0"
                                    >
                                        <div
                                            style="
                                                font-weight: 600;
                                                line-height: 1.2;
                                                white-space: nowrap;
                                                overflow: hidden;
                                                text-overflow: ellipsis;
                                            "
                                        >
                                            {{ item.name }}
                                        </div>
                                        <div
                                            class="text-muted"
                                            style="font-size: 11px"
                                        >
                                            {{ item.sku }} · {{ item.category }}
                                        </div>
                                    </div>

                                    <i
                                        v-if="isAlreadyAdded(item)"
                                        class="pi pi-check-circle"
                                        style="
                                            color: #2e7d32;
                                            font-size: 14px;
                                            flex-shrink: 0;
                                        "
                                    ></i>
                                    <i
                                        v-else
                                        class="pi pi-plus-circle"
                                        style="
                                            color: #0d6efd;
                                            font-size: 14px;
                                            flex-shrink: 0;
                                        "
                                    ></i>
                                </div>
                            </div>

                            <!-- No results message -->
                            <div
                                v-else-if="
                                    !suppliesCatalogLoading &&
                                    suppliesCatalogSearch &&
                                    !filteredSuppliesCatalog.length
                                "
                                class="text-muted mb-1"
                                style="font-size: 12px"
                            >
                                No catalog items match "{{
                                    suppliesCatalogSearch
                                }}".
                            </div>

                            <!-- ── Inherited global components header ── -->
                            <template v-if="globalPackagingComponents.length">
                                <div class="gc-module-inherited-header">
                                    <i class="pi pi-globe"></i>
                                    <span
                                        >Components from Global Config —
                                        editable per ASIN</span
                                    >
                                </div>
                            </template>

                            <!-- ── Added components list (global-inherited + ASIN-specific + catalog-picked) ── -->
                            <div
                                v-for="(comp, i) in packagingComponents"
                                :key="'comp-' + i"
                                class="pkg-component-row"
                                :class="{
                                    'gc-from-global-field': comp._fromGlobal,
                                }"
                            >
                                <div class="pkg-comp-icon">
                                    <img
                                        v-if="comp.img"
                                        :src="resolveSupplyThumb(comp)"
                                        style="
                                            width: 32px;
                                            height: 32px;
                                            object-fit: contain;
                                            border-radius: 4px;
                                            border: 1px solid #eee;
                                        "
                                        @error="
                                            $event.target.style.display = 'none'
                                        "
                                    />
                                    <i
                                        v-else
                                        class="pi pi-box"
                                        style="color: #aaa; font-size: 16px"
                                    ></i>
                                </div>
                                <div class="pkg-comp-details">
                                    <div
                                        class="d-flex align-items-center gap-2 mb-1"
                                    >
                                        <InputText
                                            v-model="comp.name"
                                            placeholder="Component name"
                                            size="small"
                                            class="w-100"
                                            style="font-weight: 600"
                                        />
                                        <span
                                            v-if="comp._fromGlobal"
                                            class="gc-from-global-badge"
                                            style="white-space: nowrap"
                                        >
                                            <i
                                                class="pi pi-globe"
                                                style="font-size: 10px"
                                            ></i>
                                            Global
                                        </span>
                                        <span
                                            v-if="comp._fromCatalog"
                                            style="
                                                font-size: 10px;
                                                background: #e3f2fd;
                                                color: #1565c0;
                                                padding: 1px 6px;
                                                border-radius: 4px;
                                                white-space: nowrap;
                                            "
                                        >
                                            <i
                                                class="pi pi-database"
                                                style="font-size: 9px"
                                            ></i>
                                            Catalog
                                        </span>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <InputText
                                            v-model="comp.sku"
                                            placeholder="SKU"
                                            size="small"
                                            style="flex: 2"
                                        />
                                        <InputText
                                            v-model="comp.qty"
                                            placeholder="Qty"
                                            size="small"
                                            style="flex: 1"
                                            type="number"
                                            min="1"
                                        />
                                        <InputText
                                            v-model="comp.note"
                                            placeholder="Note"
                                            size="small"
                                            style="flex: 2"
                                        />
                                    </div>
                                </div>
                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    text
                                    size="small"
                                    style="flex-shrink: 0"
                                    @click="packagingComponents.splice(i, 1)"
                                />
                            </div>

                            <Button
                                label="Add Blank Component"
                                icon="pi pi-plus"
                                size="small"
                                text
                                class="align-self-start mt-1"
                                @click="addPackagingComponent"
                            />
                        </div>
                    </div>

                    <!-- ── Box Specifications card ── -->
                    <div class="pkg-card">
                        <div class="pkg-card-header">
                            <i class="pi pi-box"></i>
                            <span>Box Specifications</span>
                        </div>
                        <div class="pkg-card-body">
                            <div class="row g-2">
                                <div class="col-6 col-md-3">
                                    <label class="pkg-label">
                                        Size
                                        <span
                                            v-if="
                                                !boxSpecs.size &&
                                                globalBoxSpecs.size
                                            "
                                            class="gc-default-badge"
                                            >inherited</span
                                        >
                                    </label>
                                    <InputText
                                        v-model="boxSpecs.size"
                                        :placeholder="
                                            globalBoxSpecs.size ||
                                            'e.g. 12x8x6 in'
                                        "
                                        size="small"
                                        class="w-100"
                                    />
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="pkg-label">
                                        Type
                                        <span
                                            v-if="
                                                !boxSpecs.type &&
                                                globalBoxSpecs.type
                                            "
                                            class="gc-default-badge"
                                            >inherited</span
                                        >
                                    </label>
                                    <InputText
                                        v-model="boxSpecs.type"
                                        :placeholder="
                                            globalBoxSpecs.type || 'e.g. RSC'
                                        "
                                        size="small"
                                        class="w-100"
                                    />
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="pkg-label">
                                        Weight
                                        <span
                                            v-if="
                                                !boxSpecs.weight &&
                                                globalBoxSpecs.weight
                                            "
                                            class="gc-default-badge"
                                            >inherited</span
                                        >
                                    </label>
                                    <InputText
                                        v-model="boxSpecs.weight"
                                        :placeholder="
                                            globalBoxSpecs.weight ||
                                            'e.g. 2 lbs'
                                        "
                                        size="small"
                                        class="w-100"
                                    />
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="pkg-label">
                                        Materials
                                        <span
                                            v-if="
                                                !boxSpecs.materials &&
                                                globalBoxSpecs.materials
                                            "
                                            class="gc-default-badge"
                                            >inherited</span
                                        >
                                    </label>
                                    <InputText
                                        v-model="boxSpecs.materials"
                                        :placeholder="
                                            globalBoxSpecs.materials ||
                                            'e.g. Corrugated'
                                        "
                                        size="small"
                                        class="w-100"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- end d-flex wrapper -->

            <!-- ── DIALOG FOOTER ──────────────────────────────────────────── -->
            <template #footer>
                <Button
                    label="Cancel"
                    severity="secondary"
                    text
                    @click="showASINConfig = false"
                />
                <Button
                    label="Save"
                    icon="pi pi-save"
                    severity="secondary"
                    outlined
                    :loading="savingAll"
                    @click="saveAllFields(false)"
                />
                <Button
                    label="Save & Publish"
                    icon="pi pi-send"
                    :loading="publishing"
                    @click="saveAllFields(true)"
                />
            </template>
        </Dialog>

        <Dialog
            v-model:visible="showGlobalConfig"
            header="Global ASIN Configuration"
            :style="{ width: '860px', maxWidth: '98vw' }"
            :modal="true"
            :closable="true"
            :draggable="false"
        >
            <div class="global-config-wrapper">
                <p class="global-config-hint">
                    <i
                        class="pi pi-info-circle"
                        style="color: #6366f1; margin-right: 6px"
                    ></i>
                    Fields defined here become the <strong>default</strong> for
                    every ASIN. Each ASIN can still override or extend them
                    individually.
                </p>

                <!-- LABELING -->
                <div class="gc-section">
                    <div
                        class="gc-section-header"
                        @click="
                            globalLabelingCollapsed = !globalLabelingCollapsed
                        "
                    >
                        <span><i class="pi pi-tag"></i> Labeling Fields</span>
                        <i
                            :class="
                                globalLabelingCollapsed
                                    ? 'pi pi-chevron-down'
                                    : 'pi pi-chevron-up'
                            "
                        ></i>
                    </div>
                    <div
                        v-if="!globalLabelingCollapsed"
                        class="gc-section-body"
                    >
                        <div
                            v-for="(field, i) in globalLabelingFields"
                            :key="i"
                            class="gc-field-row"
                        >
                            <InputText
                                v-model="field.label"
                                placeholder="Label"
                                size="small"
                                style="flex: 2"
                            />
                            <Select
                                v-model="field.type"
                                :options="fieldTypeOptions"
                                placeholder="Type"
                                size="small"
                                style="flex: 2"
                                @change="onFieldTypeChange(field)"
                            />
                            <InputText
                                v-model="field.defaultValue"
                                placeholder="Default value"
                                size="small"
                                style="flex: 2"
                            />
                            <div class="gc-field-flags">
                                <label
                                    ><input
                                        type="checkbox"
                                        v-model="field.required"
                                    />
                                    Required</label
                                >
                            </div>
                            <Button
                                icon="pi pi-trash"
                                severity="danger"
                                text
                                size="small"
                                @click="globalLabelingFields.splice(i, 1)"
                            />
                        </div>
                        <Button
                            label="Add Field"
                            icon="pi pi-plus"
                            size="small"
                            text
                            @click="
                                globalLabelingFields.push({
                                    label: '',
                                    type: '',
                                    defaultValue: '',
                                    required: false,
                                    hasOptions: false,
                                    preTypedNotes: false,
                                    options: [],
                                })
                            "
                        />
                    </div>
                </div>

                <!-- TESTING -->
                <div class="gc-section">
                    <div
                        class="gc-section-header"
                        @click="
                            globalTestingCollapsed = !globalTestingCollapsed
                        "
                    >
                        <span
                            ><i class="pi pi-check-circle"></i> Testing
                            Fields</span
                        >
                        <i
                            :class="
                                globalTestingCollapsed
                                    ? 'pi pi-chevron-down'
                                    : 'pi pi-chevron-up'
                            "
                        ></i>
                    </div>
                    <div v-if="!globalTestingCollapsed" class="gc-section-body">
                        <div
                            v-for="(field, i) in globalTestingFields"
                            :key="i"
                            class="gc-field-row"
                        >
                            <InputText
                                v-model="field.label"
                                placeholder="Label"
                                size="small"
                                style="flex: 2"
                            />
                            <Select
                                v-model="field.type"
                                :options="fieldTypeOptions"
                                placeholder="Type"
                                size="small"
                                style="flex: 2"
                                @change="onFieldTypeChange(field)"
                            />
                            <InputText
                                v-model="field.defaultValue"
                                placeholder="Default value"
                                size="small"
                                style="flex: 2"
                            />
                            <div class="gc-field-flags">
                                <label
                                    ><input
                                        type="checkbox"
                                        v-model="field.required"
                                    />
                                    Required</label
                                >
                            </div>
                            <Button
                                icon="pi pi-trash"
                                severity="danger"
                                text
                                size="small"
                                @click="globalTestingFields.splice(i, 1)"
                            />
                        </div>
                        <Button
                            label="Add Field"
                            icon="pi pi-plus"
                            size="small"
                            text
                            @click="
                                globalTestingFields.push({
                                    label: '',
                                    type: '',
                                    defaultValue: '',
                                    required: false,
                                    hasOptions: false,
                                    preTypedNotes: false,
                                    options: [],
                                })
                            "
                        />
                    </div>
                </div>

                <!-- REPAIR -->
                <div class="gc-section">
                    <div
                        class="gc-section-header"
                        @click="globalRepairCollapsed = !globalRepairCollapsed"
                    >
                        <span
                            ><i class="pi pi-wrench"></i> Repair
                            Categories</span
                        >
                        <i
                            :class="
                                globalRepairCollapsed
                                    ? 'pi pi-chevron-down'
                                    : 'pi pi-chevron-up'
                            "
                        ></i>
                    </div>
                    <div v-if="!globalRepairCollapsed" class="gc-section-body">
                        <div
                            v-for="(cat, i) in globalRepairFields"
                            :key="i"
                            class="gc-category-row"
                        >
                            <div class="gc-category-header">
                                <InputText
                                    v-model="cat.name"
                                    placeholder="Category name"
                                    size="small"
                                    style="flex: 1"
                                />
                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    text
                                    size="small"
                                    @click="globalRepairFields.splice(i, 1)"
                                />
                            </div>
                            <div class="gc-actions-list">
                                <div
                                    v-for="(action, j) in cat.actions"
                                    :key="j"
                                    class="gc-action-row"
                                >
                                    <InputText
                                        v-model="action.title"
                                        placeholder="Action title"
                                        size="small"
                                        style="flex: 1"
                                    />
                                    <InputText
                                        v-model="action.description"
                                        placeholder="Description"
                                        size="small"
                                        style="flex: 2"
                                    />
                                    <Button
                                        icon="pi pi-trash"
                                        severity="danger"
                                        text
                                        size="small"
                                        @click="cat.actions.splice(j, 1)"
                                    />
                                </div>
                                <Button
                                    label="Add Action"
                                    icon="pi pi-plus"
                                    size="small"
                                    text
                                    @click="
                                        cat.actions.push({
                                            title: '',
                                            description: '',
                                            editing: false,
                                        })
                                    "
                                />
                            </div>
                        </div>
                        <Button
                            label="Add Category"
                            icon="pi pi-plus"
                            size="small"
                            text
                            @click="
                                globalRepairFields.push({
                                    name: '',
                                    actions: [],
                                })
                            "
                        />
                    </div>
                </div>

                <!-- CLEANING -->
                <div class="gc-section">
                    <div
                        class="gc-section-header"
                        @click="
                            globalCleaningCollapsed = !globalCleaningCollapsed
                        "
                    >
                        <span
                            ><i class="pi pi-sparkles"></i> Cleaning
                            Categories</span
                        >
                        <i
                            :class="
                                globalCleaningCollapsed
                                    ? 'pi pi-chevron-down'
                                    : 'pi pi-chevron-up'
                            "
                        ></i>
                    </div>
                    <div
                        v-if="!globalCleaningCollapsed"
                        class="gc-section-body"
                    >
                        <div
                            v-for="(cat, i) in globalCleaningFields"
                            :key="i"
                            class="gc-category-row"
                        >
                            <div class="gc-category-header">
                                <InputText
                                    v-model="cat.name"
                                    placeholder="Category name"
                                    size="small"
                                    style="flex: 1"
                                />
                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    text
                                    size="small"
                                    @click="globalCleaningFields.splice(i, 1)"
                                />
                            </div>
                            <div class="gc-actions-list">
                                <div
                                    v-for="(action, j) in cat.actions"
                                    :key="j"
                                    class="gc-action-row"
                                >
                                    <InputText
                                        v-model="action.title"
                                        placeholder="Action title"
                                        size="small"
                                        style="flex: 1"
                                    />
                                    <InputText
                                        v-model="action.description"
                                        placeholder="Description"
                                        size="small"
                                        style="flex: 2"
                                    />
                                    <Button
                                        icon="pi pi-trash"
                                        severity="danger"
                                        text
                                        size="small"
                                        @click="cat.actions.splice(j, 1)"
                                    />
                                </div>
                                <Button
                                    label="Add Action"
                                    icon="pi pi-plus"
                                    size="small"
                                    text
                                    @click="
                                        cat.actions.push({
                                            title: '',
                                            description: '',
                                            editing: false,
                                        })
                                    "
                                />
                            </div>
                        </div>
                        <Button
                            label="Add Category"
                            icon="pi pi-plus"
                            size="small"
                            text
                            @click="
                                globalCleaningFields.push({
                                    name: '',
                                    actions: [],
                                })
                            "
                        />
                    </div>
                </div>

                <!-- PACKAGING -->
                <div class="gc-section">
                    <div
                        class="gc-section-header"
                        @click="
                            globalPackagingCollapsed = !globalPackagingCollapsed
                        "
                    >
                        <span
                            ><i class="pi pi-box"></i> Packaging Defaults</span
                        >
                        <i
                            :class="
                                globalPackagingCollapsed
                                    ? 'pi pi-chevron-down'
                                    : 'pi pi-chevron-up'
                            "
                        ></i>
                    </div>
                    <div
                        v-if="!globalPackagingCollapsed"
                        class="gc-section-body"
                    >
                        <div class="gc-box-specs">
                            <div class="gc-spec-row">
                                <label>Box Size</label>
                                <InputText
                                    v-model="globalBoxSpecs.size"
                                    placeholder="e.g. 12x8x6 in"
                                    size="small"
                                />
                            </div>
                            <div class="gc-spec-row">
                                <label>Box Type</label>
                                <InputText
                                    v-model="globalBoxSpecs.type"
                                    placeholder="e.g. RSC"
                                    size="small"
                                />
                            </div>
                            <div class="gc-spec-row">
                                <label>Weight</label>
                                <InputText
                                    v-model="globalBoxSpecs.weight"
                                    placeholder="e.g. 2 lbs"
                                    size="small"
                                />
                            </div>
                            <div class="gc-spec-row">
                                <label>Materials</label>
                                <InputText
                                    v-model="globalBoxSpecs.materials"
                                    placeholder="e.g. Corrugated cardboard"
                                    size="small"
                                />
                            </div>
                        </div>
                        <div class="mt-2">
                            <strong style="font-size: 13px"
                                >Global Packaging Components</strong
                            >
                            <div
                                v-for="(comp, i) in globalPackagingComponents"
                                :key="i"
                                class="gc-component-row"
                            >
                                <InputText
                                    v-model="comp.name"
                                    placeholder="Component"
                                    size="small"
                                    style="flex: 3"
                                />
                                <InputText
                                    v-model="comp.sku"
                                    placeholder="SKU"
                                    size="small"
                                    style="flex: 2"
                                />
                                <InputText
                                    v-model="comp.qty"
                                    placeholder="Qty"
                                    size="small"
                                    style="flex: 1"
                                    type="number"
                                    min="1"
                                />
                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    text
                                    size="small"
                                    @click="
                                        globalPackagingComponents.splice(i, 1)
                                    "
                                />
                            </div>
                            <Button
                                label="Add Component"
                                icon="pi pi-plus"
                                size="small"
                                text
                                @click="
                                    globalPackagingComponents.push({
                                        name: '',
                                        sku: '',
                                        qty: 1,
                                        note: '',
                                    })
                                "
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dialog Footer -->
            <template #footer>
                <Button
                    label="Cancel"
                    severity="secondary"
                    text
                    @click="showGlobalConfig = false"
                />
                <Button
                    label="Save Global Config"
                    icon="pi pi-save"
                    :loading="savingGlobalConfig"
                    @click="saveGlobalConfig"
                />
            </template>
        </Dialog>

        <ScrollTop />
    </div>
</template>

<script>
import asinlist from "./asinlist.js";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import {
    Button,
    Dialog,
    InputText,
    Select,
    Textarea,
    ScrollTop,
    Paginator,
} from "primevue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import { ROWS_PER_PAGE } from "../../constant.js";

const TABLE_COLUMNS = [
    {
        header: "Image",
        slot: "image",
        style: { width: "5rem", minWidth: "5rem" },
        headerStyle:
            "width: 5rem; min-width: 5rem; max-width: 5rem; padding: 0.25rem;",
        bodyStyle:
            "width: 5rem; min-width: 5rem; max-width: 5rem; padding: 0.25rem;",
    },
    {
        header: "Product Name",
        slot: "productName",
        style: { minWidth: "15rem", maxWidth: "20rem" },
    },
    {
        header: "ASIN",
        field: "ASIN",
        bodyStyle: "font-size: 14px",
    },
    {
        header: "EAN / UPC",
        slot: "EANUPC",
        bodyStyle: "font-size: 14px",
    },
    {
        header: "Related ASINs",
        slot: "relatedAsins",
        bodyStyle: "font-size: 14px",
    },
    {
        header: "Color",
        slot: "color",
        bodyStyle: "font-size: 14px",
    },
    {
        header: "Qty Inside",
        slot: "quantityInside",
        bodyStyle: "font-size: 14px",
    },
    {
        header: "FNSKUs",
        slot: "fnsku_count",
        bodyStyle: "font-size: 14px",
    },
];

const FNSKU_COLUMNS = [
    {
        header: "FNSKU",
        field: "FNSKU",
        bodyStyle: "font-size: 14px",
    },
    {
        header: "MSKU",
        slot: "MSKU",
        bodyStyle: "font-size: 14px",
    },
    {
        header: "Units",
        slot: "Units",
        bodyStyle: "font-size: 14px",
    },
    {
        header: "Grade",
        field: "grading",
        slot: "grading",
        bodyStyle: "font-size: 14px",
    },
    {
        header: "Times Used",
        slot: "timesused",
        field: "Units",
        bodyStyle: "font-size: 14px",
        sortable: true,
    },
    {
        header: "FNSKU Limit",
        slot: "fnskuLimit",
        bodyStyle: "font-size: 14px",
    },
];

export default {
    mixins: [asinlist],
    components: {
        XDataTable,
        Button,
        Dialog,
        InputText,
        Select,
        Textarea,
        ScrollTop,
        TitlePage,
        Paginator,
    },
    data() {
        return {
            columns: TABLE_COLUMNS,
            fnsku_columns: FNSKU_COLUMNS,
            rowsPerPage: ROWS_PER_PAGE,
        };
    },
    computed: {
        storeOptions() {
            return [
                { value: "", label: "All Stores" },
                ...this.stores.map((store) => ({ value: store, label: store })),
            ];
        },
    },
};
</script>

<style scope>
/* Import base module styles */
@import "../../../css/modules.css";

@media (max-width: 600px) {
    .mobile-fullscreen-dialog.p-dialog {
        width: 100vw !important;
        height: 100vh !important;
        max-width: none !important;
        max-height: none !important;
        border-radius: 0 !important;

        top: 0 !important;
        left: 0 !important;
        transform: none !important;
    }

    .filter-title {
        display: none;
    }

    .search-container fieldset {
        width: 100%;
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 0.1rem;
    }

    .select-form,
    .p-select {
        width: 100% !important;
    }
}

.select-form {
    width: 200px;
}

.search-container {
    margin: 20px 0;
    display: flex;
    align-items: center;
    gap: 20px;
}

.top-header {
    background-color: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.header-buttons {
    display: flex;
    gap: 10px;
    align-items: center;
}

.btn-header {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 10px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-header:hover {
    background-color: #0056b3;
    transform: translateY(-1px);
}

.bulk-upload-btn {
    background-color: #28a745 !important;
}

.bulk-upload-btn:hover {
    background-color: #218838 !important;
}

.store-filter {
    display: flex;
    align-items: center;
    gap: 15px;
}

.store-filter label {
    font-weight: 600;
    color: #495057;
    margin: 0;
}

.store-select {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    background-color: white;
    font-size: 14px;
    min-width: 150px;
}

/* Bulk Instruction Card Upload Modal Styles */
.bulk-instruction-card-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1200;
}

.bulk-instruction-card-modal-content {
    background-color: white;
    border-radius: 12px;
    width: 90%;
    max-width: 700px;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.bulk-instruction-card-modal-header {
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #f8f9fa;
    border-radius: 12px 12px 0 0;
}

.bulk-instruction-card-modal-header h3 {
    margin: 0;
    color: #495057;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.bulk-instruction-card-modal-body {
    padding: 30px;
}

.bulk-upload-form {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-weight: 600;
    color: #495057;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.bulk-asin-textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
    font-family: "Courier New", monospace;
    resize: vertical;
    transition: border-color 0.3s ease;
}

.bulk-asin-textarea:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.asin-count-info {
    font-size: 12px;
    color: #6c757d;
    font-weight: 500;
}

.asin-count-info .error-text {
    color: #dc3545;
    font-weight: 600;
}

.bulk-card-select {
    padding: 10px 12px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
    background-color: white;
    transition: border-color 0.3s ease;
}

.bulk-card-select:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.file-upload-container {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.bulk-file-input {
    padding: 10px;
    border: 2px dashed #dee2e6;
    border-radius: 6px;
    background-color: #f8f9fa;
    cursor: pointer;
    transition: all 0.3s ease;
}

.bulk-file-input:hover {
    border-color: #007bff;
    background-color: #e9ecef;
}

.selected-file-info {
    padding: 8px 12px;
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    border-radius: 4px;
    color: #155724;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.text-success {
    color: #28a745;
}

.bulk-upload-instructions {
    background-color: #e9ecef;
    padding: 15px;
    border-radius: 6px;
    border-left: 4px solid #007bff;
}

.bulk-upload-instructions h4 {
    margin: 0 0 10px 0;
    color: #495057;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.bulk-upload-instructions ul {
    margin: 0;
    padding-left: 20px;
    color: #6c757d;
    font-size: 13px;
    line-height: 1.6;
}

.bulk-upload-instructions li {
    margin-bottom: 4px;
}

.card-upload-section {
    margin-bottom: 15px;
    padding: 15px;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    background-color: #f8f9fa;
}

.card-upload-label {
    font-weight: 600;
    color: #495057;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 8px;
}

.cards-selected-info {
    font-size: 13px;
    color: #6c757d;
    font-weight: 600;
    padding: 10px;
    background-color: #e9ecef;
    border-radius: 4px;
    text-align: center;
    margin-top: 15px;
}

.cards-selected-info .error-text {
    color: #dc3545;
    font-weight: 600;
}

.bulk-upload-progress {
    text-align: center;
    padding: 30px;
    background-color: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.progress-spinner {
    font-size: 24px;
    color: #007bff;
    margin-bottom: 15px;
}

.progress-text {
    font-size: 16px;
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
}

.progress-details {
    font-size: 14px;
    color: #6c757d;
}

.bulk-upload-results {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    border: 1px solid #dee2e6;
}

.bulk-upload-results h4 {
    margin: 0 0 15px 0;
    color: #495057;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.results-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.result-item {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 600;
}

.result-item.success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.result-item.failed {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.result-item.skipped {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.bulk-instruction-card-modal-footer {
    padding: 20px;
    border-top: 1px solid #dee2e6;
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    background-color: #f8f9fa;
    border-radius: 0 0 12px 12px;
}

.btn-cancel-bulk,
.btn-upload-bulk {
    padding: 12px 24px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    border: none;
}

.btn-cancel-bulk {
    background-color: #6c757d;
    color: white;
}

.btn-cancel-bulk:hover:not(:disabled) {
    background-color: #5a6268;
}

.btn-upload-bulk {
    background-color: #28a745;
    color: white;
}

.btn-upload-bulk:hover:not(:disabled) {
    background-color: #218838;
    transform: translateY(-1px);
}

.btn-cancel-bulk:disabled,
.btn-upload-bulk:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
    transform: none;
}

/* Modal close button shared styles */
.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6c757d;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.3s ease;
}

.modal-close:hover {
    color: #495057;
}

/* Mobile responsiveness for bulk upload */
@media (max-width: 768px) {
    .top-header {
        flex-direction: column;
        align-items: stretch;
    }

    .header-buttons {
        justify-content: center;
        flex-wrap: wrap;
    }

    .store-filter {
        justify-content: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .bulk-instruction-card-modal-content {
        width: 95%;
        margin: 10px;
        max-width: none;
    }

    .bulk-instruction-card-modal-body {
        padding: 20px;
    }

    .results-summary {
        flex-direction: column;
    }

    .bulk-instruction-card-modal-footer {
        flex-direction: column;
        gap: 10px;
    }

    .btn-cancel-bulk,
    .btn-upload-bulk {
        width: 100%;
        justify-content: center;
    }
}

/* Simple ASIN viewer styles */
.asin-viewer-module {
    max-width: 100%;
    margin: 0 auto;
}

.fnsku-count {
    font-weight: 600;
    color: #007bff;
}

/* Title cell styling */
.title-cell {
    max-width: 300px;
}

.title-content {
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 12px;
    color: #495057;
}

/* Codes container styling */
.codes-container {
    font-size: 11px;
}

.code-item {
    margin-bottom: 2px;
}

.code-label {
    font-weight: 600;
    color: #495057;
    margin-right: 4px;
}

.code-value {
    color: #007bff;
    font-family: "Courier New", monospace;
}

.no-codes {
    color: #6c757d;
    font-style: italic;
}

/* Related ASINs styling */
.related-asins {
    font-size: 11px;
    max-width: 200px;
}

.related-item {
    margin-bottom: 2px;
    display: flex;
    flex-wrap: wrap;
}

.related-label {
    font-weight: 600;
    color: #495057;
    margin-right: 4px;
    min-width: 50px;
}

.related-value {
    color: #007bff;
    font-family: "Courier New", monospace;
    font-size: 10px;
    word-break: break-all;
}

.no-related {
    color: #6c757d;
    font-style: italic;
}

/* Table layout fixes */
.table-container table {
    table-layout: fixed;
    width: 100%;
    min-width: 1220px;
}

.sticky-col.first-col {
    position: sticky;
    left: 0;
    background-color: white;
    z-index: 10;
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
}

/* Product container fixed width */
.product-container {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    max-width: 340px;
}

.product-image-container {
    flex-shrink: 0;
    width: 60px;
    height: 60px;
}

.product-thumbnail {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 4px;
    border: 1px solid #dee2e6;
}

.product-info {
    flex: 1;
    min-width: 0;
}

.product-name {
    margin: 0 0 4px 0;
    font-size: 13px;
    font-weight: 600;
    color: #495057;
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    word-wrap: break-word;
}

.product-title {
    margin: 0;
    font-size: 11px;
    color: #6c757d;
    line-height: 1.2;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    word-wrap: break-word;
    font-style: italic;
}

.fnsku-table-container {
    width: 100%;
    overflow-x: auto;
    margin-top: 10px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.fnsku-detail-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    background-color: #fff;
    border-radius: 8px;
    overflow: hidden;
    min-width: 600px;
}

.fnsku-detail-table thead {
    background-color: #1a252f;
    color: white;
}

.fnsku-detail-table thead th {
    padding: 14px 12px;
    text-align: left;
    font-weight: 700;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.fnsku-detail-table tbody td {
    padding: 10px 12px;
    border-bottom: 1px solid #dee2e6;
    color: #495057;
    font-size: 12px;
}

.fnsku-detail-table tbody tr:nth-child(even) {
    background-color: #f8f9fa;
}

.fnsku-detail-table tbody tr:hover {
    background-color: #e3f2fd;
}

.fnsku-code {
    font-family: "Courier New", monospace;
    font-weight: 600;
    color: #007bff;
}

/* Mobile FNSKU styling */
.mobile-fnsku-list {
    margin-top: 10px;
}

.mobile-fnsku-item {
    background-color: #ffffff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 10px;
}

.mobile-fnsku-detail {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 0;
    border-bottom: 1px solid #e3f2fd;
}

.mobile-fnsku-detail:last-child {
    border-bottom: none;
}

.mobile-fnsku-label {
    font-weight: 600;
    color: #495057;
    font-size: 12px;
}

.mobile-fnsku-value {
    color: #6c757d;
    font-size: 12px;
    text-align: right;
}

.mobile-section h4 {
    background-color: #1a252f;
    color: white;
    padding: 10px 15px;
    margin: 0 0 10px 0;
    border-radius: 6px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* ASIN Details Modal Styling */
.asin-details-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.asin-details-content {
    background-color: white;
    border-radius: 12px;
    width: 95%;
    max-width: 1400px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.asin-details-header {
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #f8f9fa;
    border-radius: 12px 12px 0 0;
}

.header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.btn-edit {
    background-color: #17a2b8;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s ease;
}

.btn-edit:hover {
    background-color: #138496;
}

.btn-edit.active {
    background-color: #ffc107;
    color: #212529;
}

.asin-details-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6c757d;
}

.asin-details-body {
    padding: 20px;
}

.asin-details-layout {
    display: flex;
    gap: 25px;
}

.asin-details-left {
    flex: 0 0 380px;
    max-width: 380px;
}

.asin-details-right {
    flex: 1;
    min-width: 0;
    max-width: 100%;
}

/* Images Section - Updated for uniform sizing */
.images-section {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.image-container {
    flex: 1;
    text-align: center;
    min-width: 120px;
    max-width: 180px;
    position: relative;
}

.asin-details-image {
    margin-bottom: 8px;
}

.asin-details-thumbnail {
    width: 100%;
    max-width: 180px;
    height: 200px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    cursor: pointer;
    transition: transform 0.3s ease;
}

.asin-details-thumbnail.enlarged {
    transform: scale(1.2);
}

/* Shared styles for image containers with overlays */
.instruction-card-main,
.asin-images-main {
    position: relative;
    margin-bottom: 8px;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s ease;
}

.instruction-card-main:hover,
.asin-images-main:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.instruction-card-main-thumbnail,
.asin-images-main-thumbnail {
    width: 100%;
    max-width: 180px;
    height: 200px;
    object-fit: cover;
    border: 1px solid #dee2e6;
    display: block;
    border-radius: 8px;
}

/* Small thumbnails overlay - shared styles for 3 cards */
.instruction-card-thumbnails,
.asin-images-thumbnails {
    position: absolute;
    bottom: 5px;
    right: 5px;
    display: flex;
    gap: 2px;
    flex-wrap: wrap;
    max-width: 70px;
    /* Accommodate 3 thumbnails */
}

.small-thumb {
    width: 22px;
    /* Slightly smaller to fit 3 */
    height: 22px;
    border-radius: 3px;
    border: 2px solid #fff;
    position: relative;
    overflow: hidden;
    background-color: #f8f9fa;
}

.small-thumb.has-image {
    border-color: #28a745;
}

/* Specific styling for ASIN image thumbnails */
.asin-images-thumbnails .asin-thumb.has-image {
    border-color: #28a745;
}

.asin-images-thumbnails .vector-thumb.has-image {
    border-color: #6f42c1;
}

.small-thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 0.9;
}

/* Thumbnail labels and numbers */
.thumb-number,
.thumb-label {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: rgba(0, 0, 0, 0.7);
    color: white;
    font-weight: bold;
    font-size: 8px;
    /* Smaller font for 3 cards */
}

.small-thumb.has-image .thumb-number,
.small-thumb.has-image .thumb-label {
    display: none;
}

/* User Manual Container Styles */
.user-manual-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 200px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background-color: #f8f9fa;
    transition: all 0.3s ease;
}

.user-manual-container.has-manual {
    background-color: #e8f5e8;
    border-color: #28a745;
}

.user-manual-icon {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 20px;
}

.user-manual-icon i {
    font-size: 48px;
    color: #dc3545;
    transition: color 0.3s ease;
}

.user-manual-container.has-manual .user-manual-icon i {
    color: #28a745;
}

.user-manual-icon.no-manual i {
    color: #6c757d;
    opacity: 0.5;
}

.user-manual-icon span {
    font-size: 12px;
    font-weight: 600;
    color: #495057;
    text-align: center;
}

.user-manual-link {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    color: inherit;
    transition: all 0.3s ease;
    padding: 10px;
    border-radius: 6px;
}

.user-manual-link:hover {
    background-color: rgba(40, 167, 69, 0.1);
    transform: translateY(-2px);
    text-decoration: none;
    color: inherit;
}

.user-manual-upload {
    margin-top: 15px;
    width: 100%;
    display: flex;
    justify-content: center;
}

.btn-upload-manual {
    background-color: #dc3545;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-upload-manual:hover:not(:disabled) {
    background-color: #c82333;
    transform: translateY(-1px);
}

.btn-upload-manual:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
    transform: none;
}

.image-label {
    font-size: 11px;
    font-weight: 600;
    color: #495057;
    text-align: center;
    padding: 4px 6px;
    background-color: #f8f9fa;
    border-radius: 4px;
    border: 1px solid #dee2e6;
    margin-top: 5px;
}

/* ASIN Details Info */
.asin-details-info {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
}

.asin-details-title {
    margin-bottom: 15px;
    color: #495057;
    font-size: 18px;
    word-wrap: break-word;
}

/* Details Sections Styling */
.details-section {
    margin-bottom: 25px;
    padding: 15px;
    background-color: #ffffff;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.section-title {
    margin: 0 0 15px 0;
    color: #495057;
    font-size: 16px;
    font-weight: 600;
    border-bottom: 2px solid #007bff;
    padding-bottom: 8px;
}

.amazon-dimensions .section-title {
    border-bottom-color: #28a745;
}

.default-dimensions .section-title {
    border-bottom-color: #ffc107;
}

.related-dimensions {
    border-bottom-color: #6a07ff;
}

.asin-details-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}

.asin-details-row:last-child {
    border-bottom: none;
}

.asin-details-label {
    font-weight: 600;
    color: #495057;
    min-width: 120px;
    font-size: 13px;
}

.asin-details-value {
    color: #6c757d;
    text-align: right;
    flex: 1;
    margin-left: 10px;
    word-wrap: break-word;
    font-size: 13px;
}

/* Input styling for details */
.details-input {
    width: 120px;
    padding: 6px 10px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 12px;
    transition: border-color 0.3s ease;
}

.details-input:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.details-textarea {
    width: 200px;
    padding: 6px 10px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 12px;
    resize: vertical;
    min-height: 60px;
    font-family: inherit;
    transition: border-color 0.3s ease;
}

.details-textarea:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.instruction-link-input {
    width: 200px !important;
    font-size: 11px;
}

/* Dimensions Grid Styling */
.dimensions-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.dimension-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.dimension-label {
    font-weight: 600;
    color: #495057;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.dimension-value {
    color: #6c757d;
    font-size: 13px;
    font-weight: 500;
}

.dimension-input {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 12px;
    transition: border-color 0.3s ease;
}

.dimension-input:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.weight-input-group {
    display: flex;
    gap: 8px;
    align-items: center;
}

.weight-value {
    flex: 2;
}

.weight-unit-select {
    flex: 1;
    padding: 6px 8px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 12px;
    background-color: white;
    transition: border-color 0.3s ease;
}

.weight-unit-select:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

/* Action buttons styling */
.dimensions-actions {
    text-align: center;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.btn-save-dimensions {
    background-color: #ffc107;
    color: #212529;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-save-dimensions:hover:not(:disabled) {
    background-color: #e0a800;
    transform: translateY(-1px);
}

.btn-save-dimensions:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
    transform: none;
    color: white;
}

/* Link styling for details */
.instruction-link,
.user-manual-link-text,
.vector-image-link-text {
    text-decoration: none;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: color 0.3s ease;
}

.instruction-link {
    color: #007bff;
}

.instruction-link:hover {
    color: #0056b3;
    text-decoration: underline;
}

.user-manual-link-text {
    color: #dc3545;
}

.user-manual-link-text:hover {
    color: #c82333;
    text-decoration: underline;
}

.vector-image-link-text {
    color: #6f42c1;
}

.vector-image-link-text:hover {
    color: #5a32a3;
    text-decoration: underline;
}

.instruction-link i,
.user-manual-link-text i,
.vector-image-link-text i {
    font-size: 10px;
}

/* ASIN Details Actions */
.asin-details-actions {
    margin-top: 15px;
    text-align: center;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.btn-save-asin-details {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-save-asin-details:hover:not(:disabled) {
    background-color: #0056b3;
    transform: translateY(-1px);
}

.btn-save-asin-details:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
    transform: none;
}

.asin-details-stores-section {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.asin-details-stores-section h4 {
    margin-bottom: 10px;
    color: #495057;
    font-size: 14px;
}

.stores-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.store-item {
    background-color: #007bff;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Related ASINs Section */
/* .asin-details-related-section {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
} */

.asin-details-related-section h4 {
    margin-bottom: 10px;
    color: #495057;
    font-size: 14px;
}

.related-asins-details {
    background-color: #ffffff;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #dee2e6;
}

.related-asin-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f8f9fa;
}

.related-asin-item:last-child {
    border-bottom: none;
}

.related-asin-label {
    font-weight: 600;
    color: #495057;
    font-size: 12px;
    min-width: 100px;
}

.related-asin-value {
    color: #007bff;
    font-family: "Courier New", monospace;
    font-size: 12px;
    text-align: right;
}

.related-asin-input {
    width: 150px;
    padding: 6px 10px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 12px;
    font-family: "Courier New", monospace;
    transition: border-color 0.3s ease;
}

.related-asin-input:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.related-asins-actions {
    margin-top: 15px;
    text-align: center;
}

.btn-save-related {
    background-color: #28a745;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-save-related:hover:not(:disabled) {
    background-color: #218838;
    transform: translateY(-1px);
}

.btn-save-related:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
    transform: none;
}

/* FNSKU Table Section */
.asin-details-section h4 {
    margin-bottom: 15px;
    color: #495057;
    font-size: 16px;
}

.responsive-table-container {
    width: 100%;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    overflow-x: auto;
}

.asin-details-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    table-layout: fixed;
    min-width: 600px;
}

.asin-details-table thead {
    background-color: #1a252f !important;
    color: white !important;
}

.asin-details-table thead th {
    padding: 12px 8px !important;
    text-align: left !important;
    font-weight: 700 !important;
    font-size: 12px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    color: white !important;
    background-color: #1a252f !important;
    white-space: nowrap;
}

.asin-details-table thead th:nth-child(1) {
    width: 40%;
}

.asin-details-table thead th:nth-child(2) {
    width: 30%;
}

.asin-details-table thead th:nth-child(3) {
    width: 15%;
}

.asin-details-table thead th:nth-child(4) {
    width: 15%;
}

.asin-details-table tbody td {
    padding: 10px 8px;
    border-bottom: 1px solid #dee2e6;
    color: #495057;
    font-size: 12px;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.asin-details-table .units-cell {
    text-align: center;
    font-weight: 600;
    color: #007bff;
}

.asin-details-table tbody tr:nth-child(even) {
    background-color: #f8f9fa;
}

.asin-details-table tbody tr:hover {
    background-color: #e9ecef;
}

.grade-cell {
    text-align: center;
    padding: 8px !important;
    font-weight: 600;
    color: #28a745;
}

/* Footer */
.asin-details-footer {
    padding: 20px;
    border-top: 1px solid #dee2e6;
    text-align: right;
    background-color: #f8f9fa;
}

.btn-close-details {
    background-color: #6c757d;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s ease;
}

.btn-close-details:hover {
    background-color: #5a6268;
}

/* Modal Shared Styles - Updated for 3 cards */
.instruction-card-modal,
.asin-image-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1100;
}

.instruction-card-modal-content,
.asin-image-modal-content {
    background-color: white;
    border-radius: 12px;
    width: 90%;
    max-width: 1100px;
    /* Increased from 800px to accommodate 3 cards */
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.instruction-card-modal-header,
.asin-image-modal-header {
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #f8f9fa;
    border-radius: 12px 12px 0 0;
}

.instruction-card-modal-header h3,
.asin-image-modal-header h3 {
    margin: 0;
    color: #495057;
    font-size: 18px;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6c757d;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.3s ease;
}

.modal-close:hover {
    color: #495057;
}

.instruction-card-modal-body,
.asin-image-modal-body {
    padding: 30px;
}

/* Updated layout for 3 cards */
.card-management-layout,
.image-management-layout {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
}

.card-slot,
.image-slot {
    flex: 1;
    min-width: 250px;
    max-width: 300px;
    text-align: center;
}

.card-slot-header,
.image-slot-header {
    margin-bottom: 15px;
}

.card-slot-header h4,
.image-slot-header h4 {
    margin: 0;
    color: #495057;
    font-size: 16px;
    font-weight: 600;
}

.card-slot-image,
.image-slot-image {
    margin-bottom: 20px;
    border-radius: 8px;
    overflow: hidden;
    border: 2px solid #dee2e6;
    background-color: #f8f9fa;
}

.card-slot-thumbnail,
.image-slot-thumbnail {
    width: 100%;
    height: 250px;
    object-fit: cover;
    display: block;
}

.card-slot-actions,
.image-slot-actions {
    text-align: center;
}

/* Upload button styles */
.btn-upload-card,
.btn-upload-image,
.btn-upload-vector {
    border: none;
    padding: 12px 24px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    min-width: 150px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin: 0 auto;
    color: white;
}

.btn-upload-card {
    background-color: #007bff;
}

.btn-upload-card:hover:not(:disabled) {
    background-color: #0056b3;
    transform: translateY(-1px);
}

.btn-upload-image {
    background-color: #28a745;
}

.btn-upload-image:hover:not(:disabled) {
    background-color: #218838;
    transform: translateY(-1px);
}

.btn-upload-vector {
    background-color: #6f42c1;
}

.btn-upload-vector:hover:not(:disabled) {
    background-color: #5a32a3;
    transform: translateY(-1px);
}

.btn-upload-card:disabled,
.btn-upload-image:disabled,
.btn-upload-vector:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
    transform: none;
}

.instruction-card-modal-footer,
.asin-image-modal-footer {
    padding: 20px;
    border-top: 1px solid #dee2e6;
    text-align: right;
    background-color: #f8f9fa;
    border-radius: 0 0 12px 12px;
}

.btn-close-modal {
    background-color: #6c757d;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s ease;
}

.btn-close-modal:hover {
    background-color: #5a6268;
}

/* Error and success states */
.instruction-card-thumbnail[style*="opacity: 0.5"],
.card-slot-thumbnail[style*="opacity: 0.5"] {
    filter: grayscale(100%);
    border-style: dashed;
}

.instruction-card-thumbnail.uploaded,
.card-slot-thumbnail.uploaded {
    border-color: #28a745;
    box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.25);
}

/* Mobile Responsive Updates */
@media (max-width: 768px) {
    .images-section {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .image-container {
        max-width: 100%;
        margin-bottom: 0;
    }

    .user-manual-container {
        height: 150px;
    }

    .user-manual-icon i {
        font-size: 36px;
    }

    .user-manual-icon span {
        font-size: 11px;
    }

    .instruction-link-input {
        width: 150px !important;
    }

    .details-textarea {
        width: 150px !important;
    }

    .card-management-layout,
    .image-management-layout {
        flex-direction: column;
        gap: 15px;
    }

    .card-slot,
    .image-slot {
        max-width: 100%;
        min-width: auto;
    }

    .instruction-card-modal-content,
    .asin-image-modal-content {
        width: 95%;
        margin: 10px;
        max-width: none;
    }

    .asin-details-layout {
        flex-direction: column;
    }

    .asin-details-left {
        flex: none;
        max-width: 100%;
        margin-bottom: 20px;
    }

    .dimensions-grid {
        grid-template-columns: 1fr;
    }

    .weight-input-group {
        flex-direction: column;
        gap: 5px;
    }

    .weight-value {
        flex: none;
    }

    .weight-unit-select {
        flex: none;
    }

    .asin-details-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }

    .asin-details-label {
        min-width: auto;
    }

    .asin-details-value {
        text-align: left;
        margin-left: 0;
    }

    .details-input,
    .related-asin-input {
        width: 100%;
    }

    .instruction-card-thumbnails,
    .asin-images-thumbnails {
        max-width: 80px;
        gap: 1px;
    }

    .small-thumb {
        width: 24px;
        height: 24px;
    }

    .thumb-number,
    .thumb-label {
        font-size: 9px;
    }
}

@media (min-width: 1200px) {
    .instruction-card-modal-content,
    .asin-image-modal-content {
        max-width: 1200px;
    }
}

.image-loading {
    position: relative;
    overflow: hidden;
}

.image-loading::after {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.4),
        transparent
    );
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% {
        left: -100%;
    }

    100% {
        left: 100%;
    }
}

/* Image refresh indicator */
.image-refreshing {
    filter: brightness(0.8);
    transition: filter 0.3s ease;
}

/* Success state for newly uploaded images */
.image-uploaded {
    animation: imageUploaded 0.6s ease-out;
    border: 2px solid #28a745;
}

@keyframes imageUploaded {
    0% {
        transform: scale(0.95);
        opacity: 0.7;
    }

    50% {
        transform: scale(1.02);
        opacity: 1;
    }

    100% {
        transform: scale(1);
        opacity: 1;
    }
}

/* NEW: Enhanced Bulk Upload Design */
.bulk-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.bulk-card-upload-slot {
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s ease;
}

.bulk-card-upload-slot:hover {
    border-color: #007bff;
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
}

.bulk-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.bulk-card-header h4 {
    margin: 0;
    color: #495057;
    font-size: 14px;
    font-weight: 600;
}

.optional-badge {
    background: #6c757d;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.bulk-card-preview {
    width: 100%;
    height: 200px;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
    overflow: hidden;
    transition: all 0.3s ease;
    background: white;
}

.bulk-card-preview.has-image {
    border-color: #28a745;
    border-style: solid;
}

.bulk-card-preview-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.bulk-card-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    color: #6c757d;
    text-align: center;
}

.bulk-card-placeholder i {
    font-size: 48px;
    opacity: 0.5;
}

.bulk-card-placeholder span {
    font-size: 13px;
    font-weight: 500;
}

.bulk-card-actions {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}

.bulk-file-input-hidden {
    display: none !important;
}

.btn-select-file {
    flex: 1;
    background: #007bff;
    color: white;
    border: none;
    padding: 10px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.btn-select-file:hover:not(:disabled) {
    background: #0056b3;
    transform: translateY(-1px);
}

.btn-remove-file {
    background: #dc3545;
    color: white;
    border: none;
    padding: 10px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.btn-remove-file:hover:not(:disabled) {
    background: #c82333;
    transform: translateY(-1px);
}

.btn-select-file:disabled,
.btn-remove-file:disabled {
    background: #6c757d;
    cursor: not-allowed;
    transform: none;
}

.file-info {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 10px;
    font-size: 12px;
}

.file-name {
    font-weight: 600;
    color: #495057;
    margin-bottom: 4px;
    word-break: break-all;
}

.file-size {
    color: #6c757d;
}

.cards-selected-summary {
    background: #e3f2fd;
    border: 1px solid #90caf9;
    border-radius: 8px;
    padding: 12px 16px;
    margin-top: 20px;
    color: #0d47a1;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.cards-selected-summary i {
    color: #1976d2;
}

.cards-selected-summary strong {
    color: #0d47a1;
}

.cards-selected-summary .error-text {
    color: #d32f2f;
    font-weight: 600;
}

/* Update existing bulk upload styles */
.bulk-instruction-card-modal-content {
    max-width: 900px;
}

.bulk-upload-progress {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    text-align: center;
    padding: 30px;
    border-radius: 12px;
    margin: 20px 0;
}

.progress-spinner {
    font-size: 32px;
    margin-bottom: 15px;
}

.progress-text {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 8px;
}

.progress-details {
    font-size: 14px;
    opacity: 0.9;
}

.bulk-upload-results {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 12px;
    padding: 20px;
    margin: 20px 0;
}

.bulk-upload-results h4 {
    color: #495057;
    margin: 0 0 15px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.results-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
}

.result-item {
    background: white;
    border-radius: 8px;
    padding: 16px;
    text-align: center;
    font-weight: 600;
    border: 2px solid transparent;
    transition: all 0.3s ease;
}

.result-item.success {
    color: #155724;
    border-color: #28a745;
    background: linear-gradient(135deg, #d4edda, #c3e6cb);
}

.result-item.failed {
    color: #721c24;
    border-color: #dc3545;
    background: linear-gradient(135deg, #f8d7da, #f5c6cb);
}

.result-item.skipped {
    color: #856404;
    border-color: #ffc107;
    background: linear-gradient(135deg, #fff3cd, #ffeaa7);
}

.result-item i {
    font-size: 18px;
    margin-bottom: 8px;
    display: block;
}

.color-cell {
    display: flex;
    align-items: center;
    gap: 8px;
}

.quantity-inside-cell {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Optional: Color-specific styling for dropdown options */
.p-dropdown-item[data-value="Black"] {
    color: #000;
    font-weight: 600;
}

.p-dropdown-item[data-value="White"] {
    color: #333;
    background-color: #f8f9fa;
    font-weight: 600;
}

.p-dropdown-item[data-value="Gray"] {
    color: #6c757d;
    font-weight: 600;
}

.p-dropdown-item[data-value="Blue"] {
    color: #007bff;
    font-weight: 600;
}

.p-dropdown-item[data-value="Green"] {
    color: #28a745;
    font-weight: 600;
}

.p-dropdown-item[data-value="Red"] {
    color: #dc3545;
    font-weight: 600;
}

.p-dropdown-item[data-value="Yellow"] {
    color: #ffc107;
    font-weight: 600;
}

/* ══════════════════════════════════════════════════════════════
   GLOBAL CONFIG DIALOG
   ══════════════════════════════════════════════════════════════ */

.global-config-wrapper {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.global-config-hint {
    font-size: 13px;
    color: #555;
    background: #f0f0ff;
    border-left: 3px solid #6366f1;
    padding: 8px 12px;
    border-radius: 4px;
    margin-bottom: 0.5rem;
}

/* ── Global config section card ─────────────────────────────── */
.gc-section {
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    overflow: hidden;
}

.gc-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    background: #f8fafc;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    gap: 8px;
    user-select: none;
}

.gc-section-header:hover {
    background: #f1f5f9;
}

.gc-section-header i:first-child {
    margin-right: 6px;
    color: #6366f1;
}

.gc-section-body {
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* ── Global config field rows ───────────────────────────────── */
.gc-field-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.gc-field-flags {
    display: flex;
    gap: 10px;
    font-size: 13px;
    white-space: nowrap;
}

.gc-field-flags label {
    display: flex;
    align-items: center;
    gap: 4px;
    cursor: pointer;
}

/* ── Global config category / action rows ───────────────────── */
.gc-category-row {
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.gc-category-header {
    display: flex;
    align-items: center;
    gap: 8px;
}

.gc-actions-list {
    padding-left: 12px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.gc-action-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* ── Global config packaging specs ─────────────────────────── */
.gc-box-specs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 12px;
}

.gc-spec-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 13px;
}

.gc-component-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 4px;
}

/* ══════════════════════════════════════════════════════════════
   PER-ASIN CONFIG DIALOG — INHERITED FIELDS
   ══════════════════════════════════════════════════════════════ */

/* Inherited block header inside a module */
.gc-module-inherited-header {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #6d28d9;
    background: #f5f3ff;
    border: 1px solid #ddd6fe;
    border-radius: 4px;
    padding: 5px 10px;
}

.gc-module-inherited-header i {
    color: #7c3aed;
    font-size: 12px;
}

/* Fields that originated from global config — subtle left accent, still fully editable */
.gc-from-global-field {
    border-left: 3px solid #a78bfa !important;
    background: #faf8ff;
}

/* "Global" pill badge shown next to the field label */
.gc-from-global-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 10px;
    font-weight: 700;
    background: #ede9fe;
    color: #6d28d9;
    border-radius: 999px;
    padding: 2px 7px;
    white-space: nowrap;
    letter-spacing: 0.3px;
    flex-shrink: 0;
}

/* Divider between inherited and ASIN-specific blocks */
.gc-section-divider {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 11px;
    color: #999;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.gc-section-divider::before,
.gc-section-divider::after {
    content: "";
    flex: 1;
    border-top: 1px dashed #ddd;
}

/* ══════════════════════════════════════════════════════════════
   PACKAGING MODULE CARDS
   ══════════════════════════════════════════════════════════════ */

.pkg-card {
    border: 1.5px solid #f9a8d4;
    border-radius: 10px;
    overflow: hidden;
    background: #fff;
}

.pkg-card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: #fce7f3;
    font-weight: 600;
    font-size: 14px;
    color: #9d174d;
    border-bottom: 1px solid #f9a8d4;
}

.pkg-card-header i {
    color: #db2777;
    font-size: 15px;
}

.pkg-card-body {
    padding: 14px 16px;
}

.pkg-label {
    display: block;
    font-size: 12px;
    color: #555;
    margin-bottom: 3px;
}

/* Upload button */
.pkg-upload-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: #db2777;
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.15s;
}

.pkg-upload-btn:hover {
    background: #be185d;
}

/* Thumbnail box */
.pkg-image-thumb {
    width: 120px;
    height: 120px;
    min-width: 120px;
    border: 2px solid #f9a8d4;
    border-radius: 8px;
    background: #fff0f6;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

/* Component row */
.pkg-component-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 12px;
    border: 1px solid #fce7f3;
    border-radius: 8px;
    background: #fff;
}

.pkg-comp-icon {
    width: 36px;
    height: 36px;
    min-width: 36px;
    background: #fce7f3;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 2px;
}

.pkg-comp-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.pkg-comp-name {
    font-weight: 600;
    font-size: 14px;
    color: #111;
}

.pkg-comp-meta {
    display: flex;
    gap: 8px;
    font-size: 12px;
    color: #888;
}

/* ══════════════════════════════════════════════════════════════
   SHARED BADGES
   ══════════════════════════════════════════════════════════════ */

.gc-default-badge {
    background: #e0f2fe;
    color: #0369a1;
    border-radius: 3px;
    padding: 1px 5px;
    font-size: 11px;
    margin-left: 4px;
}

.gc-required-badge {
    background: #fee2e2;
    color: #b91c1c;
    border-radius: 3px;
    padding: 1px 5px;
    font-size: 11px;
    margin-left: 4px;
}

/* Divider between inherited and ASIN-specific blocks */
.gc-section-divider {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 11px;
    color: #999;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.gc-section-divider::before,
.gc-section-divider::after {
    content: "";
    flex: 1;
    border-top: 1px dashed #ddd;
}

/* Option chip (read-only inherited options) */
.gc-option-chip {
    background: #ede9fe;
    color: #5b21b6;
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 11px;
}

/* ══════════════════════════════════════════════════════════════
   PACKAGING MODULE CARDS
   ══════════════════════════════════════════════════════════════ */

.pkg-card {
    border: 1.5px solid #f9a8d4;
    border-radius: 10px;
    overflow: hidden;
    background: #fff;
}

.pkg-card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: #fce7f3;
    font-weight: 600;
    font-size: 14px;
    color: #9d174d;
    border-bottom: 1px solid #f9a8d4;
}

.pkg-card-header i {
    color: #db2777;
    font-size: 15px;
}

.pkg-card-body {
    padding: 14px 16px;
}

.pkg-label {
    display: block;
    font-size: 12px;
    color: #555;
    margin-bottom: 3px;
}

/* Upload button */
.pkg-upload-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: #db2777;
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.15s;
}

.pkg-upload-btn:hover {
    background: #be185d;
}

/* Thumbnail box */
.pkg-image-thumb {
    width: 120px;
    height: 120px;
    min-width: 120px;
    border: 2px solid #f9a8d4;
    border-radius: 8px;
    background: #fff0f6;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

/* Component row */
.pkg-component-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 12px;
    border: 1px solid #fce7f3;
    border-radius: 8px;
    background: #fff;
}

.pkg-comp-icon {
    width: 36px;
    height: 36px;
    min-width: 36px;
    background: #fce7f3;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 2px;
}

.pkg-comp-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.pkg-comp-name {
    font-weight: 600;
    font-size: 14px;
    color: #111;
}

.pkg-comp-meta {
    display: flex;
    gap: 8px;
    font-size: 12px;
    color: #888;
}

/* ══════════════════════════════════════════════════════════════
   SHARED BADGES
   ══════════════════════════════════════════════════════════════ */

.gc-default-badge {
    background: #e0f2fe;
    color: #0369a1;
    border-radius: 3px;
    padding: 1px 5px;
    font-size: 11px;
    margin-left: 4px;
}

.gc-required-badge {
    background: #fee2e2;
    color: #b91c1c;
    border-radius: 3px;
    padding: 1px 5px;
    font-size: 11px;
    margin-left: 4px;
}

/* Mobile responsiveness for new design */
@media (max-width: 768px) {
    .bulk-cards-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .bulk-card-upload-slot {
        padding: 15px;
    }

    .bulk-card-preview {
        height: 160px;
    }

    .bulk-card-actions {
        flex-direction: column;
    }

    .results-summary {
        grid-template-columns: 1fr;
        gap: 10px;
    }
}
</style>
