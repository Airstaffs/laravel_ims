<template>
    <div class="vue-container stockroom-module">


        <!-- Scanner Component (with hideButton prop to hide the scanner button) -->
        <scanner-component scanner-title="Stockroom Scanner" storage-prefix="stockroom" :enable-camera="true"
            :display-fields="['Serial', 'FNSKU', 'Location']" :api-endpoint="'/api/stockroom/process-scan'"
            :hide-button="true" @process-scan="handleScanProcess" @hardware-scan="handleHardwareScan"
            @scanner-opened="handleScannerOpened" @scanner-closed="handleScannerClosed"
            @scanner-reset="handleScannerReset" @mode-changed="handleModeChange" ref="scanner">
            <template #input-fields>
                <div class="input-group">
                    <label>Serial Number:</label>
                    <input type="text" v-model="serialNumber" placeholder="Enter Serial Number..."
                        @input="handleSerialInput" @keyup.enter="
                            showManualInput
                                ? focusNextField('fnskuInput')
                                : processScan()
                            " ref="serialNumberInput" />
                </div>

                <div class="input-group">
                    <label>FNSKU:</label>
                    <input type="text" v-model="fnsku" placeholder="Enter FNSKU..." @input="handleFnskuInput"
                        @keyup.enter="
                            showManualInput
                                ? focusNextField('locationInput')
                                : processScan()
                            " ref="fnskuInput" />
                </div>

                <div class="input-group">
                    <label>Location:</label>
                    <input type="text" v-model="locationInput" placeholder="Enter Location..."
                        @input="handleLocationInput" @keyup.enter="processScan()" ref="locationInput" />
                    <div class="container-type-hint">
                        Format: L###X (e.g., L123A) or 'Floor'
                    </div>
                </div>

                <button v-if="showManualInput" @click="processScan()" class="submit-button">
                    Submit
                </button>
            </template>
        </scanner-component>

        <!-- <h2 class="module-title">Stockroom Module</h2> -->
        <div class="d-flex align-items-center justify-content-between flex-wrap mb-4">
            <TitlePage title="Stockroom Module"
                subtitle="View and manage current inventory data, product details, and fulfillment methods for items in stock." />
            <div class="d-flex justify-content-center gap-4 mx-4 flex-wrap desktop-view">
                <Button size="small" severity="secondary" outlined @click="openScannerModal" label="Scan Items"
                    icon="pi pi-barcode" />
                <Button size="small" severity="secondary" outlined @click="loadFBAInboundShipment"
                    label="FBA Inbound Shipment" icon="pi pi-truck" />

                <OverlayBadge v-if="shouldShowBadge" :value="displayCount" severity="danger">
                    <Button :title="`${newScannedCount} new items scanned today (US time)`" size="small"
                        severity="secondary" outlined @click="showNewScannedModal = true" icon="pi pi-barcode"
                        label="New Scanned">
                    </Button>
                </OverlayBadge>

                <Button v-else :title="`${newScannedCount} new items scanned today (US time)`" size="small"
                    severity="secondary" outlined @click="showNewScannedModal = true" icon="pi pi-barcode"
                    label="New Scanned">
                </Button>
                <Button size="small" severity="secondary" outlined @click="openDs7Oos" label="Open DS7 & OO" />
            </div>

            <div class="mobile-view w-100 ms-2">
                <OverlayBadge v-if="shouldShowBadge" class=" w-100" severity="danger">
                    <Button label="More Actions" fluid size="small" severity="secondary" outlined icon="pi pi-list"
                        @click="toggle($event)" aria-haspopup="true" aria-controls="overlay_menu" />
                </OverlayBadge>
                <Button v-else label="More Actions" fluid size="small" severity="secondary" outlined icon="pi pi-list"
                    @click="toggle($event)" aria-haspopup="true" aria-controls="overlay_menu" />
                <Menu ref="menu" id="overlay_menu" :model="menuActions" :popup="true">
                    <template #item="{ item, props }">
                        <a v-ripple class="flex align-items-center" v-bind="props.action">
                            <span :class="item.icon" />
                            <span class="ml-2">{{ item.label }}</span>
                            <Badge v-if="item.badge && shouldShowBadge" :value="item.badge" class="ml-auto"
                                severity="danger" />
                        </a>
                    </template>
                </Menu>
            </div>
        </div>

        <AnimateDiv :delay="200" class="stats-container px-4">
            <div class="stat-card bg-primary-light">
                <div class="stat-icon bg-primary">
                    <i class="pi pi-hashtag text-white"></i>
                </div>
                <div>
                    <p class="mb-0 ">Total Counts</p>
                    <h5 class="mb-0">{{ inventoryCounts?.total || 0 }}</h5>
                </div>
            </div>

            <div class="stat-card bg-success-light">
                <div class="stat-icon bg-success">
                    <i class="pi pi-box text-white"></i>
                </div>
                <div>
                    <p class="mb-0 ">Total QOHs</p>
                    <h5 class="mb-0">{{ inventoryCounts?.qoh || 0 }}</h5>
                </div>
            </div>

            <div class="stat-card bg-warning-light">
                <div class="stat-icon bg-warning">
                    <i class="pi pi-user text-white"></i>
                </div>
                <div>
                    <p class="mb-0 ">Total FBMs</p>
                    <h5 class="mb-0">{{ inventoryCounts?.fbm || 0 }}</h5>
                </div>
            </div>

            <div class="stat-card bg-danger-light">
                <div class="stat-icon bg-danger">
                    <i class="pi pi-warehouse text-white"></i>
                </div>
                <div>
                    <p class="mb-0 ">Total FBAs</p>
                    <h5 class="mb-0">{{ inventoryCounts?.fba || 0 }}</h5>
                </div>
            </div>
        </AnimateDiv>

        <!-- Desktop Table Container -->
        <AnimateDiv :delay="300" class="px-4">
            <div class="search-container">
                <fieldset class="d-flex align-items-center gap-1">
                    <label for="moduleFilter">Store</label>
                    <Select :options="storeOptions" v-model="selectedStore" optionLabel="label" optionValue="value"
                        size="small" class="select-form" @change="changeStore" placeholder="Select a store" />
                </fieldset>
                <fieldset class="d-flex align-items-center gap-1">
                    <label>Fullfilment</label>
                    <Select :options="fullfilmentOptions" v-model="availabilityFilter" optionLabel="label"
                        optionValue="value" size="small" class="select-form" />
                </fieldset>
            </div>
            <XDataTable :value="sortedInventory" :columns="columns" :paginator="false" tableClass="desktop-view"
                :loading="loading" selectionMode="multiple" dataKey="ProductID">
                <template #productName="{ data }">
                    <div class="product-container">
                        <div class="product-image-container clickable">
                            <img :src="data.useDefaultImage
                                ? defaultImagePath
                                : getImagePath(data.ASIN)
                                " :alt="data.AStitle" class="product-thumbnail" @error="
                                    handleImageError($event, data)
                                    " />
                        </div>
                        <div style="word-break: break-word; white-space: normal; overflow-wrap: break-word; flex: 1;">
                            <p class="fw-bold">
                                 {{ data.display_title || data.system_title || data.AStitle }}
                            </p>
                        </div>
                    </div>
                </template>
                <template #fnskus="{ data }">
                    <div>
                        <div class="fnsku-selector" v-if="data.fnskus && data.fnskus.length > 0">
                            <select class="fnsku-select">
                                <option v-for="fnsku in data.fnskus" :key="fnsku.FNSKU || fnsku"
                                    :value="fnsku.FNSKU || fnsku">
                                    {{ fnsku.FNSKU || fnsku }}
                                </option>
                            </select>
                            <span class="fnsku-count">({{ data.fnskus.length }})</span>
                        </div>
                        <div v-else>-</div>
                    </div>
                </template>
                <template #quantity="{ data }">
                    <div :class="{
                        'item-count-cell': true,
                        'item-count-warning': !data.countValid,
                    }">
                        {{ data.item_count }}
                        <i v-if="!data.countValid" class="fas fa-exclamation-circle"
                            title="Item count doesn't match serial numbers"></i>
                    </div>
                </template>
                <template #actions="{ data }">
                    <div class="d-flex flex-column align-items-start">
                        <Button label="Print" icon="pi pi-print" size="small" severity="contrast" variant="text"
                            class="text-success" @click="printLabel(data.ProductID)" />
                        <Button label="More Details" icon="pi pi-info-circle" size="small" severity="contrast"
                            variant="text" class="text-primary" @click="viewProductDetails(data)" />
                        <Button label="Process" icon="pi pi-cog" size="small" severity="contrast" variant="text"
                            class="text-warning" @click="openProcessModal(data)" />
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
                <div class="mobile-card" v-else v-for="(item, index) in sortedInventory" :key="item.ASIN">
                    <div class="mobile-card-header">
                        <div class="mobile-checkbox">
                            <input type="checkbox" v-model="item.checked" />
                        </div>
                        <div class="mobile-product-image clickable" @click="viewProductImage(item)">
                            <img :src="item.useDefaultImage
                                ? defaultImagePath
                                : getImagePath(item.ASIN)
                                " :alt="item.AStitle" class="product-thumbnail-mobile"
                                @error="handleImageError($event, item)" />
                        </div>
                        <div class="mobile-product-info">
                            <h6 class="mobile-product-name clickable" @click="viewProductDetails(item)">
                               {{ item.display_title || item.system_title || item.AStitle }}
                            </h6>
                        </div>
                    </div>

                    <hr />

                    <div class="mobile-card-details">
                        <div>
                            <span class="mobile-detail-label">ASIN: </span>
                            <span class="mobile-detail-value">{{
                                item.ASIN
                                }}</span>
                        </div>
                        <div>
                            <span class="mobile-detail-label">Store: </span>
                            <span class="mobile-detail-value">{{
                                item.storename
                                }}</span>
                        </div>


                        <div>
                            <span class="mobile-detail-label">Quantity Inside: </span>
                            <span :class="{
                                'mobile-detail-value': true,
                                'item-count-warning': !item.countValid,
                            }">
                                <!-- UPDATED: Use the new display logic -->
                                <template v-if="item.quantity_inside > 1">
                                    {{ item.unit_count }} units ({{ item.item_count }} qty)
                                </template>
                                <template v-else>
                                    {{ item.item_count }}
                                </template>
                                <i v-if="!item.countValid" class="fas fa-exclamation-circle"
                                    title="Unit count doesn't match serial numbers"></i>
                            </span>
                        </div>


                        <div>
                            <span class="mobile-detail-label">FBM/FBA: </span>
                            <span class="mobile-detail-value">{{ item.FBMAvailable }} /
                                {{ item.FbaAvailable }}</span>
                        </div>
                        <div>
                            <span class="mobile-detail-label">FNSKUs: </span>
                            <span class="mobile-detail-value">{{
                                item.fnskus ? item.fnskus.length : 0
                                }}</span>
                        </div>
                    </div>

                    <hr />

                    <div class="d-flex flex-nowrap overflow-auto gap-2 pb-3">
                        <Button @click="printLabel(item.ProductID)" icon="pi pi-print" label="Print"
                            class="flex-shrink-0" size="small" />
                        <Button @click="viewProductDetails(item)" icon="pi pi-info-circle" label="More Details"
                            severity="info" class="flex-shrink-0" size="small" />
                        <Button @click="openProcessModal(item)" icon="pi pi-cog" label="Process" class="flex-shrink-0"
                            size="small" severity="warn" />
                    </div>

                    <hr v-if="expandedRows[index]" />

                    <div v-if="expandedRows[index]" class="mobile-expanded-content">
                        <div class="mobile-section">
                            <h4>Serial Numbers:</h4>
                            <div class="mobile-serial-list">
                                <div v-for="serial in item.serials" :key="serial.ProductID" class="mobile-serial-item">
                                    <div class="mobile-serial-detail">
                                        <span class="mobile-serial-label">Store:</span>
                                        <span class="mobile-serial-value">{{ serial.storename }}</span>
                                    </div>
                                    <div class="mobile-serial-detail">
                                        <span class="mobile-serial-label">RT#:</span>
                                        <span class="mobile-serial-value">{{
                                            formatRTNumber(
                                                serial.rtcounter,
                                                serial.storename
                                            )
                                        }}</span>
                                    </div>
                                    <div class="mobile-serial-detail">
                                        <span class="mobile-serial-label">Serial:</span>
                                        <span class="mobile-serial-value">{{
                                            serial.serialnumber
                                            }}</span>
                                    </div>
                                    <div class="mobile-serial-detail">
                                        <span class="mobile-serial-label">Location:</span>
                                        <span class="mobile-serial-value">{{
                                            serial.warehouselocation
                                            }}</span>
                                    </div>
                                    <div class="mobile-serial-detail">
                                        <span class="mobile-serial-label">FNSKU:</span>
                                        <span class="mobile-serial-value">{{
                                            serial.FNSKUviewer
                                            }}</span>
                                    </div>
                                    <div class="mobile-serial-detail">
                                        <span class="mobile-serial-label">MSKU:</span>
                                        <span class="mobile-serial-value">{{
                                            serial.MSKU
                                            }}</span>
                                    </div>
                                    <div class="mobile-serial-detail">
                                        <span class="mobile-serial-label">Grading:</span>
                                        <span class="mobile-serial-value">{{
                                            serial.display_grading ||
                                            getDisplayGrading(
                                                serial,
                                                serial.storename
                                            )
                                        }}</span>
                                    </div>
                                </div>
                                <div v-if="
                                    !item.serials ||
                                    item.serials.length === 0
                                " class="mobile-empty">
                                    No serial numbers found
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="pagination-container">
            <div class="pagination-wrapper">
                <div class="per-page-selector">
                    <span>Rows per page</span>
                    <Select v-model="perPage" @change="changePerPage" :options="rowsPerPage" size="small"
                        optionLabel="label" optionValue="value" />
                    <!-- <select v-model="perPage" @change="changePerPage" class="per-page-select">
                        <option v-for="option in [10, 15, 20, 50, 100]" :key="option" :value="option">
                            {{ option }}
                        </option>
                    </select> -->
                </div>

                <div class="pagination">
                    <Button @click="prevPage" :disabled="currentPage === 1" class="pagination-button" label="Back"
                        icon="pi pi-angle-left" size="small" severity="info" />
                    <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
                    <Button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button"
                        label="Next" icon="pi pi-angle-right" size="small" severity="info" iconPos="right" />
                </div>
            </div>
        </div>

        <!-- Process Items Modal (Replaces Move Items Modal) -->
        <Dialog v-model:visible="showProcessModal" modal header="Process Items" :pt="{
            root: { class: 'mobile-fullscreen-dialog' }
        }" :style="{ width: '95%' }">
            <div class="process-form">
                <div class="form-group">
                    <label>Shipment Type:</label>
                    <Select v-model="processShipmentType" :options="processShipmentTypeOptions" optionLabel="label"
                        optionValue="value" fluid size="small" />
                </div>
                <div class="form-group">
                    <label>Tracking Number:</label>
                    <InputText type="text" v-model="processTrackingNumber" placeholder="Enter tracking number..." fluid
                        size="small" />
                </div>
                <div class="form-group">
                    <label>Notes (optional):</label>
                    <Textarea v-model="processNotes" placeholder="Add notes about this process..." size="small" />
                </div>
                <div class="form-group" v-if="singleItemSelected">
                    <label>New Location (optional):</label>
                    <InputText type="text" v-model="processLocation" placeholder="e.g., L123A or Floor" size="small" />
                </div>
            </div>
            <div class="process-item-list">
                <h3>Items to Process</h3>
                <div class="process-item-selector">
                    <label class="select-all-checkbox">
                        <input type="checkbox" v-model="selectAllItems" @change="toggleAllItems" />
                        <span>Select All</span>
                    </label>
                    <div class="process-items-container">
                        <div v-for="serial in currentProcessItem.serials" :key="serial.ProductID"
                            class="process-item-row">
                            <label class="process-item-checkbox">
                                <input type="checkbox" v-model="selectedItems" :value="serial.ProductID" />
                                <span>[{{ serial.storename }}] {{
                                    formatRTNumber(
                                        serial.rtcounter,
                                        serial.storename
                                    )
                                }} - {{ serial.serialnumber }} - {{ serial.FNSKUviewer }} - {{
                                        serial.display_grading ||
                                        getDisplayGrading(
                                            serial,
                                            serial.storename
                                        )
                                    }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-nowrap overflow-auto gap-2 pb-3 mt-4">
                <div class="flex-shrink-0"><Button @click="printSelectedItems" :disabled="!hasSelectedItems"
                        label="Print Selected" icon="pi pi-print" size="small" severity="info" />
                </div>
                <div class="flex-shrink-0"><Button @click="updateSelectedLocation" :disabled="!hasSelectedItems"
                        label="Update Location" icon="pi pi-map-marker" size="small" severity="warn" /></div>
                <div class="flex-shrink-0"> <Button @click="mergeSelectedItems" :disabled="selectedItems.length < 2"
                        label="Merge Items" icon="pi pi-arrow-down-left-and-arrow-up-right-to-center" size="small"
                        severity="info" /></div>
                <div class="flex-shrink-0"> <Button @click="submitProcess" :disabled="!isProcessFormValid"
                        label="Submit Process" icon="pi pi-check" size="small" severity="help" /></div>
                <div class="flex-shrink-0"> <Button @click="openPostAmazonModal" :disabled="!hasSelectedItems"
                        label="Post to Amazon" icon="pi pi-check" size="small" severity="secondary" />
                </div>
            </div>
        </Dialog>

        <!-- Product Details Modal -->
        <Dialog v-model:visible="showProductDetailsModal" modal :style="{ width: '95%' }" header="Product Details" :pt="{
            root: { class: 'mobile-fullscreen-dialog' }
        }">
            <div class="product-details-layout">
                <div class="product-details-left">
                    <div class="product-details-image clickable" @click="enlargeImage = !enlargeImage">
                        <img :src="selectedProduct.useDefaultImage
                            ? defaultImagePath
                            : getImagePath(selectedProduct.ASIN)
                            " :alt="selectedProduct.AStitle" :class="[
                                'product-details-thumbnail',
                                enlargeImage ? 'enlarged' : '',
                            ]" @error="
                                handleImageError(
                                    $event,
                                    selectedProduct
                                )
                                " />
                    </div>
                    <div class="product-details-info">
                        <h3 class="product-details-title">
                            {{ selectedProduct.display_title || selectedProduct.system_title || selectedProduct.AStitle }}
                        </h3>
                    </div>
                    <div :style="{ fontSize: '14px' }">
                        <div class="product-details-row">
                            <span class="product-details-label">ASIN: </span>
                            <span class="product-details-value"> {{
                                selectedProduct.ASIN
                            }}</span>
                        </div>
                        <div class="product-details-row">
                            <span class="product-details-label">FBM: </span>
                            <span class="product-details-value"> {{
                                selectedProduct.FBMAvailable
                            }}</span>
                        </div>
                        <div class="product-details-row">
                            <span class="product-details-label">FBA: </span>
                            <span class="product-details-value"> {{
                                selectedProduct.FbaAvailable
                            }}</span>
                        </div>
                        <div class="product-details-row">
                            <span class="product-details-label">Outbound: </span>
                            <span class="product-details-value"> {{
                                selectedProduct.Outbound
                            }}</span>
                        </div>
                        <div class="product-details-row">
                            <span class="product-details-label">Inbound: </span>
                            <span class="product-details-value"> {{
                                selectedProduct.Inbound
                            }}</span>
                        </div>
                        <div class="product-details-row">
                            <span class="product-details-label">Unfulfillable: </span>
                            <span class="product-details-value"> {{
                                selectedProduct.Unfulfillable
                            }}</span>
                        </div>
                        <div class="product-details-row">
                            <span class="product-details-label">Reserved: </span>
                            <span class="product-details-value"> {{
                                selectedProduct.Reserved
                            }}</span>
                        </div>
                        <div class="product-details-row">
                            <span class="product-details-label">Store: </span>
                            <span class="product-details-value"> {{
                                selectedProduct.storename
                            }}</span>
                        </div>

                        <div class="product-details-row">
                            <span class="product-details-label">Quantity Inside: </span>
                            <span :class="{
                                'product-details-value': true,
                                'item-count-warning': !selectedProduct.countValid,
                            }">
                                <!-- UPDATED: Use the new display logic -->
                                <template v-if="selectedProduct.quantity_inside > 1">
                                    {{ selectedProduct.unit_count }} units ({{ selectedProduct.item_count }} qty)
                                </template>
                                <template v-else>
                                    {{ selectedProduct.item_count }}
                                </template>
                                <i v-if="!selectedProduct.countValid" class="fas fa-exclamation-circle"
                                    title="Unit count doesn't match serial numbers"></i>
                            </span>
                        </div>
                                                
                    </div>
                </div>
                <div class="product-details-right">
                    <Card>
                        <template #title>
                            <h6>FNSKUs</h6>
                            <Divider />
                        </template>
                        <template #content>
                            <div>
                                <div class="product-details-fnskus">
                                    <div v-for="fnsku in selectedProduct.fnskus" :key="fnsku.FNSKU"
                                        class="w-100 product-details-fnsku-item">
                                        <div class="fnsku-main">
                                            {{ fnsku.FNSKU || fnsku }}
                                        </div>
                                        <div class="fnsku-details">
                                            <span class="fnsku-detail">Store:
                                                {{
                                                    fnsku.storename || "-"
                                                }}</span>
                                            <span class="fnsku-detail">MSKU:
                                                {{
                                                    fnsku.MSKU || "-"
                                                }}</span>
                                            <span class="fnsku-detail">Grade:
                                                {{
                                                    fnsku.display_grading ||
                                                    getDisplayGrading(
                                                        fnsku,
                                                        fnsku.storename
                                                    )
                                                }}</span>
                                        </div>
                                    </div>
                                    <div v-if="
                                        !selectedProduct.fnskus ||
                                        selectedProduct.fnskus
                                            .length === 0
                                    " class="product-details-empty">
                                        No FNSKUs found
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Card>

                    <div class="mt-5 pb-4">
                        <h4>Serial Numbers and Locations</h4>
                        <XDataTable :value="selectedProduct.serials" :columns="serial_columns" :pagination="false"
                            tableClass="mt-4" scrollable scrollHeight="15rem">
                            <template #rtcounter="{ data }">
                                <p>{{ formatRTNumber(
                                    data.rtcounter,
                                    data.storename
                                ) }}</p>
                            </template>
                            <template #grading="{ data }">
                                <p>
                                    {{
                                        data.display_grading ||
                                        getDisplayGrading(
                                            data,
                                            data.storename
                                        )
                                    }}
                                </p>
                            </template>
                        </XDataTable>
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="d-flex gap-2">
                    <Button label="Print" icon="pi pi-print" size="small" severity="success"
                        @click="printLabel(selectedProduct.ProductID)" />
                    <Button label="Process" icon="pi pi-cog" size="small" severity="warn"
                        @click="openProcessModal(selectedProduct)" />
                </div>
            </template>
        </Dialog>

        <!-- Post to Amazon Modal -->
        <div v-if="showPostAmazonModal" class="modal postAmazon-modal">
            <div class="modal-overlay" @click="closePostAmazonModal"></div>

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Post to Amazon</h5>
                    <button class="postAmazon-close" @click="closePostAmazonModal">
                        &times;
                    </button>
                </div>

                <div class="modal-body" v-if="!isPosting">
                    <form @submit.prevent="submitPostToAmazon">
                        <div class="form-group">
                            <label>Marketplace</label>
                            <input v-model="postForm.marketplace" class="form-control" required />
                        </div>
                        <select v-model="postForm.fulfillmentChannel" class="form-control" required>
                            <option disabled value="">
                                Select Fulfillment Channel
                            </option>
                            <option value="AMAZON_NA">FBA</option>
                            <option value="DEFAULT">FBM</option>
                        </select>
                        <div class="form-group">
                            <label>Currency</label>
                            <input v-model="postForm.currency" class="form-control" required />
                        </div>
                        <div class="form-group">
                            <label>Price</label>
                            <input type="number" v-model="postForm.price" class="form-control" required />
                        </div>
                    </form>
                </div>
                <div class="modal-body" v-else>
                    <p>Processing... Please wait.</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" type="submit">
                        Submit
                    </button>
                </div>
            </div>
        </div>

        <!-- New Scanned Items Modal -->
        <NewScannedItemModal ref="newScannedModal" :show="showNewScannedModal" @close="closeNewScannedModal"
            @update-count="handleCountUpdate" />

        <!-- DS7oos Modal -->
        <ds7-oos-modal :show="showDs7Oos" :store-options="distinctStores" :initial="dsFilters"
            @close="showDs7Oos = false" @save="applyDsFilters" />
        <ScrollTop />
    </div>
</template>

<script>
import Stockroom from "./stockroom.js";
import XDataTable from '../../components/DataTable/XDataTable.vue'
import { Badge, Button, Card, Dialog, Divider, Drawer, InputText, Menu, OverlayBadge, ScrollTop, Select, Textarea } from "primevue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";
import { ROWS_PER_PAGE } from "../../constant.js";

const TABLE_COLUMNS = [
    {
        field: "AStitle",
        header: "Product Name",
        slot: "productName",
        sortable: true,
        style: { maxWidth: "20rem" },
    },
    {
        field: "ASIN",
        header: "ASIN",
        sortable: true,
        bodyStyle: "font-size: 14px",
    },
    {
        field: "fnskus",
        header: "FNSKUs",
        slot: "fnskus",
        sortable: true,
        bodyStyle: "font-size: 14px",
    },
    {
        field: "FBMAvailable",
        header: "FBM",
        sortable: true,
        bodyStyle: "font-size: 14px",
    },
    {
        field: "FbaAvailable",
        header: "FBA",
        sortable: true,
        bodyStyle: "font-size: 14px",
    },
    {
        field: "item_count",
        header: "Quantity Inside",
        slot: "quantity",
        sortable: true,
        bodyStyle: "font-size: 14px",
    },
]

const SERIAL_TABLE_COLUMNS = [
    {
        field: "rtcounter",
        header: "TR#",
        slot: "rtcounter",
        headerStyle: "backgroundColor: #0C81FF; color: #fff",
        bodyStyle: "fontSize: 14px"
    },
    {
        field: "storename",
        header: "Store",
        headerStyle: "backgroundColor: #0C81FF; color: #fff",
        bodyStyle: "fontSize: 14px"
    },
    {
        field: "serialnumber",
        header: "Serial Number",
        headerStyle: "backgroundColor: #0C81FF; color: #fff",
        bodyStyle: "fontSize: 14px"
    },
    {
        field: "warehouselocation",
        header: "Location",
        headerStyle: "backgroundColor: #0C81FF; color: #fff",
        bodyStyle: "fontSize: 14px"
    },
    {
        field: "FNSKUviewer",
        header: "FNSKU",
        headerStyle: "backgroundColor: #0C81FF; color: #fff",
        bodyStyle: "fontSize: 14px"
    },
    {
        field: "MSKU",
        header: "MSKU",
        headerStyle: "backgroundColor: #0C81FF; color: #fff",
        bodyStyle: "fontSize: 14px"
    },
    {
        field: "display_grading",
        header: "Grading",
        slot: "grading",
        headerStyle: "backgroundColor: #0C81FF; color: #fff",
        bodyStyle: "fontSize: 14px",
    },
]
export default {
    mixins: [Stockroom],
    components: {
        XDataTable,
        Drawer,
        Button,
        Dialog,
        Card,
        Divider,
        Select,
        InputText,
        Textarea,
        ScrollTop,
        TitlePage,
        OverlayBadge,
        Menu,
        OverlayBadge,
        Badge,
        AnimateDiv
    },
    data() {
        return {
            columns: TABLE_COLUMNS,
            serial_columns: SERIAL_TABLE_COLUMNS,
            fullfilmentOptions: [
                { value: "all", label: "All Items" },
                { value: "fbm", label: "FBM Only" },
                { value: "fba", label: "FBA Only" },
                { value: "both", label: "Both FBM & FBA" },
                { value: "none", label: "No Availability" }
            ],
            menuActions: [],
            rowsPerPage: ROWS_PER_PAGE
        }
    },
    methods: {
        toggle(event) {
            this.menuActions = this.getMoreActionItems(this.currentActionItem);
            if (this.$refs.menu) {
                this.$refs.menu.toggle(event);
            }
        },
        getMoreActionItems() {
            return [
                {
                    label: 'Scan Items',
                    icon: 'pi pi-barcode',
                    command: () => this.openScannerModal(),
                },
                {
                    label: 'FBA Inbound Shipment',
                    icon: 'pi pi-truck',
                    command: () => this.loadFBAInboundShipment(),
                },
                {
                    label: 'New Scanned',
                    icon: 'pi pi-barcode',
                    badge: this.displayCount,
                    badgeClass: "p-badge-danger",
                    command: () => this.showNewScannedModal = true,
                },
                {
                    label: 'Open DS7 & OO',
                    icon: 'pi pi-file',
                    command: () => this.openDs7Oos(),
                }
            ];
        },
    },
    computed: {
        processShipmentTypeOptions() {
            return [{ label: "For Dispense", value: "For Dispense" }, { label: "For Replacement", value: "For Replacement" }]
        },
        storeOptions() {
            const options = this.stores.map(store => ({ label: store, value: store }))

            return [{ value: '', label: 'All Stores' }, ...options]
        }
    }
}

</script>

<style scoped>
.p-badge-danger {
    background-color: #ef4444;
    /* or #dc3545 depending on theme */
    color: #ffffff;
}

.inventory-counts-section {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 20px;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    margin: 20px 0;
    font-family: Arial, sans-serif;
    flex-wrap: wrap;
    justify-content: flex-start;
    /* Align badges to the left on desktop */
}

.count-badge {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    white-space: nowrap;
}

.count-badge.total-count {
    background-color: #17a2b8;
    color: white;
    border: 1px solid #117a8b;
}

.count-badge.qoh-count {
    background-color: #6f42c1;
    color: white;
    border: 1px solid #5a369a;
}

.count-badge.fbm-count {
    background-color: #28a745;
    color: white;
    border: 1px solid #1e7e34;
}

.count-badge.fba-count {
    background-color: #fd7e14;
    color: white;
    border: 1px solid #e55a00;
}

.count-badge:not(.total-count):not(.qoh-count):not(.fbm-count):not(.fba-count) {
    background-color: #6c757d;
    color: white;
    border: 1px solid #545b62;
}

.count-label {
    font-weight: 600;
    margin-right: 2px;
}

.count-value {
    font-weight: 700;
    font-size: 14px;
}

.count-separator {
    width: 1px;
    height: 24px;
    background-color: #dee2e6;
    margin: 0 5px;
}

/* FNSKU Tag Styles - Simplified Layout */
.fnsku-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}

.fnsku-tag {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 8px 12px;
    min-width: 200px;
}

.fnsku-main {
    font-weight: 600;
    color: #007bff;
    font-size: 14px;
    margin-bottom: 4px;
}

.fnsku-sub {
    font-size: 12px;
    color: #6c757d;
}

/* Product Details FNSKU Items */
.product-details-fnsku-item {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 10px;
    margin-bottom: 8px;
}

.serial-detail-table thead {
    background-color: #1a252f !important;
    /* Much darker header - almost black */
    color: white !important;
}

.serial-detail-table thead th {
    background-color: #1a252f !important;
    color: white !important;
    padding: 16px 12px !important;
    text-align: left !important;
    font-weight: 800 !important;
    font-size: 14px !important;
    border-right: 1px solid rgba(255, 255, 255, 0.4) !important;
    white-space: nowrap !important;
    text-transform: uppercase !important;
    letter-spacing: 1px !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
}

/* Product Details Modal Table Styles with much darker header - ONLY for More Details */
.product-details-table thead {
    background-color: #212529 !important;
    /* Much darker header - almost black */
    color: white !important;
}

.product-details-table thead th {
    background-color: #212529 !important;
    color: white !important;
    padding: 14px 12px !important;
    text-align: left !important;
    font-weight: 800 !important;
    font-size: 13px !important;
    text-transform: uppercase !important;
    letter-spacing: 1px !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
}

.product-details-fnsku-item .fnsku-main {
    font-weight: 600;
    color: #007bff;
    font-size: 14px;
    margin-bottom: 4px;
}

.fnsku-details {
    font-size: 12px;
    color: #6c757d;
}

.fnsku-detail {
    margin-right: 15px;
}

/* Mobile FNSKU Styles - Simplified */
.mobile-fnsku-item {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 10px;
    margin-bottom: 8px;
}

.mobile-fnsku-item .fnsku-main {
    font-weight: 600;
    color: #007bff;
    font-size: 14px;
    margin-bottom: 4px;
}

.mobile-fnsku-item .fnsku-details {
    font-size: 12px;
    color: #6c757d;
}

/* Enhanced Mobile Serial Detail Styles */
.mobile-serial-item {
    background-color: #ffffff;
    border: 1px solid #007bff;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 12px;
    box-shadow: 0 2px 4px rgba(0, 123, 255, 0.1);
}

.mobile-serial-detail {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 0;
    border-bottom: 1px solid #e3f2fd;
}

.mobile-serial-detail:last-child {
    border-bottom: none;
}

.mobile-serial-label {
    font-weight: 600;
    color: #007bff;
    font-size: 13px;
    min-width: 70px;
}

.mobile-serial-value {
    color: #495057;
    font-size: 13px;
    text-align: right;
    flex: 1;
    margin-left: 10px;
    font-weight: 500;
}

/* Product Details Modal - Make it even wider */
.product-details-modal {
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

.product-details-content {
    background-color: white;
    border-radius: 12px;
    width: 98%;
    max-width: 1600px;
    /* Increased even more */
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.product-details-header {
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #f8f9fa;
    border-radius: 12px 12px 0 0;
}

.product-details-body {
    padding: 20px;
}

/* Product Details Layout Fixes */
.product-details-layout {
    display: flex;
    gap: 40px;
    /* Increased gap even more */
    max-width: 100%;
}

.product-details-left {
    flex: 0 0 400px;
    /* Increased width even more */
    max-width: 400px;
}

.product-details-right {
    flex: 1;
    min-width: 0;
    /* Allow shrinking */
}

.product-details-fnskus-section {
    margin-top: 15px;
}

.product-details-fnskus-section h4 {
    margin-bottom: 8px;
    color: #495057;
    font-size: 14px;
}

/* Expanded row table - Make it 90% width */
.expanded-row {
    background-color: #f8f9fa;
}

.expanded-content {
    padding: 20px;
    width: 90%;
    /* Make expanded content 90% width */
    margin: 0 auto;
    /* Center it */
}

.serial-table-container {
    width: 100%;
    overflow-x: auto;
    margin-top: 10px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Enhanced Serial Detail Table with much darker header */
.serial-detail-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    background-color: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    min-width: 900px;
    /* Increased minimum width */
}

.serial-detail-table thead {
    background-color: #1a252f;
    /* Much darker header - almost black */
    color: white;
}

.serial-detail-table thead th {
    padding: 16px 12px;
    /* Even more padding */
    text-align: left;
    font-weight: 800;
    /* Extra bold font */
    font-size: 14px;
    /* Larger font size */
    border-right: 1px solid rgba(255, 255, 255, 0.4);
    white-space: nowrap;
    text-transform: uppercase;
    /* Make headers uppercase */
    letter-spacing: 1px;
    /* More letter spacing */
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
    /* Add text shadow for better contrast */
}

.serial-detail-table thead th:last-child {
    border-right: none;
}

.serial-detail-table tbody td {
    padding: 12px 10px;
    /* Increased padding even more */
    border-bottom: 1px solid #dee2e6;
    border-right: 1px solid #dee2e6;
    color: #495057;
    vertical-align: middle;
    font-size: 13px;
}

.serial-detail-table tbody td:last-child {
    border-right: none;
}

.serial-detail-table tbody tr:nth-child(even) {
    background-color: #f8f9fa;
}

.serial-detail-table tbody tr:hover {
    background-color: #e3f2fd;
}

/* Product Details Modal Table Styles with much darker header */
.product-details-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    /* Increased font size */
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.product-details-table thead {
    background-color: #212529;
    /* Much darker header - almost black */
    color: white;
}

.product-details-table thead th {
    padding: 14px 12px;
    /* Increased padding */
    text-align: left;
    font-weight: 800;
    /* Extra bold font */
    font-size: 13px;
    text-transform: uppercase;
    /* Make headers uppercase */
    letter-spacing: 1px;
    /* More letter spacing */
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
    /* Add text shadow for better contrast */
}

.product-details-table tbody td {
    padding: 10px;
    /* Increased padding */
    border-bottom: 1px solid #dee2e6;
    color: #495057;
    font-size: 12px;
}

.product-details-table tbody tr:nth-child(even) {
    background-color: #f8f9fa;
}

.product-details-table tbody tr:hover {
    background-color: #e9ecef;
}

/* Expanded content headers */
.expanded-content strong {
    color: #1a252f !important;
    font-weight: 700 !important;
    font-size: 16px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
}

/* Mobile section headers */
.mobile-section h4 {
    background-color: #1a252f !important;
    color: white !important;
    padding: 10px 15px !important;
    margin: 0 0 10px 0 !important;
    border-radius: 6px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
}

/* Expanded Content Section Spacing */
.expanded-fnskus,
.expanded-serials {
    margin-bottom: 20px;
}

.expanded-fnskus strong,
.expanded-serials strong {
    color: #495057;
    font-size: 15px;
    display: block;
    margin-bottom: 10px;
}

/* Process Modal Item Display Enhancement */
.process-item-row span {
    font-size: 14px;
    color: #495057;
}

/* Mobile responsive adjustments for counts - FIXED VERSION */
@media (max-width: 768px) {
    .inventory-counts-section {
        display: flex;
        flex-direction: row;
        /* Force horizontal layout */
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 12px 15px;
        margin: 15px 0;
        flex-wrap: nowrap;
        /* Prevent wrapping to new lines */
    }

    .count-badge {
        flex: 1;
        /* Equal width distribution */
        display: flex;
        flex-direction: column;
        /* Stack label and value vertically within badge */
        align-items: center;
        justify-content: center;
        padding: 8px 4px;
        font-size: 11px;
        min-width: 0;
        border-radius: 4px;
        text-align: center;
    }

    .count-label {
        font-size: 10px;
        font-weight: 600;
        margin-bottom: 2px;
        margin-right: 0;
        line-height: 1;
    }

    .count-value {
        font-size: 16px;
        font-weight: 700;
        line-height: 1;
    }

    .count-separator {
        display: none;
    }

    .fnsku-detail-table-container,
    .serial-table-container {
        font-size: 12px;
    }

    .fnsku-detail-table th,
    .fnsku-detail-table td,
    .serial-detail-table th,
    .serial-detail-table td {
        padding: 8px 6px;
    }

    .mobile-fnsku-item,
    .mobile-serial-item {
        margin-bottom: 8px;
        padding: 10px;
    }

    .product-details-content {
        width: 100%;
        height: 100vh;
        border-radius: 0;
        max-height: 100vh;
    }

    .serial-detail-table {
        min-width: 700px;
    }

    .serial-detail-table thead th,
    .serial-detail-table tbody td {
        padding: 10px 8px;
        font-size: 12px;
    }

    .expanded-content {
        width: 100%;
        padding: 15px;
    }
}

@media (max-width: 480px) {
    .inventory-counts-section {
        gap: 6px;
        padding: 10px 12px;
    }

    .count-badge {
        padding: 6px 3px;
    }

    .count-label {
        font-size: 9px;
    }

    .count-value {
        font-size: 14px;
    }
}

/* Responsive adjustments */
@media (max-width: 1400px) {
    .product-details-content {
        width: 99%;
        max-width: 1400px;
    }

    .product-details-layout {
        gap: 30px;
    }

    .product-details-left {
        flex: 0 0 350px;
        max-width: 350px;
    }
}

@media (max-width: 1200px) {
    .product-details-content {
        width: 98%;
        max-width: 1200px;
    }

    .product-details-layout {
        gap: 20px;
    }

    .product-details-left {
        flex: 0 0 300px;
        max-width: 300px;
    }
}

@media (max-width: 992px) {
    .product-details-layout {
        flex-direction: column;
    }

    .product-details-left {
        flex: none;
        max-width: 100%;
    }

    .product-details-right {
        flex: none;
    }

    .expanded-content {
        width: 95%;
    }
}

/* Ensure parent containers don't clip the badge */
.top-header {
    overflow: visible !important;
    position: relative !important;
    z-index: 100 !important;
}

.btn-new-scanned {
    position: relative;
    background-color: #28a745 !important;
    border-color: #28a745 !important;
    overflow: visible;
    /* Allow badge to show outside button */
}

.btn-new-scanned:hover {
    background-color: #218838 !important;
    border-color: #1e7e34 !important;
}

.header-buttons .btn {
    position: relative !important;
    overflow: visible !important;
}

/* Force badge to show */
.notification-badge {
    position: absolute !important;
    top: -8px !important;
    right: -8px !important;
    background-color: #ff0000 !important;
    color: white !important;
    border-radius: 50% !important;
    min-width: 22px !important;
    height: 22px !important;
    font-size: 11px !important;
    font-weight: bold !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    border: 2px solid white !important;
    box-shadow: 0 2px 8px rgba(255, 0, 0, 0.6) !important;
    z-index: 999 !important;
    animation: pulse-red 2s infinite !important;
    padding: 0 4px !important;
    line-height: 1 !important;
}

/* Updated pulse animation with bright red color */
@keyframes pulse-red {
    0% {
        transform: scale(1);
        box-shadow: 0 2px 8px rgba(255, 0, 0, 0.6);
    }

    50% {
        transform: scale(1.1);
        box-shadow: 0 4px 12px rgba(255, 0, 0, 0.8);
    }

    100% {
        transform: scale(1);
        box-shadow: 0 2px 8px rgba(255, 0, 0, 0.6);
    }
}

/* For larger numbers (10+), make badge slightly wider */
.notification-badge.large-number {
    min-width: 26px;
    height: 22px;
    font-size: 10px;
    border-radius: 11px;
    /* More oval for larger numbers */
}

/* Additional styles for very large numbers (100+) */
.notification-badge.extra-large {
    min-width: 30px;
    height: 22px;
    font-size: 9px;
    border-radius: 11px;
}

.stats-container {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    border: 1px solid #dee2e6;
    padding: 0.75rem;
    border-radius: 0.75rem;
    flex: 0 0 14%;
}

/* Icon */
.stat-icon {
    border-radius: 50%;
    padding: 1rem;
    display: flex;
    justify-content: center;
    align-items: center;
}

/* Light backgrounds */
.bg-primary-light {
    background: rgba(13, 110, 253, 0.1);
}

.bg-success-light {
    background: rgba(25, 135, 84, 0.1);
}

.bg-warning-light {
    background: rgba(255, 193, 7, 0.15);
}

.bg-danger-light {
    background: rgba(220, 53, 69, 0.12);
}

.search-container {
    margin: 20px 0;
    display: flex;
    align-items: center;
    gap: 20px;
}

.select-form {
    width: 200px;
}

@media (max-width: 992px) {
    .stat-card {
        flex: 0 0 14%;
    }
}

@media (max-width: 768px) {
    .stats-container {
        gap: 0.5rem;
    }

    .stat-card {
        flex: 1 1 48%;
        min-width: 0;
        flex-direction: row;
        align-items: flex-start;
    }

    .stat-icon {
        display: none;
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
</style>
