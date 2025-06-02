<template>
    <div class="vue-container fbm-order-module">
        <!-- Top header bar with blue background -->
        <div class="top-header">
            <div class="header-buttons">

                <button class="btn" @click="openWorkHistoryModal">
                    <span>Work History</span>
                </button>
                <button v-if="persistentSelectedOrderIds.length > 0" class="btn" @click="PurchaseShippingLabel">
                    <i class="fas fa-shipping-fast"></i>
                    <span>Purchase Shipping Label</span>
                </button>
                <button class="btn" @click="processSelectedOrders">
                    <i class="fas fa-shipping-fast"></i>
                    <span>Process Selected</span>
                </button>
                <button class="btn" @click="printShippingLabels">
                    <i class="fas fa-tag"></i>
                    <span>Print Labels</span>
                </button>
                <button class="btn" @click="generatePackingSlips">
                    <i class="fas fa-file-alt"></i>
                    <span>Generate Packing Slips</span>
                </button>
                <button class="btn">Print Invoice</button>
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
        </div>

        <!-- Selection status bar - NEW COMPONENT -->
        <div class="selection-status-bar" v-if="persistentSelectedOrderIds.length > 0">
            <div class="selection-info">
                <i class="fas fa-check-square"></i>
                <span>{{ persistentSelectedOrderIds.length }} order{{ persistentSelectedOrderIds.length > 1 ? 's' : ''
                    }} selected across all pages</span>
                <button class="btn-clear-selection" @click="clearAllSelections">
                    <i class="fas fa-times"></i> Clear Selection
                </button>
            </div>
        </div>

        <h2 class="module-title">FBM Order Module</h2>

        <!-- Desktop Table Container -->
        <div class="table-container desktop-view">
            <table>
                <thead>
                    <tr>
                        <th class="sticky-header first-col">
                            <input type="checkbox" @click="toggleAll" v-model="selectAll" />
                        </th>
                        <th class="sticky-header second-sticky">
                            Order Details
                        </th>
                        <th>Product Details</th>
                        <th>
                            Order Type
                        </th>
                        <th>
                            Order Status
                        </th>
                        <th>
                            Action
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="6" class="text-center">
                            <div class="loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i> Loading...
                            </div>
                        </td>
                    </tr>
                    <tr v-else-if="orders.length === 0">
                        <td colspan="6" class="text-center">No orders found</td>
                    </tr>
                    <template v-else v-for="(order, index) in orders" :key="order.outboundorderid">
                        <tr :class="{ 'has-dispensed-items': hasDispensedItems(order) }">
                            <td class="sticky-col first-col">
                                <div class="checkbox-disabled-tooltip">
                                    <input type="checkbox" v-model="order.checked"
                                        @change="handleOrderCheckChange(order)" :disabled="!canSelectOrder(order)" />
                                </div>
                            </td>
                            <!-- Order ID and Customer columns combined for Order Details -->
                            <td class="sticky-col second-sticky">
                                <div class="order-id">{{ order.platform_order_id }}</div>
                                <div>Customer name: {{ order.buyer_name || 'N/A' }}</div>
                                <div>Address: {{ formatAddress(order.address) }}</div>
                                <div>Fulfillment Channel: <span class="fbm-tag">{{ order.FulfillmentChannel }}</span>
                                </div>
                                <div>Amazon Order</div>
                                <div>{{ formatDate(order.purchase_date) }}</div>
                            </td>
                            <!-- Enhanced Product Details column with multiple dispensed products support -->
                            <td class="product-details-cell">
                                <div v-for="(item, itemIndex) in (order.items || [])" :key="itemIndex"
                                    class="product-item">
                                    <!-- Item title row with checkbox -->
                                    <div class="product-title-row">
                                        <div class="checkbox-disabled-tooltip">
                                            <input type="checkbox" :value="item.outboundorderitemid"
                                                v-model="dispenseItemsSelected" class="item-dispense-checkbox"
                                                :disabled="!isItemDispensed(item)" />
                                        </div>
                                        <div class="product-title">
                                            {{ item.platform_title }}
                                            <!-- Enhanced quantity badge with dispensed count -->
                                            <span v-if="item.quantity_ordered > 1" class="quantity-badge">
                                                Qty: {{ item.quantity_ordered }}
                                                <span v-if="isItemDispensed(item)" class="dispensed-count">
                                                    ({{ getDispensedProductCount(item) }} dispensed)
                                                </span>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="product-detail">Order Item ID:
                                        {{ item.platform_order_item_id || 'N/A' }}
                                    </div>
                                    <div class="product-detail">
                                        Ordered ASIN: {{ item.platform_asin }}
                                        <button v-if="item.platform_asin" class="edit-small-btn">Edit</button>
                                    </div>
                                    <div class="product-detail">
                                        Ordered MSKU: {{ item.platform_sku }}
                                    </div>
                                    <div class="product-detail">
                                        Ordered Condition: {{ item.condition }}
                                    </div>
                                    <div class="product-detail">
                                        Item Price: ${{ parseFloat(item.unit_price || 0).toFixed(2) }}
                                    </div>
                                    <div class="product-detail">
                                        Item Tax: ${{ parseFloat(item.unit_tax || 0).toFixed(2) }}
                                    </div>

                                    <!-- Enhanced dispensed products display for multiple quantities -->
                                    <div v-if="isItemDispensed(item)" class="dispensed-item-details">
                                        <div class="dispensed-header">
                                            <span class="product-id-badge">
                                                Dispensed Products ({{ getDispensedProductCount(item) }}/{{
                                                    item.quantity_ordered }})
                                            </span>
                                        </div>

                                        <!-- Display all dispensed products -->
                                        <div v-for="(dispensedProduct, dpIndex) in getDispensedProductsDisplay(item)"
                                            :key="'dp-' + dpIndex" class="dispensed-product-item">
                                            <div class="dispensed-detail">
                                                <strong>Title:</strong> {{ dispensedProduct.title || 'N/A' }}
                                            </div>
                                            <div class="dispensed-detail">
                                                <strong>ASIN:</strong> {{ dispensedProduct.asin || 'N/A' }}
                                            </div>
                                            <div class="dispensed-detail">
                                                <strong>Location:</strong>
                                                {{ dispensedProduct.warehouseLocation || 'N/A' }}
                                            </div>
                                            <div v-if="dispensedProduct.serialNumber" class="dispensed-detail">
                                                <strong>Serial #:</strong> {{ dispensedProduct.serialNumber }}
                                            </div>
                                            <div v-if="dispensedProduct.rtCounter" class="dispensed-detail">
                                                <strong>RT Counter:</strong> {{ dispensedProduct.rtCounter }}
                                            </div>
                                            <div v-if="dispensedProduct.FNSKU" class="dispensed-detail">
                                                <strong>FNSKU:</strong> {{ dispensedProduct.FNSKU }}
                                            </div>
                                            <div class="dispensed-actions">
                                                <button class="btn-not-found"
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
                            </td>
                            <!-- Order Type and Shipment columns combined -->
                            <td class="order-type-cell">
                                <div>Order Type: {{ order.order_type || 'StandardOrder' }}</div>
                                <div>Shipment Service: {{ order.shipment_service || 'Standard' }}</div>
                                <div>
                                    Replacement Order: {{ order.is_replacement ? 'true' : 'false' }}
                                </div>
                                <div>
                                    Shipped by Date:
                                    {{ formatShipByDate(order.ship_date) }}
                                </div>
                                <div>
                                    Delivered by Date:
                                    {{ formatDeliveryDate(order.delivery_date) }}
                                </div>
                                <div v-if="hasTrackingNumber(order)">
                                    Tracking Status: {{ getTrackingStatus(order) }}
                                </div>
                            </td>
                            <!-- Order Status column -->
                            <td class="order-status-cell">
                                <div>Purchase label date:</div>
                                <div>
                                    Order Status:
                                    <span :class="getStatusClass(order.order_status)">
                                        {{ order.order_status }}
                                    </span>
                                </div>
                                <div>
                                    Ship Status: {{ getShipStatus(order) }}
                                </div>
                                <div>
                                    Store Name: {{ order.storename || 'N/A' }}
                                </div>
                            </td>
                            <!-- Action column -->
                            <td>
                                <div class="action-cell">
                                    <select class="action-select">
                                        <option value="NULL">NULL</option>
                                    </select>

                                    <div class="action-buttons">
                                        <button class="btn-track">TRACK</button>
                                        <button class="btn-tracking-history">Tracking History</button>

                                        <!-- Process Button (with integrated Auto Dispense) -->
                                        <button class="btn-process" @click="openProcessModal(order)"
                                            :disabled="order.order_status === 'Shipped' || order.order_status === 'Canceled'">
                                            <i class="fas fa-shipping-fast"></i> Process
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards View -->
        <div class="mobile-view">
            <div v-if="loading" class="loading-spinner-mobile">
                <i class="fas fa-spinner fa-spin"></i> Loading...
            </div>
            <div v-else-if="orders.length === 0" class="no-data-mobile">
                No orders found
            </div>
            <div v-else class="mobile-cards">
                <div v-for="(order, index) in orders" :key="order.outboundorderid" class="mobile-card">
                    <div class="mobile-card-header">
                        <div class="mobile-checkbox">
                            <div class="checkbox-disabled-tooltip">
                                <input type="checkbox" v-model="order.checked" @change="handleOrderCheckChange(order)"
                                    :disabled="!canSelectOrder(order)" />
                            </div>
                        </div>
                        <div class="mobile-order-id">{{ order.platform_order_id }}</div>
                        <div :class="['mobile-status', getStatusClass(order.order_status)]">
                            {{ order.order_status }}
                        </div>
                    </div>

                    <div class="mobile-customer">
                        <div class="mobile-customer-name">{{ order.buyer_name }}</div>
                        <div class="mobile-customer-address">{{ formatAddress(order.address) }}</div>
                    </div>

                    <hr>

                    <!-- Enhanced Mobile Products Display -->
                    <div class="mobile-products">
                        <div v-for="(item, itemIndex) in (order.items || [])" :key="itemIndex"
                            class="mobile-product-item">
                            <!-- Mobile product title with checkbox -->
                            <div class="mobile-product-title-row">
                                <div class="checkbox-disabled-tooltip">
                                    <input type="checkbox" :value="item.outboundorderitemid"
                                        v-model="dispenseItemsSelected" class="mobile-item-dispense-checkbox"
                                        :disabled="!isItemDispensed(item)" />
                                </div>
                                <div class="mobile-product-title">
                                    {{ item.platform_title }}
                                    <span v-if="item.quantity_ordered > 1" class="quantity-badge-mobile">
                                        Qty: {{ item.quantity_ordered }}
                                        <span v-if="isItemDispensed(item)"> ({{ getDispensedProductCount(item) }}
                                            dispensed)</span>
                                    </span>
                                </div>
                            </div>
                            <div class="mobile-product-details">
                                ASIN: {{ item.platform_asin }} | SKU: {{ item.platform_sku }}
                            </div>
                            <div class="mobile-product-condition">
                                Condition: {{ item.condition }} | Price: ${{ parseFloat(item.unit_price || 0).toFixed(2)
                                }}
                            </div>

                            <!-- Enhanced mobile dispensed products display -->
                            <div v-if="isItemDispensed(item)" class="mobile-product-dispense">
                                <div class="mobile-dispensed-header">
                                    Dispensed Products ({{ getDispensedProductCount(item) }})
                                </div>
                                <div v-for="(dispensedProduct, dpIndex) in getDispensedProductsDisplay(item)"
                                    :key="'mobile-dp-' + dpIndex" class="mobile-dispensed-item">
                                    <div class="mobile-dispensed-detail">
                                        <strong>Title:</strong> {{ dispensedProduct.title || 'N/A' }}
                                    </div>
                                    <div class="mobile-dispensed-detail">
                                        <strong>ASIN:</strong> {{ dispensedProduct.asin || 'N/A' }}
                                    </div>
                                    <div class="mobile-dispensed-detail">
                                        <strong>Loc:</strong> {{ dispensedProduct.warehouseLocation || 'N/A' }}
                                    </div>
                                    <div v-if="dispensedProduct.serialNumber" class="mobile-dispensed-detail">
                                        <strong>Serial:</strong> {{ dispensedProduct.serialNumber }}
                                    </div>
                                    <div v-if="dispensedProduct.rtCounter" class="mobile-dispensed-detail">
                                        <strong>RT:</strong> {{ dispensedProduct.rtCounter }}
                                    </div>
                                    <div v-if="dispensedProduct.FNSKU" class="mobile-dispensed-detail">
                                        <strong>FNSKU:</strong> {{ dispensedProduct.FNSKU }}
                                    </div>
                                    <div class="mobile-dispensed-actions">
                                        <button class="btn-not-found-mobile"
                                            @click="markProductNotFound(dispensedProduct.product_id, item)"
                                            title="Mark as not found">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="mobile-order-details">
                        <div class="mobile-detail">
                            <span class="mobile-detail-label">Purchase Date:</span>
                            <span class="mobile-detail-value">{{ formatDate(order.purchase_date) }}</span>
                        </div>
                        <div class="mobile-detail">
                            <span class="mobile-detail-label">Order Type:</span>
                            <span class="mobile-detail-value">{{ order.order_type }}</span>
                        </div>
                        <div class="mobile-detail">
                            <span class="mobile-detail-label">Shipment:</span>
                            <span class="mobile-detail-value">{{ order.shipment_service }}</span>
                        </div>
                    </div>

                    <hr>

                    <div class="mobile-actions">
                        <button class="mobile-btn" @click="viewOrderDetails(order)">
                            <i class="fas fa-info-circle"></i> Details
                        </button>

                        <button class="mobile-btn" @click="openProcessModal(order)"
                            :disabled="order.order_status === 'Shipped' || order.order_status === 'Canceled'">
                            <i class="fas fa-shipping-fast"></i> Process
                        </button>
                    </div>
                </div>
            </div>
        </div>

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
                                                        <span
                                                            class="dispensed-value">{{ dispensedProduct.title || 'N/A' }}</span>
                                                    </div>
                                                    <div class="dispensed-row">
                                                        <span class="dispensed-label">ASIN:</span>
                                                        <span
                                                            class="dispensed-value">{{ dispensedProduct.asin || 'N/A' }}</span>
                                                    </div>
                                                    <div class="dispensed-row">
                                                        <span class="dispensed-label">Location:</span>
                                                        <span
                                                            class="dispensed-value">{{ dispensedProduct.warehouseLocation || 'N/A' }}</span>
                                                    </div>
                                                    <div v-if="dispensedProduct.serialNumber" class="dispensed-row">
                                                        <span class="dispensed-label">Serial #:</span>
                                                        <span
                                                            class="dispensed-value">{{ dispensedProduct.serialNumber }}</span>
                                                    </div>
                                                    <div v-if="dispensedProduct.rtCounter" class="dispensed-row">
                                                        <span class="dispensed-label">RT Counter:</span>
                                                        <span
                                                            class="dispensed-value">{{ dispensedProduct.rtCounter }}</span>
                                                    </div>
                                                    <div v-if="dispensedProduct.FNSKU" class="dispensed-row">
                                                        <span class="dispensed-label">FNSKU:</span>
                                                        <span
                                                            class="dispensed-value">{{ dispensedProduct.FNSKU }}</span>
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
        <div v-if="showProcessModal" class="process-modal">
            <div class="process-modal-content">
                <div class="process-modal-header">
                    <h2>Process Order: {{ currentProcessOrder ? currentProcessOrder.platform_order_id : '' }}</h2>
                    <button class="process-modal-close" @click="closeProcessModal">&times;</button>
                </div>
                <div class="process-modal-body">
                    <!-- Auto Dispense Section - Show only when auto-dispensing within the process modal -->
                    <div v-if="processingAutoDispense" class="process-auto-dispense-section">
                        <div v-if="loadingDispenseProducts" class="loading-dispense">
                            <i class="fas fa-spinner fa-spin"></i> Searching for matching products...
                        </div>
                        <div v-else-if="dispenseProducts.length === 0" class="no-matching-products">
                            <i class="fas fa-exclamation-circle"></i> No matching products found in your inventory.
                        </div>
                        <div v-else class="matching-products">
                            <h3>Matching Products</h3>

                            <div v-for="(dispenseItem, index) in dispenseProducts" :key="'dispense-' + index"
                                class="dispense-item">
                                <div class="ordered-item-details">
                                    <h4>Ordered Item</h4>
                                    <div class="ordered-item-title">
                                        {{ dispenseItem.ordered_item.platform_title }}
                                        <!-- Add quantity information -->
                                        <span class="quantity-info">
                                            Quantity: {{ dispenseItem.quantity_ordered }}
                                            ({{ dispenseItem.quantity_dispensed }} dispensed,
                                            {{ dispenseItem.quantity_remaining }} remaining)
                                        </span>
                                    </div>
                                    <div class="ordered-item-info">
                                        ASIN: {{ dispenseItem.ordered_item.platform_asin }} |
                                        SKU: {{ dispenseItem.ordered_item.platform_sku }} |
                                        Condition: {{ getConditionDisplay(dispenseItem.ordered_item) }}
                                    </div>
                                    <div class="ordered-item-info">
                                        Order Item ID: {{ dispenseItem.ordered_item.platform_order_item_id }}
                                    </div>

                                    <!-- Show already dispensed products if any -->
                                    <div v-if="dispenseItem.quantity_dispensed > 0" class="already-dispensed-section">
                                        <h5>Already Dispensed Products ({{ dispenseItem.quantity_dispensed }})</h5>
                                        <div class="already-dispensed-ids">
                                            <span v-for="(productId, idx) in dispenseItem.already_dispensed_products"
                                                :key="'dispensed-' + idx" class="dispensed-id-tag">
                                                Product ID: {{ productId }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Only show product selection if more quantities are needed -->
                                <div v-if="dispenseItem.quantity_remaining > 0" class="matching-products-list">
                                    <h4>Select {{ dispenseItem.quantity_remaining }} More Product{{
                                        dispenseItem.quantity_remaining > 1 ? 's' : '' }}</h4>
                                    <div class="fifo-note">
                                        <i class="fas fa-info-circle"></i> Products are sorted by stockroom date (oldest
                                        first)
                                    </div>

                                    <div v-if="dispenseItem.matching_products.length === 0" class="no-matches-for-item">
                                        No matching products for this item
                                    </div>

                                    <div v-else class="matching-product-options">
                                        <!-- Create a selection area for each needed quantity -->
                                        <div v-for="slot in dispenseItem.quantity_remaining" :key="'slot-' + slot"
                                            class="product-selection-slot">
                                            <h5>Selection {{ slot }}</h5>
                                            <div class="matching-product-options">
                                                <div v-for="(product, prodIndex) in dispenseItem.matching_products"
                                                    :key="'product-' + prodIndex" :class="['matching-product',
                                                        selectedDispenseProducts[`${dispenseItem.item_id}-${slot - 1}`] &&
                                                            selectedDispenseProducts[`${dispenseItem.item_id}-${slot - 1}`].ProductID === product.ProductID
                                                            ? 'selected' : '']"
                                                    @click="selectDispenseProduct(dispenseItem.item_id, slot - 1, product)">
                                                    <div class="matching-product-title">{{ product.title }}</div>
                                                    <div class="matching-product-info">
                                                        <div>ASIN: {{ product.asin }}</div>
                                                        <div>MSKU: {{ product.msku }}</div>
                                                        <div>Condition: {{ product.condition }}</div>
                                                        <div>Product ID: {{ product.ProductID }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="fully-dispensed-message">
                                    <i class="fas fa-check-circle"></i> This item is fully dispensed
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Regular Process Section - Show when not auto-dispensing -->
                    <div v-else>
                        <div class="process-order-items">
                            <h3>Order Items</h3>
                            <div class="process-items-list">
                                <div v-for="(item, idx) in (currentProcessOrder && currentProcessOrder.items ? currentProcessOrder.items : [])"
                                    :key="idx" class="process-item">
                                    <div class="process-item-details">
                                        <div class="process-item-title">{{ item.platform_title }}</div>
                                        <div class="process-item-info">
                                            ASIN: {{ item.platform_asin }} | SKU: {{ item.platform_sku }} | Qty:
                                            {{ item.quantity_ordered }}
                                            <span v-if="isItemDispensed(item)"> ({{ getDispensedProductCount(item) }}
                                                dispensed)</span>
                                        </div>
                                        <div class="process-item-info">
                                            Order Item ID: {{ item.platform_order_item_id }}
                                        </div>

                                        <!-- Show multiple dispensed products if they exist -->
                                        <div v-if="isItemDispensed(item)" class="process-item-info product-id-info">
                                            <div v-for="(dispensedProduct, dpIndex) in getDispensedProductsDisplay(item)"
                                                :key="'process-dp-' + dpIndex" class="process-dispensed-product">
                                                <div><strong>Title:</strong> {{ dispensedProduct.title || 'N/A' }}</div>
                                                <div><strong>ASIN:</strong> {{ dispensedProduct.asin || 'N/A' }}</div>
                                                <div><strong>Location:</strong>
                                                    {{ dispensedProduct.warehouseLocation || 'N/A' }}
                                                </div>
                                                <div v-if="dispensedProduct.serialNumber">
                                                    <strong>Serial #:</strong> {{ dispensedProduct.serialNumber }}
                                                </div>
                                                <div v-if="dispensedProduct.rtCounter">
                                                    <strong>RT Counter:</strong> {{ dispensedProduct.rtCounter }}
                                                </div>
                                                <div v-if="dispensedProduct.FNSKU">
                                                    <strong>FNSKU:</strong> {{ dispensedProduct.FNSKU }}
                                                </div>
                                                <div class="process-dispensed-actions">
                                                    <button class="btn-not-found-process"
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

                        <div class="process-form">
                            <div class="form-group">
                                <label>Shipment Type:</label>
                                <select v-model="processData.shipmentType" class="form-control">
                                    <option value="Standard">Standard</option>
                                    <option value="Express">Express</option>
                                    <option value="Priority">Priority</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Tracking Number:</label>
                                <input type="text" v-model="processData.trackingNumber" class="form-control"
                                    placeholder="Enter tracking number..." />
                            </div>
                            <div class="form-group">
                                <label>Notes (optional):</label>
                                <textarea v-model="processData.notes" class="form-control"
                                    placeholder="Add notes about this process..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Enhanced Process Modal Footer with improved button logic -->
                <div class="process-modal-footer">
                    <button class="btn-cancel" @click="closeProcessModal">Close</button>

                    <!-- Auto Dispense Mode Buttons -->
                    <template v-if="processingAutoDispense">
                        <button class="btn-back-to-process" @click="cancelAutoDispenseProcess">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button class="btn-confirm-dispense" @click="confirmAutoDispenseInProcess"
                            :disabled="!canConfirmDispense">
                            <i class="fas fa-check"></i> Confirm Dispense
                        </button>
                    </template>

                    <!-- Regular Process Mode Buttons -->
                    <template v-else>
                        <!-- Show Cancel Dispense button if there are dispensed items -->
                        <button v-if="hasDispensedItems(currentProcessOrder)" class="btn-cancel-dispense-in-process"
                            @click="cancelDispense(currentProcessOrder)">
                            <i class="fas fa-undo"></i> Cancel Dispense
                        </button>

                        <!-- Auto Dispense button - only show if there are unassigned items -->
                        <button class="btn-auto-dispense-from-process" @click="startAutoDispenseInProcess"
                            v-if="currentOrderHasUnassignedItems">
                            <i class="fas fa-box-open"></i> Auto Dispense Items
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <!-- Work History Modal - EXACT DESIGN MATCH -->
        <div v-if="showWorkHistoryModal" class="modal workHistory show">
            <div class="modal-overlay" @click="closeWorkHistoryModal"></div>
            <div class="modal-content work-summary-modal">
                <div class="modal-header">
                    <h2><i class="fas fa-chart-line"></i> Work Summary</h2>
                    <button class="btn btn-modal-close" @click="closeWorkHistoryModal">&times;</button>
                </div>

                <!-- Work Summary Controls - Exact Layout -->
                <div class="work-summary-controls">
                    <!-- First Row - Main Controls -->
                    <div class="controls-first-row">
                        <div class="control-group">
                            <label>Sort By:</label>
                            <select v-model="workHistoryFilters.sortBy" @change="fetchWorkHistory" class="form-control">
                                <option value="purchase_date">Label Purchase Date (DESC)</option>
                                <option value="created_date">Purchase Date</option>
                                <option value="delivery_date">Delivery Date</option>
                            </select>
                        </div>

                        <div class="control-group">
                            <label>Start Date & Time:</label>
                            <input type="datetime-local" v-model="workHistoryFilters.startDate" @change="fetchWorkHistory" class="form-control">
                        </div>

                        <div class="control-group">
                            <label>End Date & Time:</label>
                            <input type="datetime-local" v-model="workHistoryFilters.endDate" @change="fetchWorkHistory" class="form-control">
                        </div>

                        <div class="control-group">
                            <label>Select User:</label>
                            <select v-model="workHistoryFilters.userId" @change="fetchWorkHistory" class="form-control">
                                <option value="all">All Users</option>
                                <option value="Van">Van</option>
                                <option value="Jundell">Jundell</option>
                                <option value="Admin">Admin</option>
                            </select>
                        </div>

                        <div class="control-group">
                            <label>Filter Late Orders:</label>
                            <select v-model="workHistoryFilters.lateOrders" @change="fetchWorkHistory" class="form-control">
                                <option value="">All Orders</option>
                                <option value="late">Late Orders Only</option>
                                <option value="ontime">On Time Orders</option>
                            </select>
                        </div>
                    </div>

                    <!-- Second Row - Stats and Actions -->
                    <div class="controls-second-row">
                        <div class="summary-stats-left">
                            <span class="total-orders">Total Orders: {{ workHistoryStats.totalOrders }}</span>
                            <input type="text" v-model="workHistoryFilters.searchQuery" @input="fetchWorkHistory" 
                                   placeholder="Search AmazonOrderId or ..." class="search-input">
                            <span class="carrier-breakdown">
                                <i class="fas fa-truck"></i> Carrier Breakdown
                            </span>
                        </div>
                        <div class="summary-stats-right">
                            <button class="btn btn-export" @click="exportWorkHistory">
                                <i class="fas fa-download"></i> Export Work History
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal-body">
                    <div v-if="loading" class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i> Loading work history...
                    </div>
                    <div v-else-if="error" class="error-message">
                        <i class="fas fa-exclamation-triangle"></i> {{ error }}
                        <button class="btn btn-retry" @click="fetchWorkHistory">
                            <i class="fas fa-redo"></i> Retry
                        </button>
                    </div>
                    <div v-else-if="workHistory && workHistory.length > 0" class="work-history-content">
                        <!-- Exact Table Design Match -->
                        <div class="work-history-table-container">
                            <table class="work-history-table">
                                <thead>
                                    <tr>
                                        <th class="col-purchase-date">
                                            <div class="th-content">
                                                <div class="th-main">Purchase Date</div>
                                                <div class="th-sub">Label Purchase Date</div>
                                            </div>
                                        </th>
                                        <th class="col-customer">Customer Name</th>
                                        <th class="col-items">
                                            <div class="th-content">
                                                <div class="th-main">Ordered Items</div>
                                                <div class="th-sub">(ASIN / Title / MSKU)</div>
                                            </div>
                                        </th>
                                        <th class="col-amazon-order">Amazon Order ID</th>
                                        <th class="col-tracking">Tracking ID</th>
                                        <th class="col-carrier">
                                            <div class="th-content">
                                                <div class="th-main">Carrier</div>
                                                <select v-model="workHistoryFilters.carrierFilter" @change="fetchWorkHistory" class="carrier-filter">
                                                    <option value="">All Statuses</option>
                                                    <option value="UPS">UPS</option>
                                                    <option value="FEDEX">FedEx</option>
                                                    <option value="USPS">USPS</option>
                                                    <option value="DHL">DHL</option>
                                                </select>
                                            </div>
                                        </th>
                                        <th class="col-delivery">
                                            <div class="th-content">
                                                <div class="th-main">Date Delivered</div>
                                                <div class="th-sub">by Date Ship Date</div>
                                            </div>
                                        </th>
                                        <th class="col-dispensed">Dispensed FNSKU</th>
                                        <th class="col-stores">
                                            <div class="th-content">
                                                <div class="th-main">All Stores</div>
                                                <select v-model="workHistoryFilters.storeFilter" @change="fetchWorkHistory" class="store-filter">
                                                    <option value="">All Stores</option>
                                                    <option value="TestStore">TestStore</option>
                                                    <option value="AllRenewed">AllRenewed</option>
                                                </select>
                                            </div>
                                        </th>
                                        <th class="col-remarks">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(historyItem, index) in workHistory" :key="index" class="work-history-row">
                                        <td class="col-purchase-date">
                                            <div class="date-cell">
                                                <div class="main-date">{{ getMainDate(historyItem.orderInfo) }}</div>
                                                <div class="sub-date">{{ getSubDate(historyItem.orderInfo) }}</div>
                                            </div>
                                        </td>
                                        <td class="col-customer">
                                            <span class="customer-link">{{ historyItem.orderInfo.customer_name || 'N/A' }}</span>
                                        </td>
                                        <td class="col-items">
                                            <div class="items-cell">
                                                <div v-for="(item, itemIndex) in (historyItem.orderInfo.items || [])" 
                                                     :key="itemIndex" class="item-entry">
                                                    <div class="item-indicator">|</div>
                                                </div>
                                                <div v-if="!historyItem.orderInfo.items || historyItem.orderInfo.items.length === 0" class="item-entry">
                                                    <div class="item-indicator">|</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="col-amazon-order">
                                            <span class="amazon-order-link">{{ historyItem.orderInfo.AmazonOrderId }}</span>
                                        </td>
                                        <td class="col-tracking">
                                            <span class="tracking-number">{{ historyItem.orderInfo.trackingid || 'N/A' }}</span>
                                        </td>
                                        <td class="col-carrier">
                                            <span :class="getCarrierClass(historyItem.orderInfo.carrier || historyItem.orderInfo.carrier_description)">
                                                {{ getCarrierText(historyItem.orderInfo.carrier || historyItem.orderInfo.carrier_description) }}
                                            </span>
                                        </td>
                                        <td class="col-delivery">
                                            <div class="delivery-cell">
                                                <div class="delivery-main">{{ getDeliveryStatus(historyItem.orderInfo) }}</div>
                                                <div class="delivery-sub">{{ getDeliverySubDate(historyItem.orderInfo) }}</div>
                                            </div>
                                        </td>
                                        <td class="col-dispensed">
                                            <span class="dispensed-status">{{ getDispensedStatus(historyItem.orderInfo) }}</span>
                                        </td>
                                        <td class="col-stores">
                                            <span class="store-link">{{ historyItem.orderInfo.strname || 'N/A' }}</span>
                                        </td>
                                        <td class="col-remarks">
                                            <span class="remarks-text">{{ getRemarks(historyItem.orderInfo) }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div v-else class="no-data">
                        No work history available for the selected criteria.
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-close-modal" @click="closeWorkHistoryModal">Close</button>
                </div>
            </div>
        </div>
    </div>

<!-- REMOVED THE PROBLEMATIC COMPONENT REFERENCES -->
</template>

<script>
    import fbmorder from "./fbmOrders.js";
    export default fbmorder;
</script>

<style>
/* Work History Modal Styles - Exact Design Match */
.modal.workHistory {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    background-color: rgba(0, 0, 0, 0.5);
    display: none;
}

.modal.workHistory.show {
    display: flex !important;
    align-items: center;
    justify-content: center;
}

.work-summary-modal {
    max-width: 98vw !important;
    width: 1600px !important;
    max-height: 95vh !important;
    height: 90vh !important;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    position: relative;
    background: white;
    border-radius: 8px;
    max-width: 90vw;
    max-height: 90vh;
    width: 900px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    display: flex;
    flex-direction: column;
    z-index: 10000;
}

.modal-header {
    background: #52c41a;
    color: white;
    padding: 12px 20px;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.modal-header h2 {
    margin: 0;
    font-size: 18px;
    font-weight: bold;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-modal-close {
    background: none;
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    padding: 4px;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 3px;
}

.btn-modal-close:hover {
    background: rgba(255, 255, 255, 0.2);
}

/* Work Summary Controls - Exact Layout */
.work-summary-controls {
    background: #f5f5f5;
    padding: 12px 16px;
    border-bottom: 1px solid #ddd;
    flex-shrink: 0;
}

.controls-first-row {
    display: flex;
    align-items: flex-end;
    gap: 12px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}

.controls-second-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.control-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.control-group label {
    font-size: 12px;
    font-weight: 600;
    color: #333;
    margin: 0;
}

.form-control {
    padding: 6px 8px;
    border: 1px solid #d9d9d9;
    border-radius: 4px;
    font-size: 12px;
    min-width: 140px;
    height: 28px;
}

.summary-stats-left {
    display: flex;
    align-items: center;
    gap: 15px;
}

.summary-stats-right {
    display: flex;
    align-items: center;
}

.total-orders {
    font-weight: bold;
    font-size: 13px;
    color: #333;
}

.search-input {
    padding: 6px 8px;
    border: 1px solid #d9d9d9;
    border-radius: 4px;
    font-size: 12px;
    width: 200px;
    height: 28px;
}

.carrier-breakdown {
    font-size: 13px;
    color: #333;
    display: flex;
    align-items: center;
    gap: 5px;
}

.btn-export {
    background: #1890ff;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    height: 28px;
}

.btn-export:hover {
    background: #096dd9;
}

.modal-body {
    padding: 0;
    overflow: hidden;
    flex: 1;
    min-height: 400px;
}

.modal-footer {
    padding: 12px 16px;
    border-top: 1px solid #ddd;
    display: flex;
    justify-content: flex-end;
    flex-shrink: 0;
}

.loading-spinner {
    text-align: center;
    padding: 40px;
    font-size: 16px;
    color: #666;
}

.loading-spinner i {
    font-size: 20px;
    margin-right: 8px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.error-message {
    text-align: center;
    padding: 40px;
    color: #e74c3c;
    font-size: 14px;
}

.btn-retry {
    background: #e74c3c;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    margin-left: 8px;
    cursor: pointer;
    font-size: 12px;
}

/* Work History Table - Exact Match */
.work-history-table-container {
    overflow: auto;
    height: calc(100% - 0px);
    border: 1px solid #e8e8e8;
}

.work-history-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    min-width: 1400px;
    background: white;
}

.work-history-table th {
    background: #fafafa;
    border: 1px solid #e8e8e8;
    padding: 8px 6px;
    text-align: left;
    font-weight: 600;
    position: sticky;
    top: 0;
    z-index: 10;
    font-size: 11px;
    color: #333;
}

.work-history-table td {
    border: 1px solid #e8e8e8;
    padding: 6px;
    vertical-align: top;
    font-size: 11px;
}

.th-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.th-main {
    font-weight: 600;
    color: #333;
}

.th-sub {
    font-size: 10px;
    color: #666;
    font-weight: normal;
}

/* Column Widths - Exact Match */
.col-purchase-date {
    width: 120px;
}

.col-customer {
    width: 100px;
}

.col-items {
    width: 80px;
}

.col-amazon-order {
    width: 140px;
}

.col-tracking {
    width: 140px;
}

.col-carrier {
    width: 100px;
}

.col-delivery {
    width: 120px;
}

.col-dispensed {
    width: 120px;
}

.col-stores {
    width: 100px;
}

.col-remarks {
    width: 80px;
}

/* Content Styles - Exact Match */
.date-cell {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.main-date {
    font-weight: 600;
    color: #333;
    font-size: 11px;
}

.sub-date {
    color: #666;
    font-size: 10px;
}

.customer-link {
    color: #1890ff;
    text-decoration: underline;
    cursor: pointer;
    font-size: 11px;
}

.customer-link:hover {
    color: #096dd9;
}

.items-cell {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.item-entry {
    display: flex;
    align-items: center;
}

.item-indicator {
    color: #52c41a;
    font-weight: bold;
    font-size: 14px;
}

.amazon-order-link {
    color: #fa8c16;
    text-decoration: underline;
    cursor: pointer;
    font-size: 11px;
    font-weight: 500;
}

.amazon-order-link:hover {
    color: #d46b08;
}

.tracking-number {
    font-family: 'Courier New', monospace;
    color: #333;
    font-size: 10px;
}

/* Carrier Colors - Exact Match */
.carrier-ups {
    color: #8b4513;
    font-weight: 600;
}

.carrier-fedex {
    color: #4b0082;
    font-weight: 600;
}

.carrier-usps {
    color: #0047ab;
    font-weight: 600;
}

.carrier-dhl {
    color: #ffcc00;
    background: #cc0000;
    padding: 1px 3px;
    border-radius: 2px;
    color: white;
    font-weight: 600;
}

.carrier-na, .carrier-other {
    color: #8b5cf6;
    font-weight: 600;
}

.delivery-cell {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.delivery-main {
    color: #52c41a;
    font-weight: 600;
    font-size: 11px;
}

.delivery-sub {
    color: #666;
    font-size: 10px;
}

.dispensed-status {
    color: #1890ff;
    font-size: 11px;
}

.store-link {
    color: #1890ff;
    text-decoration: underline;
    cursor: pointer;
    font-size: 11px;
}

.store-link:hover {
    color: #096dd9;
}

.remarks-text {
    color: #1890ff;
    font-size: 11px;
}

/* Filter Dropdowns in Headers */
.carrier-filter, .store-filter {
    width: 100%;
    padding: 2px 4px;
    border: 1px solid #d9d9d9;
    border-radius: 3px;
    font-size: 10px;
    margin-top: 3px;
    background: white;
}

.work-history-row:hover {
    background: #f0f9ff;
}

.work-history-row:nth-child(even) {
    background: #fafafa;
}

.work-history-row:nth-child(even):hover {
    background: #f0f9ff;
}

.no-data {
    text-align: center;
    padding: 60px 20px;
    color: #999;
    font-size: 16px;
}

.btn-close-modal {
    background: #95a5a6;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}

.btn-close-modal:hover {
    background: #7f8c8d;
}

/* Responsive Design */
@media (max-width: 1400px) {
    .work-summary-modal {
        width: 95vw !important;
        max-width: 95vw !important;
    }
    
    .controls-first-row {
        flex-wrap: wrap;
    }
    
    .controls-second-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .summary-stats-left {
        flex-wrap: wrap;
    }
}

@media (max-width: 1000px) {
    .work-summary-modal {
        width: 98vw !important;
        height: 95vh !important;
    }
    
    .controls-first-row {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .form-control {
        min-width: 120px;
    }
}
</style>