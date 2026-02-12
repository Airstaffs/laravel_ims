<template>
    <div class="vue-container fbm-order-module">
        <!-- Top header bar with blue background -->
        <!-- <div class="top-header">
            <div class="header-buttons">
                <button class="btn btn-header" @click="openWorkHistoryModal">
                    <i class="fas fa-chart-line"></i>
                    <span>Work History</span>
                </button>
                <button class="btn btn-header" v-if="persistentSelectedOrderIds.length > 0"
                    @click="PurchaseShippingLabel">
                    <i class="fas fa-shipping-fast"></i>
                    <span>Purchase Shipping Label</span>
                </button>
                <button class="btn btn-header" @click="processSelectedOrders">
                    <i class="fas fa-shipping-fast"></i>
                    <span>Process Selected</span>
                </button>
                <button class="btn btn-header" @click="printShippingLabels">
                    <i class="fas fa-tag"></i>
                    <span>Print Labels</span>
                </button>
                <button class="btn btn-header" @click="generatePackingSlips">
                    <i class="fas fa-file-alt"></i>
                    <span>Generate Packing Slips</span>
                </button>
                <button class="btn btn-warning" @click="openManualShipmentLabelModal">
                    <span>Manual Shipment Label</span>
                </button>
            </div>

            <div class="store-filter">
                <label for="store-select">Store:</label>
                <select id="store-select" v-model="selectedStore" @change="changeStore" class="store-select">
                    <option value="">All Stores</option>
                    <option v-for="store in stores" :key="store" :value="store">
                        {{ store }}
                    </option>
                </select>

                <label for="status-select">Status:</label>
                <select id="status-select" v-model="statusFilter" @change="changeStatusFilter" class="status-select">
                    <option value="">All Status</option>
                    <option value="Pending">Pending</option>
                    <option value="Shipped">Shipped</option>
                    <option value="Canceled">Canceled</option>
                    <option value="Unshipped">Unshipped</option>
                </select>

                <button class="btn-refresh" @click="refreshData">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div> -->

        <scanner-component 
            scanner-title="Matching Serials Scanner"
            storage-prefix="matchSerials"
            :enable-camera="true"
            :display-fields="['Message', 'Status']"
            :hide-button="true"
            :show-camera-screen="false"
            @scanner-opened="handleMatchSerialScannerOpened"
            @scanner-closed="handleMatchSerialScannerClosed"
            @hardware-scan="handleHardwareScan"
            @mode-changed="handleModeChange"
            @scanner-reset="handleScannerReset"
            ref="scanner"
        >
            <template #input-fields>
                <div>
                   <div class="location-scanner-container">
                        <h4>Location: {{ productLocationScanner }}</h4>

                        <div class="scanner-details">
                            <div class="info-group">
                                <h6>Looking for serial number:</h6>
                                <div v-for="(item, index) in serialsAndTracking" :key="'serial-' + index">
                                    {{ item.serial }}
                                </div>
                            </div>

                            <div class="info-group">
                                <h6>Looking for tracking number:</h6>
                                <div v-for="(item, index) in serialsAndTracking" :key="'track-' + index">
                                    {{ item.tracking}}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="input-group">
                    <label>Match {{ scanMode === 'serial' ? 'Serial' : 'Tracking' }} Number:</label>
                    <input 
                        v-model="scanInput" 
                        type="text" 
                        :placeholder="`Enter ${scanMode === 'serial' ? 'serial' : 'tracking'} number`"
                        @input="handleSerialInput"
                         @keyup.enter="processMatchSerialNumber"
                        ref="scanInputRef"
                    >
                    <button class="switch-scan-mode" @click="handleChangeScanMode">
                        Switch to {{ scanMode === 'serial' ? 'Tracking' : 'Serial' }}
                    </button>
                </div>
                </div>
            </template>
        </scanner-component>

        <!-- Selection status bar - NEW COMPONENT -->
        <div class="selection-status-bar" v-if="persistentSelectedOrderIds.length > 0">
            <div class="selection-info">
                <i class="fas fa-check-square"></i>
                <span>{{ persistentSelectedOrderIds.length }} order{{
                    persistentSelectedOrderIds.length > 1 ? "s" : ""
                    }}
                    selected across all pages</span>
                <button class="btn-clear-selection" @click="clearAllSelections">
                    <i class="fas fa-times"></i> Clear Selection
                </button>
            </div>
        </div>

        <!-- <h2 class="module-title">FBM Order Module</h2> -->
        <div class="d-flex align-items-center justify-content-between flex-wrap mb-4">
            <TitlePage title="FBM Orders Module"
                subtitle="Manage all orders fulfilled directly by the merchant. Process shipments, generate labels, and track the status of FBM orders." />
            <div class="d-flex justify-content-center gap-2 me-4 flex-wrap desktop-view">
                <Button severity="secondary" size="small" outlined @click="openShipmentLabelHistoryModal"
                    label="Shipment Label History" icon="pi pi-history" />
                <Button severity="secondary" size="small" outlined @click="openWorkHistoryModal" label="Work History"
                    icon="pi pi-chart-line" />
                <Button size="small" severity="secondary" outlined v-if="persistentSelectedOrderIds.length > 0"
                    @click="PurchaseShippingLabel" label="Purchase Shipping Label" icon="pi pi-truck" />
                <Button size="small" severity="secondary" outlined @click="processSelectedOrders"
                    label="Process Selected" icon="pi pi-truck" />
                <Button size="small" severity="secondary" outlined
                    @click="() => { console.log('clicked'); openPrintDocumentsModal(); }" label="Print Labels"
                    icon="pi pi-tag" />
                <Button size="small" severity="secondary" outlined @click="generatePackingSlips"
                    label="Generate Packing Slips" icon="pi pi-file" />
                <Button size="small" severity="secondary" outlined @click="openManualShipmentLabelModal"
                    label="Manual Shipment Label" />
            </div>
            <div class="mobile-view w-100 ms-2">
                <Button label="More Actions" fluid size="small" severity="secondary" outlined icon="pi pi-list"
                    @click="toggleUpperMenuButton($event)" aria-haspopup="true" aria-controls="overlay_menu" />
            </div>

            <Menu ref="upperMenu" id="overlay_menu" :model="upperMenuActions" :popup="true" />
        </div>


        <!-- Desktop Table Container -->
        <AnimateDiv :delay="200" class="px-4">
            <div class="search-container">
                <fieldset class="d-flex align-items-center gap-3 ">
                    <label for="moduleFilter">Store</label>
                    <Select :options="storeOptions" v-model="selectedStore" optionLabel="label" optionValue="value"
                        size="small" class="select-form" @change="changeStore" placeholder="Select a store" />
                </fieldset>
                <fieldset class="d-flex align-items-center gap-3 ">
                    <label for="moduleFilter">Status</label>
                    <Select :options="statusOptions" v-model="statusFilter" optionLabel="label" optionValue="value"
                        size="small" class="select-form" @change="changeStatusFilter" placeholder="Select a status" />
                </fieldset>

                <fieldset class="d-flex align-items-center gap-3 ">
                    <label for="orderByFilter">Order</label>
                    <Select :options="orderByOptions" v-model="orderByFilter" optionLabel="label" optionValue="value"
                        size="small" class="select-form" @change="changeOrderBy" placeholder="Order by" />
                </fieldset>

            </div>
            <XDataTable :value="orders" :loading="loading" :columns="columns" :pagination="false"
                tableClass="desktop-view " dataKey="outboundorderid" selectionMode="multiple"
                :onSelectionChange="onSelectionChange" :disableRowCheckbox="row => !canSelectOrder(row)"
                :onAllSelectionChange="onAllSelectionChange">
                <template #orderDetails="{ data }">
                    <div class="d-flex flex-column gap-2">
                        <div class="detail-item-container">
                            <span>Order Id: </span>
                            <span>{{ data.platform_order_id }}</span>
                        </div>
                        <div class="detail-item-container">
                            <span>Customer Name: </span>
                            <span>{{ data.buyer_name || "N/A" }}</span>
                        </div>
                        <div class="detail-item-container">
                            <span>Address: </span>
                            <span>{{ formatAddress(data.address) }}</span>
                        </div>
                        <div class="detail-item-container">
                            <span>Fulfillment Channel: </span>
                            <span class="text-danger fw-bolder">{{
                                data.FulfillmentChannel
                                }}</span>
                        </div>
                        <div class="detail-item-container">
                            <span>Amazon Order: </span>
                            <span>{{ formatDate(data.purchase_date) }}</span>
                        </div>
                    </div>
                </template>
                <template #productDetails="{ data }">
                    <div>
                        <div v-for="(subdata, index) in data.items" :key="index"
                            class="mb-4 d-flex align-items-start gap-2 border-bottom pb-2">
                            <div class="checkbox-disabled-tooltip">
                                <!-- <input type="checkbox"
                                    :value="`${data.platform_order_id}|${subdata.outboundorderitemid}`"
                                    v-model="dispenseItemsSelected" /> -->

                                <input type="checkbox" :value="subdata.outboundorderitemid
                                    " v-model="dispenseItemsSelected" class="item-dispense-checkbox" :disabled="!isItemDispensed(subdata)
                                        " />
                            </div>
                            <div class="d-flex flex-column gap-2"
                                style="word-break: break-word; white-space: normal; overflow-wrap: break-word; border-bottom: none;">
                                <div>
                                    <h5>
                                        {{ subdata.platform_title }}
                                    </h5>
                                    <span v-if="subdata.quantity_ordered > 1"
                                        class="bg-secondary text-white px-2 rounded-2">
                                        Qty: {{ subdata.quantity_ordered }}
                                        <span v-if="isItemDispensed(subdata)">
                                            ({{
                                                getDispensedProductCount(subdata)
                                            }}
                                            dispensed)</span>
                                    </span>
                                </div>
                                <div class="detail-item-container">
                                    <span>Order Item ID: </span>
                                    <span>{{ subdata.platform_order_item_id || "N/A" }}</span>
                                </div>
                                <div class="detail-item-container d-flex align-items-center">
                                    <span>Ordered ASIN: </span>
                                    <div class="d-flex align-items-center">
                                        <p>{{ subdata.platform_asin || "N/A" }}</p>
                                        <Button v-if="subdata.platform_asin" label="Edit" variant="link" size="small"
                                            class="text-primary" />
                                    </div>
                                </div>
                                <div class="detail-item-container">
                                    <span>Ordered MNSKU: </span>
                                    <span>{{ subdata.platform_sku || "N/A" }}</span>
                                </div>
                                <div class="detail-item-container">
                                    <span>Ordered Condition: </span>
                                    <span>{{ subdata.condition || "N/A" }}</span>
                                </div>
                                <div class="detail-item-container">
                                    <span>Item Price: </span>
                                    <span>${{
                                        parseFloat(
                                            subdata.unit_price || 0
                                        ).toFixed(2)
                                    }}
                                    </span>
                                </div>
                                <div class="detail-item-container">
                                    <span>Item Tax: </span>
                                    <span>${{
                                        parseFloat(
                                            subdata.unit_tax || 0
                                        ).toFixed(2)
                                    }}
                                    </span>
                                </div>
                                <!-- Enhanced dispensed products display for multiple quantities -->
                                <Panel v-if="isItemDispensed(subdata)"
                                    :header="`Dispensed Products (${getDispensedProductCount(subdata)}/${subdata.quantity_ordered})`"
                                    class="dispensed-item-details dispensed-panel" toggleable :collapsed="true">
                                    <!-- Display all dispensed products -->
                                    <div v-for="(dispensedProduct, dpIndex) in getDispensedProductsDisplay(subdata)"
                                        :key="'dp-' + dpIndex" class="dispensed-product-item">
                                        <div class="dispensed-detail">
                                            <strong>Title:</strong>
                                            {{ dispensedProduct.title || 'N/A' }}
                                        </div>

                                        <div class="dispensed-detail">
                                            <strong>ASIN:</strong>
                                            {{ dispensedProduct.asin || 'N/A' }}
                                        </div>

                                        <div class="dispensed-detail">
                                            <strong>Location:</strong>
                                            {{ dispensedProduct.warehouseLocation || 'N/A' }}
                                        </div>

                                        <div v-if="dispensedProduct.serialNumber" class="dispensed-detail">
                                            <strong>Serial #1:</strong>
                                            {{ dispensedProduct.serialNumber }}
                                        </div>

                                        <div v-if="dispensedProduct.serialNumberb" class="dispensed-detail">
                                            <strong>Serial #2:</strong>
                                            {{ dispensedProduct.serialNumberb }}
                                        </div>

                                        <div v-if="dispensedProduct.serialNumberc" class="dispensed-detail">
                                            <strong>Serial #3:</strong>
                                            {{ dispensedProduct.serialNumberc }}
                                        </div>

                                        <div v-if="dispensedProduct.serialNumberd" class="dispensed-detail">
                                            <strong>Serial #4:</strong>
                                            {{ dispensedProduct.serialNumberd }}
                                        </div>

                                        <div v-if="dispensedProduct.rtCounter" class="dispensed-detail">
                                            <strong>RT Counter:</strong>
                                            {{ dispensedProduct.rtCounter }}
                                        </div>

                                        <div v-if="dispensedProduct.FNSKU" class="dispensed-detail">
                                            <strong>FNSKU:</strong>
                                            {{ dispensedProduct.FNSKU }}
                                        </div>

                                        <div class="dispensed-actions">
                                            <button class="btn-not-found"
                                                @click="markProductNotFound(dispensedProduct.product_id, subdata)"
                                                title="Mark product as not found and auto-select replacement">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                Not Found
                                            </button>
                                            <button class="btn-matching-serials" 
                                                @click="openMatchSerialScannerModal(subdata, dpIndex)">
                                                <i class="fas fa-copy"></i>
                                                Matching Serials
                                            </button>
                                        </div>

                                        <hr v-if="dpIndex < getDispensedProductsDisplay(subdata).length - 1"
                                            class="dispensed-separator" />
                                    </div>
                                </Panel>

                                <!-- <div v-if="isItemDispensed(subdata)" class="dispensed-item-details">
                                    <div class="dispensed-header">
                                        <span class="product-id-badge">
                                            Dispensed Products ({{
                                                getDispensedProductCount(
                                                    subdata
                                                )
                                            }}/{{ subdata.quantity_ordered }})
                                        </span>
                                    </div>

                                    <div v-for="(dispensedProduct, dpIndex) in getDispensedProductsDisplay(subdata)"
                                        :key="'dp-' + dpIndex" class="dispensed-product-item">
                                        <div class="dispensed-detail">
                                            <strong>Title:</strong>
                                            {{
                                                dispensedProduct.title ||
                                                "N/A"
                                            }}
                                        </div>
                                        <div class="dispensed-detail">
                                            <strong>ASIN:</strong>
                                            {{
                                                dispensedProduct.asin ||
                                                "N/A"
                                            }}
                                        </div>
                                        <div class="dispensed-detail">
                                            <strong>Location:</strong>
                                            {{
                                                dispensedProduct.warehouseLocation ||
                                                "N/A"
                                            }}
                                        </div>
                                        <div v-if="
                                            dispensedProduct.serialNumber
                                        " class="dispensed-detail">
                                            <strong>Serial #:</strong>
                                            {{
                                                dispensedProduct.serialNumber
                                            }}
                                        </div>
                                        <div v-if="
                                            dispensedProduct.rtCounter
                                        " class="dispensed-detail">
                                            <strong>RT Counter:</strong>
                                            {{ dispensedProduct.rtCounter }}
                                        </div>
                                        <div v-if="dispensedProduct.FNSKU" class="dispensed-detail">
                                            <strong>FNSKU:</strong>
                                            {{ dispensedProduct.FNSKU }}
                                        </div>
                                        <div class="dispensed-actions">
                                            <button class="btn-not-found" @click="
                                                markProductNotFound(
                                                    dispensedProduct.product_id,
                                                    subdata
                                                )
                                                " title="Mark product as not found and auto-select replacement">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                Not Found
                                            </button>
                                        </div>
                                        <hr v-if="
                                            dpIndex <
                                            getDispensedProductsDisplay(
                                                subdata
                                            ).length -
                                            1
                                        " class="dispensed-separator" />
                                    </div>
                                </div> -->
                            </div>
                        </div>
                    </div>
                </template>
                <template #orderType="{ data }">
                    <div class="d-flex flex-column align-items-start gap-2">
                        <div class="detail-item-container">
                            <span>Order Type: </span>
                            <span> {{ data.order_type || "StandardOrder" }}
                            </span>
                        </div>
                        <div class="detail-item-container">
                            <span>Shipment Service: </span>
                            <span> {{ data.shipment_service || "Standard" }}
                            </span>
                        </div>
                        <div class="detail-item-container">
                            <span>Replacement Order: </span>
                            <span> {{ data.is_replacement ? 'True' : 'False' }}
                            </span>
                        </div>
                        <div class="detail-item-container">
                            <span>Ship by Date: </span>
                            <span> {{ formatShipByDate(data.ship_date) }}</span>
                        </div>
                        <div class="detail-item-container">
                            <span>Delivered by Date: </span>
                            <span> {{ formatDeliveryDate(data.delivery_date)
                                }}</span>
                        </div>
                        <div v-if="hasTrackingNumber(data)">
                            <span>Tracking Status:</span>
                            <span>{{ getTrackingStatus($data) }}</span>
                        </div>
                    </div>
                </template>
<template #orderStatus="{ data }">
    <div class="d-flex flex-column gap-2">
        <div class="detail-item-container">
            <span>Order Status: </span>
            <Badge :style="{ backgroundColor: getStatusColor(data.order_status) }"
                :value="data.order_status" />
        </div>
        <div class="detail-item-container">
            <span>Ship Status: </span>
            <span>{{ getShipStatus(data) }}</span>
        </div>
        <div class="detail-item-container">
            <span>Store Name: </span>
            <span>{{ data.storename || "N/A" }}</span>
        </div>
        
        <!-- ✅ ADD THESE 3 LINES -->
        <div class="detail-item-container">
            <span>Tracking #: </span>
            <span>{{ getTrackingNumber(data) }}</span>
        </div>
        <div class="detail-item-container">
            <span>Tracking Status: </span>
            <span>{{ getTrackingStatusFromItems(data) }}</span>
        </div>
        <div class="detail-item-container">
            <span>Carrier: </span>
            <span>{{ getCarrierInfo(data) }}</span>
        </div>
    </div>
</template>
                <template #actions="{ data }">
                    <div class="d-flex flex-column align-items-start gap-2">
                        <Select optionLabel="label" optionValue="value" :options="[
                            { label: 'Null', value: 'Null' }
                        ]" fluid size="small" :modelValue="'Null'" />
                        <!-- <Button label="Track" size="small" icon="pi pi-compass" severity="contrast" variant="text"
                            outlined />
                        <Button label="Tracking History" icon="pi pi-history" severity="contrast" variant="text"
                            size="small" outlined /> -->
                        <Button label="Print" icon="pi pi-print" severity="contrast" variant="text" size="small"
                            @click="openPrintInvoiceModal(data)" class="text-primary" />
                        <!-- <Button label="Process" icon="pi pi-truck" severity="contrast" variant="text" size="small"
                            :disabled="data.order_status ===
                                'Shipped' ||
                                data.order_status ===
                                'Canceled'" @click="openProcessModal(data)" class="text-warning" /> -->

                        <Button label="Process" icon="pi pi-truck" severity="contrast" variant="text" size="small"
                            :disabled="data.order_status ===
                                'Canceled'" @click="openProcessModal(data)" class="text-warning" />
                        <Button type="button" severity="contrast" variant="text" icon="pi pi-list"
                            @click="toggle($event, data)" aria-haspopup="true" aria-controls="overlay_menu" size="small"
                            label="More Actions" />
                        <Menu ref="menu" id="overlay_menu" :model="menuActions" :popup="true" />
                    </div>
                </template>
            </XDataTable>
        </AnimateDiv>

        <!-- Mobile Cards View -->
        <div class="mobile-view">
            <div v-if="loading" class="loading-spinner-mobile">
                <i class="fas fa-spinner fa-spin"></i> Loading...
            </div>
            <div v-else-if="orders.length === 0" class="no-data-mobile">
                No orders found
            </div>
            <div v-else class="mobile-cards" :style="{ fontSize: '14px' }">
                <div v-for="(order, index) in orders" :key="order.outboundorderid" class="mobile-card">
                    <div class="mobile-card-header">
                        <div class="mobile-checkbox">
                            <div class="checkbox-disabled-tooltip">
                                <input type="checkbox" v-model="order.checked" @change="handleOrderCheckChange(order)"
                                    :disabled="!canSelectOrder(order)" />
                            </div>
                        </div>
                        <div class="mobile-order-id fw-bolder">
                            {{ order.platform_order_id }}
                        </div>
                        <Badge :style="{ backgroundColor: getStatusColor(order.order_status) }"
                            :value="order.order_status" />
                    </div>

                    <div class="mobile-customer">
                        <div class="mobile-customer-name">
                            {{ order.buyer_name }}
                        </div>
                        <div class="mobile-customer-address">
                            {{ formatAddress(order.address) }}
                        </div>
                    </div>

                    <hr />

                    <!-- Enhanced Mobile Products Display -->
                    <div class="mobile-products">
                        <div v-for="(item, itemIndex) in order.items || []" :key="itemIndex"
                            class="mobile-product-item">
                            <!-- Mobile product title with checkbox -->
                            <div class="mobile-product-title-row d-flex align-items-center gap-2 mb-2">
                                <div class="checkbox-disabled-tooltip">
                                    <input type="checkbox" :value="item.outboundorderitemid"
                                        v-model="dispenseItemsSelected" class="mobile-item-dispense-checkbox"
                                        :disabled="!isItemDispensed(item)" />
                                </div>
                                <div class="mobile-product-title fw-bolder">
                                    {{ item.platform_title }}
                                    <span v-if="item.quantity_ordered > 1" class="quantity-badge-mobile">
                                        Qty: {{ item.quantity_ordered }}
                                        <span v-if="isItemDispensed(item)">
                                            ({{
                                                getDispensedProductCount(item)
                                            }}
                                            dispensed)</span>
                                    </span>
                                </div>
                            </div>
                            <div class="mobile-order-details border-0">
                                <div class="mobile-detail">
                                    <span class="mobile-detail-label">ASIN: </span>
                                    <span class="mobile-detail-value">{{ item.platform_asin }}</span>
                                </div>
                                <div class="mobile-detail">
                                    <span class="mobile-detail-label">SKU: </span>
                                    <span class="mobile-detail-value">{{ item.platform_sku }}</span>
                                </div>
                            </div>
                            <div class="mobile-detail ps-2">
                                <span class="mobile-detail-label">Condition: </span>
                                <span class="mobile-detail-value">{{ item.condition }} | Price: ${{
                                    parseFloat(item.unit_price || 0).toFixed(2)
                                    }}</span>
                            </div>

                            <!-- Enhanced mobile dispensed products display -->
                            <!-- <div v-if="isItemDispensed(item)" class="mobile-product-dispense">
                                <div class="mobile-dispensed-header">
                                    Dispensed Products ({{
                                        getDispensedProductCount(item)
                                    }})
                                </div>
                                <div v-for="(
dispensedProduct, dpIndex
                                    ) in getDispensedProductsDisplay(item)" :key="'mobile-dp-' + dpIndex"
                                    class="mobile-dispensed-item">
                                    <div class="mobile-dispensed-detail">
                                        <strong>Title:</strong>
                                        {{ dispensedProduct.title || "N/A" }}
                                    </div>
                                    <div class="mobile-dispensed-detail">
                                        <strong>ASIN:</strong>
                                        {{ dispensedProduct.asin || "N/A" }}
                                    </div>
                                    <div class="mobile-dispensed-detail">
                                        <strong>Loc:</strong>
                                        {{
                                            dispensedProduct.warehouseLocation ||
                                            "N/A"
                                        }}
                                    </div>
                                    <div v-if="dispensedProduct.serialNumber" class="mobile-dispensed-detail">
                                        <strong>Serial:</strong>
                                        {{ dispensedProduct.serialNumber }}
                                    </div>
                                    <div v-if="dispensedProduct.rtCounter" class="mobile-dispensed-detail">
                                        <strong>RT:</strong>
                                        {{ dispensedProduct.rtCounter }}
                                    </div>
                                    <div v-if="dispensedProduct.FNSKU" class="mobile-dispensed-detail">
                                        <strong>FNSKU:</strong>
                                        {{ dispensedProduct.FNSKU }}
                                    </div>
                                    <div class="mobile-dispensed-actions">
                                        <button class="btn-not-found-mobile" @click="
                                            markProductNotFound(
                                                dispensedProduct.product_id,
                                                item
                                            )
                                            " title="Mark as not found">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            Not Found
                                        </button>
                                          <button class="btn-matching-serials-mobile" 
                                                @click="openMatchSerialScannerModal(subdata, dpIndex)">
                                                <i class="fas fa-copy"></i>
                                                Match Serials
                                            </button>
                                    </div>
                                </div>
                            </div> -->
                            <Panel v-if="isItemDispensed(item)" toggleable :collapsed="true" 
                            class="dispensed-item-details dispensed-panel" :header="`Dispensed Products (${getDispensedProductCount(item)}/${item.quantity_ordered})`">
                                <div v-for="(dispensedProduct, dpIndex) in getDispensedProductsDisplay(item)"
                                        :key="'dp-' + dpIndex" class="dispensed-product-item">
                                        <div class="dispensed-detail">
                                            <strong>Title:</strong>
                                            {{ dispensedProduct.title || 'N/A' }}
                                        </div>

                                        <div class="dispensed-detail">
                                            <strong>ASIN:</strong>
                                            {{ dispensedProduct.asin || 'N/A' }}
                                        </div>

                                        <div class="dispensed-detail">
                                            <strong>Location:</strong>
                                            {{ dispensedProduct.warehouseLocation || 'N/A' }}
                                        </div>

                                        <div v-if="dispensedProduct.serialNumber" class="dispensed-detail">
                                            <strong>Serial #1:</strong>
                                            {{ dispensedProduct.serialNumber }}
                                        </div>

                                        <div v-if="dispensedProduct.serialNumberb" class="dispensed-detail">
                                            <strong>Serial #2:</strong>
                                            {{ dispensedProduct.serialNumberb }}
                                        </div>

                                        <div v-if="dispensedProduct.serialNumberc" class="dispensed-detail">
                                            <strong>Serial #3:</strong>
                                            {{ dispensedProduct.serialNumberc }}
                                        </div>

                                        <div v-if="dispensedProduct.serialNumberd" class="dispensed-detail">
                                            <strong>Serial #4:</strong>
                                            {{ dispensedProduct.serialNumberd }}
                                        </div>

                                        <div v-if="dispensedProduct.rtCounter" class="dispensed-detail">
                                            <strong>RT Counter:</strong>
                                            {{ dispensedProduct.rtCounter }}
                                        </div>

                                        <div v-if="dispensedProduct.FNSKU" class="dispensed-detail">
                                            <strong>FNSKU:</strong>
                                            {{ dispensedProduct.FNSKU }}
                                        </div>

                                        <div class="dispensed-actions">
                                            <button class="btn-not-found"
                                                @click="markProductNotFound(dispensedProduct.product_id, item)"
                                                title="Mark product as not found and auto-select replacement">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                Not Found
                                            </button>
                                            <button class="btn-matching-serials" 
                                                @click="openMatchSerialScannerModal(item, dpIndex)">
                                                <i class="fas fa-copy"></i>
                                                Matching Serials
                                            </button>
                                        </div>

                                        <hr v-if="dpIndex < getDispensedProductsDisplay(item).length - 1"
                                            class="dispensed-separator" />
                                    </div>
                            </Panel>
                        
                        </div>
                    </div>

                    <hr />

                    <div class="mobile-order-details">
                        <div class="mobile-detail">
                            <span class="mobile-detail-label">Purchase Date: </span>
                            <span class="mobile-detail-value">{{
                                formatDate(order.purchase_date)
                                }}</span>
                        </div>
                        <div class="mobile-detail">
                            <span class="mobile-detail-label">Order Type: </span>
                            <span class="mobile-detail-value">{{
                                order.order_type
                                }}</span>
                        </div>
                        <div class="mobile-detail">
                            <span class="mobile-detail-label">Shipment: </span>
                            <span class="mobile-detail-value">{{
                                order.shipment_service
                                }}</span>
                        </div>
                    </div>

                    <hr />

                    <div class="d-flex flex-nowrap overflow-auto gap-2 pb-3">
                        <div class="flex-shrink-0"> <Button label="Track" size="small" class="w-100" outlined /></div>
                        <div class="flex-shrink-0"> <Button label="Tracking History" severity="info" size="small"
                                class="w-100" outlined /></div>
                        <div class="flex-shrink-0"><Button label="Print" icon="pi pi-print" severity="warn"
                                class="w-100" size="small" @click="openPrintInvoiceModal(order)" /></div>
                        <div class="flex-shrink-0">
                            <Button label="Process" icon="pi pi-truck" severity="help" class="w-100" size="small"
                                :disabled="order.order_status ===
                                    'Shipped' ||
                                    order.order_status ===
                                    'Canceled'
                                    " @click="openProcessModal(order)" />
                        </div>




                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination with centered layout -->
        <div class="pagination-container">
            <div class="pagination-wrapper">
                <div class="per-page-selector">
                    <span>Rows per page</span>
                    <Select v-model="perPage" @change="changePerPage" :options="rowsPerPageOptions" size="small"
                        optionLabel="label" optionValue="value" />
                </div>

                <div class="pagination">
                    <Button @click="prevPage" :disabled="currentPage === 1" class="pagination-button" size="small"
                        label="Back" icon="pi pi-angle-left" severity="info" />
                    <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
                    <Button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button"
                        size="small" label="Next" icon="pi pi-angle-right" severity="info" iconPos="right" />
                </div>
            </div>
        </div>

        <!-- Order Details Modal -->
        <div v-if="showOrderDetailsModal" class="order-details-modal">
            <div class="order-details-content">
                <div class="order-details-header">
                    <h2>Order Details</h2>
                    <button class="order-details-close" @click="closeOrderDetailsModal">&times;</button>
                </div>

                <div class="order-details-body" v-if="selectedOrder">
                    <div class="order-details-sections">
                        <!-- Order Information Section -->
                        <div class="order-details-section">
                            <h3 class="section-title">Order Information</h3>
                            <div class="order-info-grid">
                                <div class="info-row">
                                    <div class="info-label">Order ID:</div>
                                    <div class="info-value">{{ selectedOrder.platform_order_id }}</div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Store:</div>
                                    <div class="info-value">{{ selectedOrder.storename }}</div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Order Type:</div>
                                    <div class="info-value">{{ selectedOrder.order_type }}</div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Status:</div>
                                    <div class="info-value">
                                        <span :class="getStatusClass(selectedOrder.order_status)">
                                            {{ selectedOrder.order_status }}
                                        </span>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Purchase Date:</div>
                                    <div class="info-value">{{ formatDate(selectedOrder.purchase_date) }}</div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Ship Date:</div>
                                    <div class="info-value">{{ formatDate(selectedOrder.ship_date) }}</div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Delivery Date:</div>
                                    <div class="info-value">{{ formatDate(selectedOrder.delivery_date) }}</div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Shipment Service:</div>
                                    <div class="info-value">{{ selectedOrder.shipment_service }}</div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Replacement:</div>
                                    <div class="info-value">{{ selectedOrder.is_replacement ? 'Yes' : 'No' }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Customer Information Section -->
                        <div class="order-details-section">
                            <h3 class="section-title">Customer Information</h3>
                            <div class="customer-info">
                                <div class="customer-name">{{ selectedOrder.buyer_name }}</div>
                                <div class="customer-address">{{ formatAddress(selectedOrder.address, true) }}</div>
                            </div>

                            <div v-if="selectedOrder && selectedOrder.items && selectedOrder.items.some(item => item.tracking_number)"
                                class="tracking-info">
                                <h4>Tracking Information</h4>
                                <div v-for="(item, idx) in selectedOrder.items.filter(i => i.tracking_number)"
                                    :key="'tracking-' + idx" class="tracking-item">
                                    <div class="tracking-number">{{ item.tracking_number }}</div>
                                    <div class="tracking-status">{{ item.tracking_status || 'Status not available' }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Enhanced Order Actions Section -->
                        <div class="order-details-section">
                            <h3 class="section-title">Actions</h3>
                            <div class="order-actions">
                                <!-- Process Button -->
                                <button v-if="selectedOrder.order_status !== 'Shipped' &&
                                    selectedOrder.order_status !== 'Canceled'" class="action-button process-button"
                                    @click="openProcessModalFromDetails(selectedOrder)">
                                    <i class="fas fa-shipping-fast"></i> Process Order
                                </button>

                                <!-- Auto Dispense Button - only show if there are unassigned items -->
                                <button
                                    v-if="selectedOrder.items && selectedOrder.items.some(item => !isItemDispensed(item))"
                                    class="action-button auto-dispense-button" @click="autoDispense(selectedOrder)">
                                    <i class="fas fa-box-open"></i> Auto Dispense Items
                                </button>

                                <!-- Cancel Dispense Button - show if there are any dispensed items -->
                                <button v-if="hasDispensedItems(selectedOrder)"
                                    class="action-button cancel-dispense-button" @click="cancelDispense(selectedOrder)">
                                    <i class="fas fa-undo"></i> Cancel Dispense
                                </button>

                                <button class="action-button packing-button"
                                    @click="generatePackingSlip(selectedOrder.outboundorderid)">
                                    <i class="fas fa-file-alt"></i> Generate Packing Slip
                                </button>
                                <button class="action-button label-button"
                                    @click="printShippingLabel(selectedOrder.outboundorderid)">
                                    <i class="fas fa-tag"></i> Print Shipping Label
                                </button>
                                <button
                                    v-if="selectedOrder.order_status === 'Pending' || selectedOrder.order_status === 'Unshipped'"
                                    class="action-button cancel-button"
                                    @click="confirmCancelOrder(selectedOrder.outboundorderid)">
                                    <i class="fas fa-times-circle"></i> Cancel Order
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced Order Items Section for Order Details Modal -->
                    <div class="order-items-section">
                        <h3 class="section-title">Order Items</h3>
                        <div class="order-items">
                            <div v-for="(item, idx) in (selectedOrder.items || [])" :key="idx" class="order-item">
                                <div class="item-title-row">
                                    <div class="checkbox-disabled-tooltip">
                                        <input type="checkbox" :value="item.outboundorderitemid"
                                            v-model="dispenseItemsSelected" class="item-dispense-checkbox"
                                            :disabled="!isItemDispensed(item)" />
                                    </div>
                                    <div class="item-title">{{ item.platform_title }}</div>
                                </div>
                                <div class="item-details-grid">
                                    <div class="item-details-left">
                                        <div class="item-info-row">
                                            <div class="item-label">ASIN:</div>
                                            <div class="item-value">{{ item.platform_asin }}</div>
                                        </div>
                                        <div class="item-info-row">
                                            <div class="item-label">SKU:</div>
                                            <div class="item-value">{{ item.platform_sku }}</div>
                                        </div>
                                        <div class="item-info-row">
                                            <div class="item-label">Condition:</div>
                                            <div class="item-value">{{ item.condition }}</div>
                                        </div>
                                        <div class="item-info-row">
                                            <div class="item-label">Quantity:</div>
                                            <div class="item-value">
                                                {{ item.quantity_ordered }}
                                                <span v-if="isItemDispensed(item)" class="dispensed-count">
                                                    ({{ getDispensedProductCount(item) }} dispensed)
                                                </span>
                                            </div>
                                        </div>
                                        <div class="item-info-row">
                                            <div class="item-label">Price:</div>
                                            <div class="item-value">${{ parseFloat(item.unit_price || 0).toFixed(2) }}
                                            </div>
                                        </div>
                                        <div class="item-info-row">
                                            <div class="item-label">Tax:</div>
                                            <div class="item-value">${{ parseFloat(item.unit_tax || 0).toFixed(2) }}
                                            </div>
                                        </div>

                                        <!-- Enhanced dispensed item details section for multiple products -->
                                        <div v-if="isItemDispensed(item)" class="item-details-dispensed">
                                            <div class="item-dispensed-title">
                                                Dispensed Products ({{ getDispensedProductCount(item) }})
                                            </div>
                                            <div class="item-dispensed-detail">
                                                <div v-for="(dispensedProduct, dpIndex) in getDispensedProductsDisplay(item)"
                                                    :key="'modal-dp-' + dpIndex" class="dispensed-product-modal">
                                                    <div class="dispensed-row">
                                                        <span class="dispensed-label">Title:</span>
                                                        <span class="dispensed-value">{{ dispensedProduct.title || 'N/A'
                                                            }}</span>
                                                    </div>
                                                    <div class="dispensed-row">
                                                        <span class="dispensed-label">ASIN:</span>
                                                        <span class="dispensed-value">{{ dispensedProduct.asin || 'N/A'
                                                            }}</span>
                                                    </div>
                                                    <div class="dispensed-row">
                                                        <span class="dispensed-label">Location:</span>
                                                        <span class="dispensed-value">{{
                                                            dispensedProduct.warehouseLocation ||
                                                            'N/A' }}</span>
                                                    </div>
                                                    <div v-if="dispensedProduct.serialNumber" class="dispensed-row">
                                                        <span class="dispensed-label">Serial #:</span>
                                                        <span class="dispensed-value">{{ dispensedProduct.serialNumber
                                                            }}</span>
                                                    </div>
                                                    <div v-if="dispensedProduct.rtCounter" class="dispensed-row">
                                                        <span class="dispensed-label">RT Counter:</span>
                                                        <span class="dispensed-value">{{ dispensedProduct.rtCounter
                                                            }}</span>
                                                    </div>
                                                    <div v-if="dispensedProduct.FNSKU" class="dispensed-row">
                                                        <span class="dispensed-label">FNSKU:</span>
                                                        <span class="dispensed-value">{{ dispensedProduct.FNSKU
                                                            }}</span>
                                                    </div>
                                                    <div class="dispensed-row">
                                                        <span class="dispensed-label">Action:</span>
                                                        <button class="btn-not-found-modal"
                                                            @click="markProductNotFound(dispensedProduct.product_id, item)"
                                                            title="Mark product as not found and auto-select replacement">
                                                            <i class="fas fa-exclamation-triangle"></i> Not Found
                                                        </button>
                                                    </div>
                                                    <hr v-if="dpIndex < getDispensedProductsDisplay(item).length - 1"
                                                        class="dispensed-separator">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Notes Section -->
                    <div v-if="selectedOrder.ordernote" class="order-notes-section">
                        <h3 class="section-title">Order Notes</h3>
                        <div class="order-notes">
                            <pre>{{ selectedOrder.ordernote }}</pre>
                        </div>
                    </div>
                </div>

                <div class="order-details-footer">
                    <button class="close-details-button" @click="closeOrderDetailsModal">Close</button>
                </div>
            </div>
        </div>

        <!-- Process Order Modal with Integrated Auto Dispense -->
           <Dialog v-model:visible="showProcessModal"
        :header="`Process Order: ${currentProcessOrder ? currentProcessOrder.platform_order_id : ''}`" 
        modal 
        :pt="{
            root: { class: 'mobile-fullscreen-dialog process-order-modal' }
        }" 
        :style="{ width: '95%', maxWidth: '1600px' }">
        
        <div class="process-modal-body">
            <!-- Order Summary Banner -->
            <div class="info-banner">
                <div class="info-grid">
                    <div class="info-col">
                        <span class="info-label">Order ID</span>
                        <span class="info-value">{{ currentProcessOrder?.platform_order_id }}</span>
                    </div>
                    <div class="info-col">
                        <span class="info-label">Customer</span>
                        <span class="info-value">{{ currentProcessOrder?.buyer_name || 'N/A' }}</span>
                    </div>
                    <div class="info-col">
                        <span class="info-label">Store</span>
                        <span class="info-value">{{ currentProcessOrder?.storename || 'N/A' }}</span>
                    </div>
                    <div class="info-col">
                        <span class="info-label">Order Type</span>
                        <span class="info-value">{{ currentProcessOrder?.order_type || 'Standard' }}</span>
                    </div>
                    <div class="info-col">
                        <span class="info-label">Total Items</span>
                        <div class="progress-tags">
                            <Tag :value="`${currentProcessOrder?.items?.length || 0} items`" severity="info" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Auto Dispense Section -->
            <div v-if="processingAutoDispense" class="auto-dispense-section">
                <div v-if="loadingDispenseProducts" class="state-container">
                    <i class="pi pi-spin pi-spinner" style="font-size: 3rem; color: #6366f1;"></i>
                    <p class="state-text">Searching for matching products...</p>
                </div>

                <div v-else-if="dispenseProducts.length === 0" class="state-container">
                    <i class="pi pi-inbox" style="font-size: 4rem; color: #94a3b8;"></i>
                    <p class="state-text">No matching products found in your inventory.</p>
                </div>

                <div v-else class="dispense-products-container">
                    <div class="dispense-products-grid">
                        <div v-for="(dispenseItem, index) in dispenseProducts" :key="'dispense-' + index"
                            class="dispense-item-card">
                            
                            <!-- Ordered Item Header -->
                            <div class="dispense-card-header">
                                <h4 class="dispense-item-title">
                                    <i class="pi pi-box"></i>
                                    {{ dispenseItem.ordered_item.platform_title }}
                                </h4>
                                <Tag :value="`Qty: ${dispenseItem.quantity_ordered} (${dispenseItem.quantity_dispensed} dispensed, ${dispenseItem.quantity_remaining} remaining)`"
                                    severity="info" />
                            </div>

                            <!-- Ordered Item Details -->
                            <div class="ordered-item-details">
                                <div class="detail-row">
                                    <i class="pi pi-tag"></i>
                                    <span class="label">ASIN:</span>
                                    <span class="value">{{ dispenseItem.ordered_item.platform_asin }}</span>
                                </div>
                                <div class="detail-row">
                                    <i class="pi pi-barcode"></i>
                                    <span class="label">SKU:</span>
                                    <span class="value">{{ dispenseItem.ordered_item.platform_sku }}</span>
                                </div>
                                <div class="detail-row">
                                    <i class="pi pi-star"></i>
                                    <span class="label">Condition:</span>
                                    <span class="value">{{ getConditionDisplay(dispenseItem.ordered_item) }}</span>
                                </div>
                                <div class="detail-row">
                                    <i class="pi pi-hashtag"></i>
                                    <span class="label">Order Item ID:</span>
                                    <span class="value">{{ dispenseItem.ordered_item.platform_order_item_id }}</span>
                                </div>
                            </div>

                            <!-- Already Dispensed Products -->
                            <div v-if="dispenseItem.quantity_dispensed > 0" class="already-dispensed-section">
                                <h5 class="section-subtitle">
                                    <i class="pi pi-check-circle"></i>
                                    Already Dispensed ({{ dispenseItem.quantity_dispensed }})
                                </h5>
                                <div class="dispensed-tags">
                                    <Tag v-for="(productId, idx) in dispenseItem.already_dispensed_products"
                                        :key="'dispensed-' + idx" 
                                        :value="`Product ID: ${productId}`"
                                        severity="success" />
                                </div>
                            </div>

                            <!-- Product Selection -->
                            <div v-if="dispenseItem.quantity_remaining > 0" class="product-selection-section">
                                <h5 class="section-subtitle">
                                    <i class="pi pi-plus-circle"></i>
                                    Select {{ dispenseItem.quantity_remaining }} More Product{{ dispenseItem.quantity_remaining > 1 ? 's' : '' }}
                                </h5>
                                
                                <Message severity="info" :closable="false" class="mb-3">
                                    <i class="pi pi-info-circle"></i> Products are sorted by stockroom date (oldest first)
                                </Message>

                                <Message v-if="dispenseItem.matching_products.length === 0" 
                                    severity="warning" :closable="false">
                                    No matching products for this item
                                </Message>

                                <!-- Product Slots -->
                                <div v-else class="product-slots">
                                    <div v-for="slot in dispenseItem.quantity_remaining" :key="'slot-' + slot"
                                        class="product-slot">
                                        <div class="slot-header">
                                            <i class="pi pi-box"></i>
                                            <span>Selection {{ slot }}</span>
                                        </div>
                                        
                                        <!-- Product Options -->
                                        <div class="product-options">
                                            <div v-for="(product, prodIndex) in dispenseItem.matching_products"
                                                :key="'product-' + prodIndex"
                                                @click="selectDispenseProduct(dispenseItem.item_id, slot - 1, product)"
                                                :class="['product-option-card', {
                                                    'selected': selectedDispenseProducts[`${dispenseItem.item_id}-${slot - 1}`] &&
                                                        selectedDispenseProducts[`${dispenseItem.item_id}-${slot - 1}`].ProductID === product.ProductID
                                                }]">
                                                
                                                <!-- Selection Badge -->
                                                <div class="selection-badge-small">
                                                    <i :class="selectedDispenseProducts[`${dispenseItem.item_id}-${slot - 1}`] &&
                                                        selectedDispenseProducts[`${dispenseItem.item_id}-${slot - 1}`].ProductID === product.ProductID 
                                                        ? 'pi pi-check-circle' : 'pi pi-circle'"></i>
                                                </div>

                                                <div class="product-option-content">
                                                    <div class="product-option-header">
                                                        <span class="product-title">{{ product.title }}</span>
                                                        <span class="product-id-badge">ID: {{ product.ProductID }}</span>
                                                    </div>
                                                    
                                                    <div class="product-option-details">
                                                        <div class="detail-mini">
                                                            <i class="pi pi-tag"></i>
                                                            <span>{{ product.asin }}</span>
                                                        </div>
                                                        <div class="detail-mini">
                                                            <i class="pi pi-barcode"></i>
                                                            <span>{{ product.msku }}</span>
                                                        </div>
                                                        <div class="detail-mini highlight">
                                                            <i class="pi pi-map-marker"></i>
                                                            <span>{{ product.warehouseLocation }}</span>
                                                        </div>
                                                        <div class="detail-mini">
                                                            <i class="pi pi-star"></i>
                                                            <span>{{ product.condition }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="fully-dispensed">
                                <i class="pi pi-check-circle"></i>
                                <span>This item is fully dispensed</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Regular Process Section -->
            <div v-else class="regular-process-section">
                <h3 class="section-title">
                    <i class="pi pi-list"></i>
                    Order Items
                </h3>
                <Divider />
                
                <div class="order-items-grid">
                    <div v-for="(item, idx) in (currentProcessOrder && currentProcessOrder.items ? currentProcessOrder.items : [])"
                        :key="idx" 
                        class="order-item-card">

                        <!-- Item Header -->
                        <div class="item-card-header">
                            <div class="item-title-section">
                                <h4 class="item-title">{{ item.platform_title }}</h4>
                                <div class="item-meta">
                                    <span class="item-meta-item">
                                        <i class="pi pi-tag"></i>
                                        ASIN: {{ item.platform_asin }}
                                    </span>
                                    <span class="item-meta-item">
                                        <i class="pi pi-barcode"></i>
                                        SKU: {{ item.platform_sku }}
                                    </span>
                                    <span class="item-meta-item">
                                        <i class="pi pi-box"></i>
                                        Qty: {{ item.quantity_ordered }}
                                    </span>
                                </div>
                            </div>

                            <!-- Status & Actions -->
                            <div class="item-status-actions">
                                <Tag v-if="isItemDispensed(item)"
                                    :value="`${getDispensedProductCount(item)}/${item.quantity_ordered} dispensed`"
                                    severity="success" />
                                <Tag v-else value="Not dispensed" severity="warning" />

                                <Button v-if="itemNeedsMoreProducts(item)" 
                                    label="Manual Dispense"
                                    icon="pi pi-plus-circle" 
                                    severity="info" 
                                    size="small"
                                    outlined
                                    @click="openManualDispenseForItem(item)" />
                            </div>
                        </div>

                        <!-- Item Details -->
                        <div class="item-details-grid">
                            <div class="detail-row">
                                <i class="pi pi-hashtag"></i>
                                <span class="label">Order Item ID:</span>
                                <span class="value">{{ item.platform_order_item_id }}</span>
                            </div>
                            <div class="detail-row">
                                <i class="pi pi-star"></i>
                                <span class="label">Condition:</span>
                                <span class="value">{{ getConditionDisplay(item) }}</span>
                            </div>
                        </div>

                        <!-- Dispensed Products Display -->
                        <div v-if="isItemDispensed(item)" class="dispensed-products-section">
                            <h5 class="section-subtitle">
                                <i class="pi pi-check-circle"></i>
                                Dispensed Products ({{ getDispensedProductCount(item) }})
                            </h5>
                            
                            <div class="dispensed-products-list">
                                <div v-for="(dispensedProduct, dpIndex) in getDispensedProductsDisplay(item)"
                                    :key="'process-dp-' + dpIndex" 
                                    class="dispensed-product-card">
                                    
                                    <div class="dispensed-product-info">
                                        <div class="detail-row">
                                            <i class="pi pi-box"></i>
                                            <span class="label">Title:</span>
                                            <span class="value">{{ dispensedProduct.title || 'N/A' }}</span>
                                        </div>
                                        <div class="detail-row">
                                            <i class="pi pi-tag"></i>
                                            <span class="label">ASIN:</span>
                                            <span class="value">{{ dispensedProduct.asin || 'N/A' }}</span>
                                        </div>
                                        <div class="detail-row highlight">
                                            <i class="pi pi-map-marker"></i>
                                            <span class="label">Location:</span>
                                            <span class="value">{{ dispensedProduct.warehouseLocation || 'N/A' }}</span>
                                        </div>
                                        <div v-if="dispensedProduct.serialNumber" class="detail-row">
                                            <i class="pi pi-hashtag"></i>
                                            <span class="label">Serial #:</span>
                                            <span class="value">{{ dispensedProduct.serialNumber }}</span>
                                        </div>
                                        <div v-if="dispensedProduct.rtCounter" class="detail-row">
                                            <i class="pi pi-hashtag"></i>
                                            <span class="label">RT Counter:</span>
                                            <span class="value">{{ dispensedProduct.rtCounter }}</span>
                                        </div>
                                        <div v-if="dispensedProduct.FNSKU" class="detail-row">
                                            <i class="pi pi-barcode"></i>
                                            <span class="label">FNSKU:</span>
                                            <span class="value">{{ dispensedProduct.FNSKU }}</span>
                                        </div>
                                    </div>

                                    <div class="dispensed-product-actions">
                                        <Button label="Not Found" 
                                            icon="pi pi-exclamation-triangle" 
                                            severity="warning"
                                            size="small" 
                                            text
                                            @click="markProductNotFound(dispensedProduct.product_id, item)" />
                                        <Button label="Cancel This Dispense" 
                                            icon="pi pi-times" 
                                            severity="danger"
                                            size="small" 
                                            text
                                            @click="cancelSingleDispensedProduct(dispensedProduct.product_id, item)" />
                                    </div>

                                    <Divider v-if="dpIndex < getDispensedProductsDisplay(item).length - 1" class="my-2" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Buttons -->
        <template #footer>
            <div class="footer-content">
                <div class="footer-info">
                    <span v-if="processingAutoDispense && canConfirmDispense">
                        <i class="pi pi-info-circle" style="color: #6366f1;"></i>
                        {{ Object.keys(selectedDispenseProducts).length }} product(s) selected
                    </span>
                </div>
                <div class="footer-actions">
                    <Button label="Close" 
                        icon="pi pi-times" 
                        size="small" 
                        severity="secondary"
                        outlined
                        @click="closeProcessModal" />

                    <!-- Auto Dispense Mode Buttons -->
                    <template v-if="processingAutoDispense">
                        <Button label="Back" 
                            icon="pi pi-arrow-left" 
                            severity="secondary"
                            size="small"
                            outlined
                            @click="cancelAutoDispenseProcess" />
                        <Button label="Confirm Dispense" 
                            icon="pi pi-check" 
                            severity="success"
                            size="small"
                            :disabled="!canConfirmDispense" 
                            @click="confirmAutoDispenseInProcess" />
                    </template>

                    <!-- Regular Process Mode Buttons -->
                    <template v-else>
                        <Button v-if="hasDispensedItems(currentProcessOrder)" 
                            label="Cancel Dispense" 
                            icon="pi pi-times"
                            severity="danger" 
                            size="small"
                            outlined
                            @click="cancelDispense(currentProcessOrder)" />
                        <Button v-if="currentOrderHasUnassignedItems" 
                            label="Auto Dispense Items" 
                            icon="pi pi-inbox"
                            severity="info" 
                            size="small"
                            @click="startAutoDispenseInProcess" />
                    </template>
                </div>
            </div>
        </template>
    </Dialog>

        <!-- Shipment Label Modal -->
        <div v-if="showShipmentLabelModal" class="modal shipmentLabel">
            <div class="modal-overlay" @click="closeShipmentLabelModal"></div>

            <div class="modal-content">
                <div class="modal-header">
                    <h2>Shipment Label</h2>
                    <button class="btn btn-modal-close" @click="closeShipmentLabelModal">&times;</button>
                </div>

                <div class="modal-body" style="padding-bottom: 5px">
                    <div class="order-section" v-for="order in selectedShipmentData" :key="order.platform_order_id">
                        <div class="left-container">
                            <div class="order-info">
                                <ul class="list-unstyled m-0">
                                    <li>
                                        <p><strong>Amazon Order Id: </strong>{{ order.platform_order_id }}</p>
                                        <p><strong>Customer Name: </strong>{{ order.BuyerName }}</p>
                                        <p><strong>Address: </strong>
                                        <ul class="list-unstyled m-0 d-flex flex-column align-items-end">
                                            <li>{{ order.address_line1 }},</li>
                                            <li>{{ order.city }}, {{ order.StateOrRegion }}</li>
                                            <li>{{ order.postal_code }}, {{ order.CountryCode }}</li>
                                        </ul>
                                        </p>
                                    </li>

                                    <hr>

                                    <li class="d-flex flex-column gap-2" v-if="order.items && order.items.length">
                                        <p><strong>Order Items: </strong></p>
                                        <div class="orderItems-container">
                                            <ul class="list-unstyled m-0" v-for="(item, index) in order.items"
                                                :key="index">
                                                <li>Order Item Id: <strong>{{ item.platform_order_item_id }}</strong>
                                                </li>
                                                <li>ASIN: <strong>{{ item.platform_asin }}</strong></li>
                                                <li>SKU: <strong>{{ item.platform_sku }}</strong></li>
                                                <li>Qty: <strong>{{ item.QuantityOrdered }}</strong></li>
                                                <li>Status: <strong class="badge"
                                                        :class="'status-' + item.order_status">{{
                                                            item.order_status }}</strong>
                                                </li>
                                                <li>Condition: <strong>{{ item.ConditionSubtypeId }} -
                                                        {{ item.ConditionId }}</strong></li>
                                                <li>Unit Price: <strong>${{ item.unit_price }}</strong></li>
                                                <li>Unit Tax: <strong>${{ item.unit_tax }}</strong></li>
                                            </ul>
                                        </div>
                                    </li>

                                    <hr>

                                    <div class="carrier-box">
                                        <div class="carrier-header">
                                            <strong>Carrier</strong>

                                            <!-- Button -->
                                            <button class="btn btn-outline-primary btn-sm carrier-btn" type="button"
                                                @click="openCarrierModal(order)">
                                                Select Carrier Option
                                            </button>

                                            <span v-if="selectedCarriers?.[order.platform_order_id]"
                                                class="carrier-pill ok">
                                                Selected
                                            </span>

                                            <span v-else-if="hasEligibleRates(order.platform_order_id)"
                                                class="carrier-pill warning">
                                                {{ (rateResultsByOrderId?.[order.platform_order_id] || []).length }}
                                                rates
                                            </span>

                                            <span v-else class="carrier-pill muted">
                                                Not loaded
                                            </span>
                                        </div>



                                        <!-- ✅ SELECTED CARRIER SUMMARY -->
                                        <div v-if="selectedCarriers?.[order.platform_order_id]"
                                            class="selected-carrier-summary">
                                            <div>
                                                <strong>Service:</strong>
                                                {{ selectedCarriers[order.platform_order_id].ShippingServiceName }}
                                            </div>

                                            <div>
                                                <strong>Rate:</strong>
                                                ${{ getRateAmount(selectedCarriers[order.platform_order_id]) }}
                                            </div>

                                            <div>
                                                <strong>Ship Date:</strong>
                                                {{ formatDatetext(selectedCarriers[order.platform_order_id].ShipDate) }}
                                            </div>

                                            <div>
                                                <strong>ETA:</strong>
                                                {{
                                                    formatDatetext(selectedCarriers[order.platform_order_id].EarliestEstimatedDeliveryDate)
                                                }}
                                                –
                                                {{
                                                    formatDatetext(selectedCarriers[order.platform_order_id].LatestEstimatedDeliveryDate)
                                                }}
                                            </div>
                                        </div>
                                    </div>

                                </ul>
                            </div>
                        </div>

                        <div class="right-container">
                            <form v-if="forms && forms[order.platform_order_id]" class="package-dimensions">
                                <fieldset>
                                    <label>Delivery Experience</label>
                                    <select class="form-control"
                                        v-model="forms[order.platform_order_id].deliveryExperience">
                                        <option value="DeliveryConfirmationWithoutSignature">Without Signature
                                        </option>
                                        <option value="DeliveryConfirmationWithSignature">With Signature</option>
                                        <option value="DeliveryConfirmationWithAdultSignature">With Adult Signature
                                        </option>
                                        <option value="NoTracking">No Tracking</option>
                                    </select>
                                </fieldset>

                                <fieldset>
                                    <label>Length</label>
                                    <input class="form-control" type="number"
                                        v-model="forms[order.platform_order_id].length" required />
                                </fieldset>

                                <fieldset>
                                    <label>Width</label>
                                    <input class="form-control" type="number"
                                        v-model="forms[order.platform_order_id].width" required />
                                </fieldset>

                                <fieldset>
                                    <label>Height</label>
                                    <input class="form-control" type="number"
                                        v-model="forms[order.platform_order_id].height" required />
                                </fieldset>

                                <fieldset>
                                    <label>Unit</label>
                                    <select class="form-control" v-model="forms[order.platform_order_id].dimensionUnit">
                                        <option value="inches">Inches</option>
                                        <option value="centimeters">Centimeters</option>
                                    </select>
                                </fieldset>

                                <fieldset>
                                    <label>Weight</label>
                                    <input class="form-control" type="number"
                                        v-model="forms[order.platform_order_id].weight" />
                                </fieldset>

                                <fieldset>
                                    <label>Weight Unit</label>
                                    <select class="form-control" v-model="forms[order.platform_order_id].weightUnit">
                                        <option value="pound" default>Pound</option>
                                        <option value="grams">Grams</option>
                                        <option value="ounces">Ounces</option>
                                    </select>
                                </fieldset>

                                <!-- <fieldset>
                                    <label>Currency Code</label>
                                    <input class="form-control" v-model="forms[order.platform_order_id].currency"
                                        placeholder="Optional" />
                                </fieldset> -->

                                <fieldset>
                                    <label>Ship By</label>
                                    <input class="form-control" type="datetime-local"
                                        v-model="forms[order.platform_order_id].shipBy" />
                                </fieldset>

                                <fieldset>
                                    <label>Deliver By</label>
                                    <input class="form-control" type="datetime-local"
                                        v-model="forms[order.platform_order_id].deliverBy" />
                                </fieldset>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="rates-note">
                    <small class="text-muted">
                        ⚠️ If you change any package details (dimensions, weight, delivery experience, or dates),
                        please click <strong>Get Rates</strong> again to refresh the carrier options.
                    </small>
                </div>

                <div class="modal-footer">
                    <button @click="getRates">Get Rates</button>
                    <button class="btn btn-primary" :disabled="!canBuyShipment" :title="buyShipmentDisabledReason"
                        @click="buyShipmentLabel">
                        Buy Shipment
                    </button>
                    <button @click="manualShipment">Manual Shipment</button>
                </div>
            </div>
        </div>

        <!-- Work History Modal with Pagination -->
        <Dialog v-model:visible="showWorkHistoryModal" modal header="Work History" :style="{ width: '95%' }" :pt="{
            root: { class: 'mobile-fullscreen-dialog' }
        }">
            <div>
                <div>
                    <Button class="d-md-none mb-2" icon="pi pi-filter" severity="info" size="small"
                        @click="toggleFilters" :label="showFilters ? 'Hide Filters' : 'Show Filters'" />
                    <form class="workhistory-filters" v-show="showFilters">
                        <!-- Sort By -->
                        <fieldset class="filter-field">
                            <label>Sort By</label>
                            <Select :options="sortOptions" optionLabel="label" optionValue="value"
                                v-model="workHistoryFilters.sortBy" fluid size="small" @change="fetchWorkHistory" />
                        </fieldset>

                        <!-- Start Date -->
                        <fieldset class="filter-field">
                            <label>Start Date & Time</label>
                            <InputText type="datetime-local" size="small" v-model="workHistoryFilters.startDate" fluid
                                @change="fetchWorkHistory" />
                        </fieldset>

                        <!-- End Date -->
                        <fieldset class="filter-field">
                            <label>End Date & Time</label>
                            <InputText type="datetime-local" size="small" v-model="workHistoryFilters.endDate" fluid
                                @change="fetchWorkHistory" />
                        </fieldset>

                        <!-- User -->
                        <fieldset class="filter-field">
                            <label>Select User</label>
                            <Select :options="userOptions" optionLabel="label" optionValue="value"
                                v-model="workHistoryFilters.userId" fluid size="small" @change="fetchWorkHistory" />
                        </fieldset>

                        <!-- Late Orders -->
                        <fieldset class="filter-field">
                            <label>Filter Late Orders</label>
                            <Select :options="lateOrderOptions" optionLabel="label" optionValue="value"
                                v-model="workHistoryFilters.lateOrders" fluid size="small" @change="fetchWorkHistory" />
                        </fieldset>

                        <!-- Search -->
                        <fieldset class="filter-field">
                            <label>Total Orders: <span class="text-primary">{{ workHistoryStats.totalOrders
                            }}</span></label>
                            <InputText type="text" size="small" placeholder="Search Order ID or ..."
                                v-model="workHistoryFilters.searchQuery" class="search-input" fluid
                                @input="fetchWorkHistory" />
                        </fieldset>

                        <!-- Export -->
                        <fieldset class="filter-field ">
                            <!-- <label></label> -->
                            <!-- <Button size="small" severity="info" icon="pi pi-file-export" label="Export Data"
                            @click="exportWorkHistory" /> -->
                            <Button icon="pi pi-file-export" severity="info" size="small" @click="exportWorkHistory"
                                label="Export Work History" />
                        </fieldset>
                    </form>

                </div>

                <div v-if="loading" class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i> Loading work
                    history...
                </div>
                <div v-else-if="error" class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> {{ error }}
                    <button class="btn btn-retry" @click="fetchWorkHistory">
                        <i class="fas fa-redo"></i> Retry
                    </button>
                </div>

                <div v-else-if="workHistory && workHistory.length > 0">
                    <XDataTable :value="workHistory" :columns="historyColumns" :paginator="false" scrollable
                        scrollHeight="600px" tableClass="mt-4 desktop-view">
                        <!----Header Slots---->
                        <template #carrierHeader>
                            <div class="w-100">
                                <p class="fw-semibold">Carrier</p>
                                <Select :options="carrierOptions" v-model="workHistoryFilters.carrierFilter"
                                    optionLabel="label" optionValue="value" @change="fetchWorkHistory" fluid
                                    size="small" placeholder="Select Carrier" />
                            </div>
                        </template>
                        <template #allStoresHeader>
                            <div class="w-100">
                                <p class="fw-semibold">All Stores</p>
                                <Select :options="allStoresHistoryOptions" v-model="workHistoryFilters.storeFilter"
                                    optionLabel="label" optionValue="value" @change="fetchWorkHistory" fluid
                                    size="small" placeholder="Select Store" />
                            </div>
                        </template>

                        <!----End Of Header Slots--->
                        <template #purchaseDate="{ data }">
                            <div class="d-flex flex-column gap-2">
                                <div>
                                    <p class="fw-semibold">Purchase Date:</p>
                                    <p>{{ getMainDate(data.orderInfo) }}</p>
                                </div>
                                <div>
                                    <p class="fw-semibold">Label Purchase Date:</p>
                                    <p>{{ getSubDate(data.orderInfo) }}</p>
                                </div>
                            </div>
                        </template>

                        <template #customerName="{ data }">
                            <p>{{ data.orderInfo.customer_name || "N/A" }}</p>
                        </template>
                        <template #orderedItems="{ data }">
                            <div
                                style="word-break: break-word; white-space: normal; overflow-wrap: break-word; flex: 1;">
                                <ul class="list-unstyled m-0" v-for="(item, itemIndex) in data.orderInfo.items || []"
                                    :key="itemIndex">
                                    <li>
                                        <strong>{{
                                            item.Title
                                        }}</strong>
                                    </li>
                                    <li>{{ item.ASIN }}</li>
                                    <li>{{ item.MSKU }}</li>
                                </ul>
                            </div>
                        </template>
                        <template #AmazonOrderId="{ data }">
                            <p>{{ data.orderInfo.AmazonOrderId }}</p>
                        </template>
                        <template #trackingid="{ data }">
                            <p>{{ data.orderInfo.trackingid }}</p>
                        </template>
                        <template #carrier="{ data }">
                            <p :class="getCarrierClass(
                                data.orderInfo
                                    .carrier ||
                                data.orderInfo
                                    .carrier_description
                            )
                                ">
                                {{
                                    getCarrierText(
                                        data.orderInfo
                                            .carrier ||
                                        data.orderInfo
                                            .carrier_description
                                    )
                                }}
                            </p>

                        </template>

                        <template #deliveryDate="{ data }">
                            <p class="mb-1">
                                <strong>Date Delivered:</strong>
                                {{
                                    getDeliveryStatus(
                                        data.orderInfo
                                    )
                                }}
                            </p>
                            <p class="mb-2">
                                <strong>Date Ship:</strong>
                                {{
                                    getDeliverySubDate(
                                        data.orderInfo
                                    )
                                }}
                            </p>

                        </template>

                        <template #dispensedFNSKU="{ data }">
                            <p> {{ getDispensedStatus(data.orderInfo) }}
                            </p>
                        </template>

                        <template #allStores="{ data }">
                            {{ data.orderInfo.strname || "N/A" }}
                        </template>
                        <template #remarks="{ data }">
                            {{ getRemarks(data.orderInfo) }}
                        </template>
                    </XDataTable>
                    <div class="work-history-mobile d-block d-md-none">
                        <div class="card mb-3" v-for="(historyItem, index) in workHistory" :key="index"
                            :style="{ fontSize: '14px' }">
                            <div class="card-body">
                                <!-- Purchase Dates -->
                                <p class="mb-1">
                                    <strong>Purchase Date:</strong>
                                    {{ getMainDate(historyItem.orderInfo) }}
                                </p>
                                <p class="mb-2">
                                    <strong>Label Purchase Date:</strong>
                                    {{ getSubDate(historyItem.orderInfo) }}
                                </p>

                                <!-- Customer Name -->
                                <p class="mb-2">
                                    <strong>Customer:</strong>
                                    {{
                                        historyItem.orderInfo
                                            .customer_name || "N/A"
                                    }}
                                </p>

                                <!-- Ordered Items -->
                                <div v-for="(item, itemIndex) in historyItem
                                    .orderInfo.items || []" :key="itemIndex" class="mb-2">
                                    <p>
                                        <strong>Item:</strong>
                                        {{ item.Title }}
                                    </p>
                                    <p>
                                        <strong>ASIN:</strong>
                                        {{ item.ASIN }}
                                    </p>
                                    <p>
                                        <strong>MSKU:</strong>
                                        {{ item.MSKU }}
                                    </p>
                                </div>

                                <!-- Amazon Order ID -->
                                <p class="mb-2">
                                    <strong>Order ID:</strong>
                                    {{
                                        historyItem.orderInfo.AmazonOrderId
                                    }}
                                </p>

                                <!-- Tracking and Carrier -->
                                <p class="mb-2">
                                    <strong>Tracking ID:</strong>
                                    {{
                                        historyItem.orderInfo.trackingid ||
                                        "N/A"
                                    }}
                                </p>
                                <p class="mb-2">
                                    <strong>Carrier:</strong>
                                    <span :class="getCarrierClass(
                                        historyItem.orderInfo
                                            .carrier ||
                                        historyItem.orderInfo
                                            .carrier_description
                                    )
                                        ">
                                        {{
                                            getCarrierText(
                                                historyItem.orderInfo
                                                    .carrier ||
                                                historyItem.orderInfo
                                                    .carrier_description
                                            )
                                        }}
                                    </span>
                                </p>

                                <!-- Delivery Info -->
                                <p class="mb-1">
                                    <strong>Date Delivered:</strong>
                                    {{
                                        getDeliveryStatus(
                                            historyItem.orderInfo
                                        )
                                    }}
                                </p>
                                <p class="mb-2">
                                    <strong>Date Ship:</strong>
                                    {{
                                        getDeliverySubDate(
                                            historyItem.orderInfo
                                        )
                                    }}
                                </p>

                                <!-- FNSKU & Store -->
                                <p class="mb-2">
                                    <strong>Dispensed FNSKU:</strong>
                                    {{
                                        getDispensedStatus(
                                            historyItem.orderInfo
                                        )
                                    }}
                                </p>
                                <p class="mb-2">
                                    <strong>Store:</strong>
                                    {{
                                        historyItem.orderInfo.strname ||
                                        "N/A"
                                    }}
                                </p>

                                <!-- Remarks -->
                                <p class="mb-0">
                                    <strong>Remarks:</strong>
                                    {{ getRemarks(historyItem.orderInfo) }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="work-history-pagination" v-if="workHistory && workHistory.length > 0">
                        <div class="pagination-controls" v-if="workHistoryPagination.totalPages > 1">
                            <!-- First Button -->
                            <Button @click="goToWorkHistoryPage(1)" size=small outlined severity="secondary"
                                :disabled="workHistoryPagination.currentPage === 1">
                                <i class="pi pi-angle-left"></i>
                                <i class="pi pi-angle-left"></i>
                            </Button>

                            <!-- Previous Button -->
                            <Button @click="prevWorkHistoryPage" size=small outlined severity="secondary" :disabled="workHistoryPagination.currentPage === 1
                                ">
                                <i class="pi pi-angle-left"></i>
                            </Button>

                            <!-- Page Numbers -->
                            <div class="page-numbers">
                                <template v-for="page in visibleWorkHistoryPages" :key="page">
                                    <Button size="small" outlined severity="secondary" class="pagination-btn page-btn"
                                        :class="{ active: page === workHistoryPagination.currentPage }"
                                        @click="goToWorkHistoryPage(page)">
                                        {{ page }}
                                    </Button>
                                </template>
                            </div>

                            <!-- Next Button -->
                            <Button @click="nextWorkHistoryPage" size=small outlined severity="secondary" :disabled="workHistoryPagination.currentPage ===
                                workHistoryPagination.totalPages
                                ">
                                <i class="pi pi-angle-right"></i>
                            </Button>

                            <!-- Last Button -->
                            <Button size=small outlined severity="secondary" @click="
                                goToWorkHistoryPage(
                                    workHistoryPagination.totalPages
                                )
                                " :disabled="workHistoryPagination.currentPage ===
                                    workHistoryPagination.totalPages
                                    ">
                                <i class="pi pi-angle-right"></i>
                                <i class="pi pi-angle-right"></i>
                            </Button>
                        </div>
                    </div>
                </div>




                <ScrollFab targetSelector=".modal-content" bottomSelector=".work-history-pagination" />
            </div>
        </Dialog>


        <PrintInvoiceModal :visible="printInvoiceVisible" :order="selectedOrder" @close="closePrintInvoiceModal" />

        <!-- Manual Shipment Label Modal -->
        <ManualShipmentLabelModal :visible="manualShipmentLabelVisible" @close="closeManualShipmentLabelModal" />

        <!-- Manual Dispense Modal Component -->
        <ManualDispenseModal v-model:visible="showManualDispenseModal" :item="currentManualDispenseItem"
            :order-id="currentProcessOrder ? currentProcessOrder.outboundorderid : 0"
            @dispense-complete="handleManualDispenseComplete" />
        <ScrollTop />
    </div>

    <CarrierModal :visible="showCarrierModal" :order="carrierModalOrder"
        :eligible-rates="getEligibleRatesForOrder(carrierModalOrder?.platform_order_id)"
        :rejected-rates="getRejectedRatesForOrder(carrierModalOrder?.platform_order_id)"
        :selected-rate="selectedCarrierRateByOrderId?.[carrierModalOrder?.platform_order_id] || null"
        @close="closeCarrierModal" @select="handleCarrierSelected" />

    <PrintDocumentsModal :visible="showPrintDocumentsModal" :order-ids="selectedPlatformOrderIdsForPrint"
        :defaultLabelChecked="true" :defaultInvoiceChecked="false" @update:visible="showPrintDocumentsModal = $event"
        @print="handlePrintDocuments" />

    <ShipmentLabelHistory :visible="showShipmentLabelHistory" @close="closeShipmentLabelHistoryModal" />


</template>

<script>
import { Badge, Button, Dialog, Divider, InputText, Menu, Message, Panel, ScrollTop, Select, Tag, Textarea, Tooltip } from "primevue";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import fbmorder from "./fbmOrders.js";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";
import { ROWS_PER_PAGE } from "../../constant.js";
const TABLE_COLUMNS = [
    {
        header: "Order Details",
        slot: "orderDetails",
        headerStyle: "width: 25rem; min-width: 25rem; max-width: 25rem; padding: 0.25rem;",
        bodyStyle: "width: 25rem; min-width: 25rem; max-width: 25rem; padding: 0.25rem; font-size: 14px",
    },
    {
        header: "Product Details",
        slot: "productDetails",
        bodyStyle: "font-size: 14px",
        style: { minWidth: "20rem", maxWidth: "20rem" },
    },
    {
        header: "Order Type",
        bodyStyle: "font-size: 14px",
        slot: "orderType",
        style: { minWidth: "16rem" }
    },
    {
        header: "Order Status",
        bodyStyle: "font-size: 14px",
        slot: "orderStatus",
        style: { minWidth: "14rem" }
    }
]

const TABLE_HISTORY_COLUMNS = [
    {
        header: "Dates",
        field: "orderInfo",
        slot: "purchaseDate",
        bodyStyle: "font-size: 14px",
        style: { minWidth: "10rem" }
    },
    {
        header: "Customer Name",
        field: "customer_name",
        slot: "customerName",
        bodyStyle: "font-size: 14px",
    },
    {
        header: "Ordered Items (Title / ASIN / MSKU)",
        field: "orderInfo",
        slot: "orderedItems",
        bodyStyle: "font-size: 14px",
        style: { minWidth: "30rem" }
    },
    {
        header: "Amazon Order Id",
        field: "AmazonOrderId",
        slot: "AmazonOrderId",
        bodyStyle: "font-size: 14px"
    },
    {
        header: "Tracking Id",
        field: "trackingid",
        slot: "trackingid",
        bodyStyle: "font-size: 14px"
    },
    {
        field: "carrier",
        slot: "carrier",
        headerSlot: "carrierHeader",
        bodyStyle: "font-size: 14px",
        style: { minWidth: "10rem" }
    },
    {
        header: "Delivery Date",
        field: "deliveryDate",
        slot: "deliveryDate",
        bodyStyle: "font-size: 14px"
    },
    {
        header: "Dispensed FNSKU",
        field: "dispensedFNSKU",
        slot: "dispensedFNSKU",
        bodyStyle: "font-size: 14px"
    },
    {
        field: "allStores",
        slot: "allStores",
        headerSlot: "allStoresHeader",
        bodyStyle: "font-size: 14px"
    },
    {
        header: "Remarks",
        field: "remarks",
        slot: "remarks",
        bodyStyle: "font-size: 14px"
    }
]
export default {
    mixins: [fbmorder],
    components: {
        XDataTable,
        Button,
        Divider,
        Select,
        Badge,
        Tag,
        Message,
        InputText,
        Dialog,
        Textarea,
        Panel,
        ScrollTop,
        Tooltip,
        Menu,
        TitlePage,
        Tag,
        AnimateDiv
    },
    data() {
        return {
            columns: TABLE_COLUMNS,
            historyColumns: TABLE_HISTORY_COLUMNS,
            menuActions: [],
            upperMenuActions: [],
            currentActionItem: null,
            currentUpperActionItem: null,
            storeOptions: [
                {
                    "label": "All Stores",
                    "value": ''
                },
                {
                    "label": "Allrenewed",
                    "value": "Allrenewed"
                },
                {
                    "label": "Renovartech",
                    "value": "Renovartech"
                }
            ],
            statusOptions: [
                { value: "", label: "All Status" },
                { value: "Pending", label: "Pending" },
                { value: "Shipped", label: "Shipped" },
                { value: "Delivered", label: "Delivered" },
                { value: "Canceled", label: "Canceled" },
                { value: "Unshipped", label: "Unshipped" }
            ],
            sortOptions: [
                { value: "purchase_date", label: "Label Purchase Date (DESC)" },
                { value: "created_date", label: "Purchase Date" },
                { value: "delivery_date", label: "Delivery Date" },
            ],

            // ✅ ADD THESE NEW OPTIONS
            orderByOptions: [
                { value: "desc", label: "Newest First" },
                { value: "asc", label: "Oldest First" }
            ],
            // ✅ ADD THIS NEW STATE
            orderByFilter: "desc",

            userOptions: [
                { value: "all", label: "All Users" },
                { value: "Van", label: "Van" },
                { value: "Jundell", label: "Jundell" },
                { value: "Admin", label: "Admin" },
            ],
            lateOrderOptions: [
                { value: "", label: "All Orders" },
                { value: "late", label: "Late Orders Only" },
                { value: "ontime", label: "On Time Orders" },
            ],
            carrierOptions: [
                { value: "all", label: "All Status" },
                { value: "UPS", label: "UPS" },
                { value: "FEDEX", label: "FedEx" },
                { value: "USPS", label: "USPS" },
                { value: "DHL", label: "DHL" },
            ],
            allStoresHistoryOptions: [
                { value: "", label: "All Orders" },
                { value: "TestStore", label: "TestStore" },
                { value: "AllRenewed", label: "AllRenewed" },
            ],
            rowsPerPageOptions: ROWS_PER_PAGE,
        }

    },
    methods: {
        onSelectionChange(order, isSelected) {
            order.checked = isSelected;
            this.handleOrderCheckChange(order);
        },
        onAllSelectionChange(selectedRows, isSelectAll) {
            this.selectAll = isSelectAll
            this.toggleAll()
        },
        toggle(event, item) {
            this.currentActionItem = item;
            this.menuActions = this.getMoreActionItems(this.currentActionItem);
            if (this.$refs.menu) {
                this.$refs.menu.toggle(event);
            }
        },
        toggleUpperMenuButton(event) {
            this.upperMenuActions = this.getMoreUpperActionItems(this.currentUpperActionItem);
            if (this.$refs.upperMenu) {
                this.$refs.upperMenu.toggle(event);
            }
        },
        getMoreActionItems(item) {
            return [
                {
                    label: 'Track',
                    icon: 'pi pi-compass',
                },
                {
                    label: 'Tracking History',
                    icon: 'pi pi-history',
                }
            ]
        },
        getMoreUpperActionItems() {
            return [
                {
                    label: "Shipment Label History",
                    icon: "pi pi-history",
                    command: () => this.openShipmentLabelHistoryModal(),
                },
                {
                    label: 'Work History',
                    icon: 'pi pi-chart-line',
                    command: () => this.openWorkHistoryModal(),
                },
                {
                    label: 'Purchase Shipping Label',
                    icon: 'pi pi-truck',
                    command: () => this.PurchaseShippingLabel(),
                    visible: this.persistentSelectedOrderIds.length > 0
                },
                {
                    label: 'Process Selected',
                    icon: 'pi pi-truck',
                    command: () => this.processSelectedOrders(),
                },
                {
                    label: 'Print Labels',
                    icon: 'pi pi-tag',
                    command: () => self.openPrintDocumentsModal(),
                },
                {
                    label: 'Generate Packing Slips',
                    icon: 'pi pi-file',
                    command: () => this.generatePackingSlips(),
                },
                {
                    label: 'Manual Shipment Label',
                    command: () => this.openManualShipmentLabelModal(),
                },
            ]
        },



        getStatusColor(status) {
            switch (status) {
                case 'Pending':
                    return "#037CA6"
                case 'Unshipped':
                    return "#FFCC00"
                case 'Shipped':
                    return '#47FF69'
                case 'Canceled':
                    return '#E30000'
                default:
                    return '#F1FF00'
            }
        }

    },
    mounted() {
        console.log(this.workHistoryFilters.carrierFilter, "carrierFilter"
        )
    },
};
</script>

<style scoped>
/* Process Modal Body */
.process-modal-body {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    padding: 0.5rem;
    max-height: 70vh;
    overflow-y: auto;
}

/* Info Banner (reusing Manual Dispense style) */
.info-banner {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    padding: 1.5rem;
    border-radius: 8px;
    color: white;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.info-col {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.info-label {
    font-size: 0.75rem;
    opacity: 0.9;
    text-transform: uppercase;
    font-weight: 600;
}

.info-value {
    font-size: 0.95rem;
    font-weight: 600;
}

.progress-tags {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* State Container */
.state-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    gap: 1rem;
}

.state-text {
    font-size: 1.1rem;
    color: #64748b;
    margin: 0;
}

/* Section Titles */
.section-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}

.section-subtitle {
    font-size: 0.95rem;
    font-weight: 600;
    color: #475569;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 0.75rem 0;
}

/* Dispense Products Grid */
.dispense-products-grid {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.dispense-item-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: 1.5rem;
}

.dispense-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.dispense-item-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 1;
}

/* Ordered Item Details */
.ordered-item-details {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1rem;
}

.detail-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    padding: 0.25rem 0;
}

.detail-row i {
    color: #94a3b8;
    font-size: 0.75rem;
    flex-shrink: 0;
}

.detail-row .label {
    color: #64748b;
    font-weight: 500;
    flex-shrink: 0;
}

.detail-row .value {
    color: #1e293b;
    font-weight: 600;
    margin-left: auto;
}

.detail-row.highlight .value {
    color: #6366f1;
}

/* Already Dispensed Section */
.already-dispensed-section {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1rem;
}

.dispensed-tags {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 0.5rem;
}

/* Product Selection Section */
.product-selection-section {
    margin-top: 1rem;
}

.product-slots {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.product-slot {
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 1rem;
    background: #fafafa;
}

.slot-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 0.75rem;
}

.product-options {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 0.75rem;
}

.product-option-card {
    position: relative;
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    padding: 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.product-option-card:hover {
    border-color: #6366f1;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
    transform: translateY(-2px);
}

.product-option-card.selected {
    border-color: #6366f1;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(139, 92, 246, 0.05) 100%);
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.2);
}

.selection-badge-small {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    font-size: 1.25rem;
    color: #cbd5e1;
    transition: all 0.2s;
}

.product-option-card.selected .selection-badge-small {
    color: #6366f1;
}

.product-option-content {
    padding-right: 2rem;
}

.product-option-header {
    margin-bottom: 0.75rem;
}

.product-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1e293b;
    display: block;
    margin-bottom: 0.25rem;
}

.product-id-badge {
    font-size: 0.75rem;
    color: #64748b;
    font-weight: 500;
}

.product-option-details {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.detail-mini {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
}

.detail-mini i {
    color: #94a3b8;
    font-size: 0.7rem;
}

.detail-mini span {
    color: #64748b;
}

.detail-mini.highlight span {
    color: #6366f1;
    font-weight: 600;
}

/* Fully Dispensed */
.fully-dispensed {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 1rem;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 6px;
    color: #16a34a;
    font-weight: 600;
}

/* Regular Process Section */
.order-items-grid {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.order-item-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: 1.5rem;
}

.item-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.item-title-section {
    flex: 1;
    min-width: 300px;
}

.item-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 0.5rem 0;
}

.item-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    font-size: 0.875rem;
    color: #64748b;
}

.item-meta-item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.item-status-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    align-items: flex-end;
}

.item-details-grid {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 6px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 0.5rem;
    margin-bottom: 1rem;
}

/* Dispensed Products Section */
.dispensed-products-section {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    padding: 1rem;
    border-radius: 6px;
    margin-top: 1rem;
}

.dispensed-products-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: 0.75rem;
}

.dispensed-product-card {
    background: white;
    padding: 1rem;
    border-radius: 6px;
}

.dispensed-product-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.dispensed-product-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Footer */
.footer-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.footer-info {
    flex: 1;
    font-size: 0.95rem;
    color: #64748b;
}

.footer-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

/* Scrollbar */
.process-modal-body::-webkit-scrollbar {
    width: 8px;
}

.process-modal-body::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}

.process-modal-body::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.process-modal-body::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.location-scanner-container {
    width: 100%;
    color: #ffffff;
    background: #16506b;
    background: linear-gradient(90deg, rgba(22, 80, 107, 1) 0%, rgba(30, 176, 91, 1) 50%, rgba(145, 130, 76, 1) 99%);
    padding: 1rem;
    border-radius: 1rem;
    margin-bottom: 2rem;
}

.scanner-details {
    display: flex;
    gap: 20px; 
}

.info-group h6 {
    margin-top: 1rem;
    color: #ffffff;
    font-weight: bold;
}

/* Responsive */
@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .product-options {
        grid-template-columns: 1fr;
    }
    
    .item-card-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .item-status-actions {
        align-items: stretch;
    }
    
    .item-details-grid {
        grid-template-columns: 1fr;
    }
    
    .dispensed-product-info {
        grid-template-columns: 1fr;
    }
    
    .footer-content {
        flex-direction: column;
        align-items: stretch;
    }
    
    .footer-actions {
        width: 100%;
    }
    
    .footer-actions button {
        flex: 1;
    }
}
</style>