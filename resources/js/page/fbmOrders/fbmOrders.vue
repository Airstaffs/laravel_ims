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

        <!-- Work History Modal - INLINE -->
        <div v-if="showWorkHistoryModal" class="modal workHistory">
            <div class="modal-overlay" @click="closeWorkHistoryModal"></div>

            <div class="modal-content">
                <div class="modal-header">
                    <h2>Work History</h2>
                    <button class="btn btn-modal-close" @click="closeWorkHistoryModal">&times;</button>
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
                        <!-- Structured display for your work history data -->
                        <div v-for="(historyItem, index) in workHistory" :key="index" class="work-history-order">
                            <div class="order-header">
                                <h3>{{ historyItem.orderInfo.AmazonOrderId }}</h3>
                                <div class="order-meta">
                                    <span class="customer">{{ historyItem.orderInfo.customer_name }}</span>
                                    <span class="store">{{ historyItem.orderInfo.strname }}</span>
                                </div>
                            </div>
                            
                            <div class="order-details">
                                <div class="order-detail-row">
                                    <strong>Purchase Date:</strong> {{ historyItem.orderInfo.datecreatedsheesh }}
                                </div>
                                <div class="order-detail-row">
                                    <strong>Label Purchase Date:</strong> {{ historyItem.orderInfo.purchaselabeldate }}
                                </div>
                                <div class="order-detail-row">
                                    <strong>Latest Ship Date:</strong> {{ historyItem.orderInfo.LatestShipDateoforder }}
                                </div>
                                <div class="order-detail-row">
                                    <strong>Delivery Date:</strong> {{ historyItem.orderInfo.datedeliveredsheesh }}
                                </div>
                                <div class="order-detail-row">
                                    <strong>Tracking ID:</strong> {{ historyItem.orderInfo.trackingid }}
                                </div>
                                <div class="order-detail-row">
                                    <strong>Carrier:</strong> {{ historyItem.orderInfo.carrier_description || historyItem.orderInfo.carrier }}
                                </div>
                                <div class="order-detail-row">
                                    <strong>Tracking Status:</strong> {{ historyItem.orderInfo.trackingstatus }}
                                </div>
                                <div v-if="historyItem.orderInfo.ordernote" class="order-detail-row">
                                    <strong>Order Note:</strong> {{ historyItem.orderInfo.ordernote }}
                                </div>
                            </div>

                            <!-- Order Items -->
                            <div v-if="historyItem.orderInfo.items && historyItem.orderInfo.items.length > 0" class="order-items">
                                <h4>Items ({{ historyItem.orderInfo.items.length }})</h4>
                                <div v-for="(item, itemIndex) in historyItem.orderInfo.items" :key="itemIndex" class="order-item">
                                    <div class="item-title">{{ item.Title }}</div>
                                    <div class="item-details">
                                        <span><strong>ASIN:</strong> {{ item.ASIN }}</span>
                                        <span><strong>SKU:</strong> {{ item.MSKU }}</span>
                                        <span><strong>Order Item ID:</strong> {{ item.OrderItemId }}</span>
                                        <span v-if="item.ProductID"><strong>Product ID:</strong> {{ item.ProductID }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Dispensed FNSKU -->
                            <div v-if="historyItem.orderInfo.dispensedFNSKU && historyItem.orderInfo.dispensedFNSKU.length > 0" class="dispensed-fnsku">
                                <h4>Dispensed FNSKU</h4>
                                <div class="fnsku-list">
                                    <span v-for="(fnsku, fnskuIndex) in historyItem.orderInfo.dispensedFNSKU" :key="fnskuIndex" class="fnsku-item">
                                        {{ fnsku }}
                                    </span>
                                </div>
                            </div>

                            <hr v-if="index < workHistory.length - 1" class="order-separator">
                        </div>
                    </div>
                    <div v-else class="no-data">
                        No work history available for the selected criteria.
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="closeWorkHistoryModal">Close</button>
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