<!--fbmoerders.vue -->
<!--fbmoerders.vue -->
<template>
    <div class="vue-container fbm-order-module">
        <!-- Top header bar with blue background -->
        <div class="top-header">
            <div class="header-buttons">
                <button class="btn" @click="openScannerModal" v-if="$refs.scanner">
                    <i class="fas fa-barcode"></i> Scan Items
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
        </div>

        <!-- Selection status bar - NEW COMPONENT -->
        <div class="selection-status-bar" v-if="persistentSelectedOrderIds.length > 0">
            <div class="selection-info">
                <i class="fas fa-check-square"></i> 
                <span>{{ persistentSelectedOrderIds.length }} order{{ persistentSelectedOrderIds.length > 1 ? 's' : '' }} selected across all pages</span>
                <button class="btn-clear-selection" @click="clearAllSelections">
                    <i class="fas fa-times"></i> Clear Selection
                </button>
            </div>
            <div class="selection-actions">
                <button class="btn-action" @click="processSelectedOrders">
                    <i class="fas fa-shipping-fast"></i> Process Selected
                </button>
                <button class="btn-action" @click="printShippingLabels">
                    <i class="fas fa-tag"></i> Print Labels
                </button>
                <button class="btn-action" @click="generatePackingSlips">
                    <i class="fas fa-file-alt"></i> Generate Packing Slips
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
                        <th class="sticky-header" colspan="2">
                            Order Details
                        </th>
                        <th>Product Details</th>
                        <th colspan="2">
                            Order Type
                        </th>
                        <th>
                            Order Status
                        </th>
                        <th colspan="2">
                            Action
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="9" class="text-center">
                            <div class="loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i> Loading...
                            </div>
                        </td>
                    </tr>
                    <tr v-else-if="orders.length === 0">
                        <td colspan="9" class="text-center">No orders found</td>
                    </tr>
                    <template v-else v-for="(order, index) in orders" :key="order.outboundorderid">
                        <tr :class="{'has-dispensed-items': hasDispensedItems(order)}">
                            <td class="sticky-col first-col">
                                <div class="checkbox-disabled-tooltip">
                                    <input type="checkbox" v-model="order.checked" @change="handleOrderCheckChange(order)" 
                                        :disabled="!canSelectOrder(order)" />
                                </div>
                            </td>
                            <!-- Order ID and Customer columns combined for Order Details -->
                            <td colspan="2" class="order-details-cell">
                                <div class="order-id">{{ order.platform_order_id }}</div>
                                <div>Customer name: {{ order.buyer_name || 'N/A' }}</div>
                                <div>Address: {{ formatAddress(order.address) }}</div>
                                <div>Fulfillment Channel: <span class="fbm-tag">{{ order.FulfillmentChannel }}</span></div>
                                <div>Amazon Order</div>
                                <div>{{ formatDate(order.purchase_date) }}</div>
                            </td>
                            <!-- Enhanced Product Details column with multiple dispensed products support -->
                            <td class="product-details-cell">
                                <div v-for="(item, itemIndex) in (order.items || [])" :key="itemIndex" class="product-item">
                                    <!-- Item title row with checkbox -->
                                    <div class="product-title-row">
                                      <div class="checkbox-disabled-tooltip">
                                          <input type="checkbox" :value="item.outboundorderitemid" v-model="dispenseItemsSelected" 
                                              class="item-dispense-checkbox" :disabled="!isItemDispensed(item)" />
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

                                    <div class="product-detail">Order Item ID: {{ item.platform_order_item_id || 'N/A' }}</div>
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
                                                Dispensed Products ({{ getDispensedProductCount(item) }}/{{ item.quantity_ordered }})
                                            </span>
                                        </div>
                                        
                                        <!-- Display all dispensed products -->
                                        <div v-for="(dispensedProduct, dpIndex) in getDispensedProductsDisplay(item)" 
                                             :key="'dp-' + dpIndex" class="dispensed-product-item">
                                            <div class="dispensed-detail">
                                                <strong>Product ID:</strong> {{ dispensedProduct.product_id }}
                                            </div>
                                            <div v-if="dispensedProduct.warehouseLocation" class="dispensed-detail">
                                                <strong>Location:</strong> {{ dispensedProduct.warehouseLocation }}
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
                                            <hr v-if="dpIndex < getDispensedProductsDisplay(item).length - 1" class="dispensed-separator">
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <!-- Order Type and Shipment columns combined -->
                            <td colspan="2" class="order-type-cell">
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
                            <td colspan="2">
                                <div class="action-cell">
                                    <select class="action-select">
                                        <option value="NULL">NULL</option>
                                    </select>
                                    
                                    <div class="action-buttons">
                                        <button class="btn-track">TRACK</button>
                                        <button class="btn-tracking-history">Tracking History</button>
                                        <button class="btn-shipping-label">Purchase Shipping Label</button>
                                        <button class="btn-invoice">Print Invoice</button>
                                        <button class="btn-edit-customer">Edit Customer Name</button>
                                        <button class="btn-edit-address">Edit Address</button>
                                        <button class="btn-edit-note">Edit Note</button>
                                        
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

                    <!-- Enhanced Mobile Products Display -->
                    <div class="mobile-products">
                        <div v-for="(item, itemIndex) in (order.items || [])" :key="itemIndex" class="mobile-product-item">
                            <!-- Mobile product title with checkbox -->
                            <div class="mobile-product-title-row">
                                <div class="checkbox-disabled-tooltip">
                                    <input type="checkbox" :value="item.outboundorderitemid" v-model="dispenseItemsSelected" 
                                        class="mobile-item-dispense-checkbox" :disabled="!isItemDispensed(item)" />
                                </div>
                                <div class="mobile-product-title">
                                    {{ item.platform_title }}
                                    <span v-if="item.quantity_ordered > 1" class="quantity-badge-mobile">
                                        Qty: {{ item.quantity_ordered }}
                                        <span v-if="isItemDispensed(item)"> ({{ getDispensedProductCount(item) }} dispensed)</span>
                                    </span>
                                </div>
                            </div>
                            <div class="mobile-product-details">
                                ASIN: {{ item.platform_asin }} | SKU: {{ item.platform_sku }}
                            </div>
                            <div class="mobile-product-condition">
                                Condition: {{ item.condition }} | Price: ${{ parseFloat(item.unit_price || 0).toFixed(2) }}
                            </div>
                            
                            <!-- Enhanced mobile dispensed products display -->
                            <div v-if="isItemDispensed(item)" class="mobile-product-dispense">
                                <div class="mobile-dispensed-header">
                                    Dispensed Products ({{ getDispensedProductCount(item) }})
                                </div>
                                <div v-for="(dispensedProduct, dpIndex) in getDispensedProductsDisplay(item)" 
                                     :key="'mobile-dp-' + dpIndex" class="mobile-dispensed-item">
                                    <div class="mobile-dispensed-detail">
                                        <strong>ID:</strong> {{ dispensedProduct.product_id }}
                                    </div>
                                    <div v-if="dispensedProduct.warehouseLocation" class="mobile-dispensed-detail">
                                        <strong>Loc:</strong> {{ dispensedProduct.warehouseLocation }}
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
                                </div>
                            </div>
                        </div>
                    </div>

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

                            <div v-if="selectedOrder && selectedOrder.items && selectedOrder.items.some(item => item.tracking_number)" class="tracking-info">
                                <h4>Tracking Information</h4>
                                <div v-for="(item, idx) in selectedOrder.items.filter(i => i.tracking_number)" :key="'tracking-'+idx" class="tracking-item">
                                    <div class="tracking-number">{{ item.tracking_number }}</div>
                                    <div class="tracking-status">{{ item.tracking_status || 'Status not available' }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Enhanced Order Actions Section -->
                        <div class="order-details-section">
                            <h3 class="section-title">Actions</h3>
                            <div class="order-actions">
                                <!-- Process Button -->
                                <button 
                                    v-if="selectedOrder.order_status !== 'Shipped' && 
                                         selectedOrder.order_status !== 'Canceled'"
                                    class="action-button process-button" 
                                    @click="openProcessModalFromDetails(selectedOrder)">
                                    <i class="fas fa-shipping-fast"></i> Process Order
                                </button>
                                
                                <!-- Auto Dispense Button - only show if there are unassigned items -->
                                <button 
                                    v-if="selectedOrder.items && selectedOrder.items.some(item => !isItemDispensed(item))"
                                    class="action-button auto-dispense-button" 
                                    @click="autoDispense(selectedOrder)">
                                    <i class="fas fa-box-open"></i> Auto Dispense Items
                                </button>
                                
                                <!-- Cancel Dispense Button - show if there are any dispensed items -->
                                <button 
                                    v-if="hasDispensedItems(selectedOrder)"
                                    class="action-button cancel-dispense-button" 
                                    @click="cancelDispense(selectedOrder)">
                                    <i class="fas fa-undo"></i> Cancel Dispense
                                </button>
                                
                                <button class="action-button packing-button" @click="generatePackingSlip(selectedOrder.outboundorderid)">
                                    <i class="fas fa-file-alt"></i> Generate Packing Slip
                                </button>
                                <button class="action-button label-button" @click="printShippingLabel(selectedOrder.outboundorderid)">
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
                                        <input type="checkbox" :value="item.outboundorderitemid" v-model="dispenseItemsSelected" 
                                            class="item-dispense-checkbox" :disabled="!isItemDispensed(item)" />
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
                                            <div class="item-value">${{ parseFloat(item.unit_price || 0).toFixed(2) }}</div>
                                        </div>
                                        <div class="item-info-row">
                                            <div class="item-label">Tax:</div>
                                            <div class="item-value">${{ parseFloat(item.unit_tax || 0).toFixed(2) }}</div>
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
                                                        <span class="dispensed-label">Product ID:</span>
                                                        <span class="dispensed-value">{{ dispensedProduct.product_id }}</span>
                                                    </div>
                                                    <div v-if="dispensedProduct.warehouseLocation" class="dispensed-row">
                                                        <span class="dispensed-label">Location:</span>
                                                        <span class="dispensed-value">{{ dispensedProduct.warehouseLocation }}</span>
                                                    </div>
                                                    <div v-if="dispensedProduct.serialNumber" class="dispensed-row">
                                                        <span class="dispensed-label">Serial #:</span>
                                                        <span class="dispensed-value">{{ dispensedProduct.serialNumber }}</span>
                                                    </div>
                                                    <div v-if="dispensedProduct.rtCounter" class="dispensed-row">
                                                        <span class="dispensed-label">RT Counter:</span>
                                                        <span class="dispensed-value">{{ dispensedProduct.rtCounter }}</span>
                                                    </div>
                                                    <div v-if="dispensedProduct.FNSKU" class="dispensed-row">
                                                        <span class="dispensed-label">FNSKU:</span>
                                                        <span class="dispensed-value">{{ dispensedProduct.FNSKU }}</span>
                                                    </div>
                                                    <hr v-if="dpIndex < getDispensedProductsDisplay(item).length - 1" class="dispensed-separator">
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
        
        <div v-for="(dispenseItem, index) in dispenseProducts" :key="'dispense-'+index" class="dispense-item">
            <div class="ordered-item-details">
                <h4>Ordered Item</h4>
                <div class="ordered-item-title">
                    {{ dispenseItem.ordered_item.platform_title }}
                    <!-- Add quantity information -->
                    <span class="quantity-info">
                        Quantity: {{ dispenseItem.quantity_ordered }} 
                        ({{ dispenseItem.quantity_dispensed }} dispensed, {{ dispenseItem.quantity_remaining }} remaining)
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
                        <span v-for="(productId, idx) in dispenseItem.already_dispensed_products" :key="'dispensed-'+idx" 
                              class="dispensed-id-tag">
                            Product ID: {{ productId }}
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Only show product selection if more quantities are needed -->
            <div v-if="dispenseItem.quantity_remaining > 0" class="matching-products-list">
                <h4>Select {{ dispenseItem.quantity_remaining }} More Product{{ dispenseItem.quantity_remaining > 1 ? 's' : '' }}</h4>
                <div class="fifo-note">
                    <i class="fas fa-info-circle"></i> Products are sorted by stockroom date (oldest first)
                </div>
                
                <div v-if="dispenseItem.matching_products.length === 0" class="no-matches-for-item">
                    No matching products for this item
                </div>
                
                <div v-else class="matching-product-options">
                    <!-- Create a selection area for each needed quantity -->
                    <div v-for="slot in dispenseItem.quantity_remaining" :key="'slot-'+slot" class="product-selection-slot">
                        <h5>Selection {{ slot }}</h5>
                        <div class="matching-product-options">
                            <div v-for="(product, prodIndex) in dispenseItem.matching_products" 
                                :key="'product-'+prodIndex"
                                :class="['matching-product', 
                                          selectedDispenseProducts[`${dispenseItem.item_id}-${slot-1}`] && 
                                          selectedDispenseProducts[`${dispenseItem.item_id}-${slot-1}`].ProductID === product.ProductID 
                                          ? 'selected' : '']"
                                @click="selectDispenseProduct(dispenseItem.item_id, slot-1, product)">
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
                                <div v-for="(item, idx) in (currentProcessOrder && currentProcessOrder.items ? currentProcessOrder.items : [])" :key="idx" class="process-item">
                                    <div class="process-item-details">
                                        <div class="process-item-title">{{ item.platform_title }}</div>
                                        <div class="process-item-info">
                                            ASIN: {{ item.platform_asin }} | SKU: {{ item.platform_sku }} | Qty: {{ item.quantity_ordered }}
                                            <span v-if="isItemDispensed(item)"> ({{ getDispensedProductCount(item) }} dispensed)</span>
                                        </div>
                                        <div class="process-item-info">
                                            Order Item ID: {{ item.platform_order_item_id }}
                                        </div>
                                        
                                        <!-- Show multiple dispensed products if they exist -->
                                        <div v-if="isItemDispensed(item)" class="process-item-info product-id-info">
                                            <div v-for="(dispensedProduct, dpIndex) in getDispensedProductsDisplay(item)" 
                                                 :key="'process-dp-' + dpIndex" class="process-dispensed-product">
                                                <div><strong>Product ID:</strong> {{ dispensedProduct.product_id }}</div>
                                                <div v-if="dispensedProduct.warehouseLocation"><strong>Location:</strong> {{ dispensedProduct.warehouseLocation }}</div>
                                                <div v-if="dispensedProduct.serialNumber"><strong>Serial #:</strong> {{ dispensedProduct.serialNumber }}</div>
                                                <div v-if="dispensedProduct.rtCounter"><strong>RT Counter:</strong> {{ dispensedProduct.rtCounter }}</div>
                                                <div v-if="dispensedProduct.FNSKU"><strong>FNSKU:</strong> {{ dispensedProduct.FNSKU }}</div>
                                                <hr v-if="dpIndex < getDispensedProductsDisplay(item).length - 1" class="dispensed-separator">
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
                                <input type="text" v-model="processData.trackingNumber" class="form-control" placeholder="Enter tracking number..." />
                            </div>
                            <div class="form-group">
                                <label>Notes (optional):</label>
                                <textarea v-model="processData.notes" class="form-control" placeholder="Add notes about this process..."></textarea>
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
        <button class="btn-confirm-dispense" @click="confirmAutoDispenseInProcess" :disabled="!canConfirmDispense">
            <i class="fas fa-check"></i> Confirm Dispense
        </button>
    </template>
    
    <!-- Regular Process Mode Buttons -->
    <template v-else>
        <!-- Show Cancel Dispense button if there are dispensed items -->
        <button v-if="hasDispensedItems(currentProcessOrder)" 
                class="btn-cancel-dispense-in-process" 
                @click="cancelDispense(currentProcessOrder)">
            <i class="fas fa-undo"></i> Cancel Dispense
        </button>
        
        <!-- Auto Dispense button - only show if there are unassigned items -->
        <button class="btn-auto-dispense-from-process" 
                @click="startAutoDispenseInProcess" 
                v-if="currentOrderHasUnassignedItems">
            <i class="fas fa-box-open"></i> Auto Dispense Items
        </button>
    </template>
</div>
            </div>
        </div>
    </div>
</template>

<script>
    import fbmorder from "./fbmOrders.js";
    export default fbmorder;
</script>

<style>
/* Module-specific styles */
/* Complete CSS for FBM Order Module with Enhanced Multiple Product Display */

/* Module-specific styles */
.fbm-order-module .status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
}

.fbm-order-module .status-pending, .fbm-order-module .status-unshipped {
    background-color: #fff3cd;
    color: #856404;
}

.fbm-order-module .status-shipped {
    background-color: #d4edda;
    color: #155724;
}

.fbm-order-module .status-canceled {
    background-color: #f8d7da;
    color: #721c24;
}

.fbm-order-module .product-item {
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f0f0f0;
}

.fbm-order-module .product-title-row {
    display: flex;
    align-items: center;
    margin-bottom: 4px;
}

.fbm-order-module .product-title {
    font-weight: 600;
    margin-bottom: 2px;
    margin-left: 8px;
}

.fbm-order-module .product-details {
    font-size: 0.8rem;
    color: #666;
}

.fbm-order-module .product-detail {
    font-size: 0.85rem;
    margin-bottom: 3px;
    padding-left: 24px; /* Align with text after checkbox */
}

/* Item title row in details modal */
.fbm-order-module .item-title-row {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.fbm-order-module .item-title {
    font-weight: 600;
    font-size: 1.1rem;
    color: #333;
    margin-left: 8px;
}

/* Enhanced Styling for Multiple Dispensed Items */
.fbm-order-module .dispensed-item-details {
  border-left: 3px solid #17a2b8;
  padding: 12px;
  margin-top: 10px;
  background-color: #f8f9fa;
  border-radius: 6px;
  margin-left: 24px; /* Align with text after checkbox */
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.fbm-order-module .dispensed-header {
  margin-bottom: 10px;
  font-weight: bold;
  border-bottom: 1px solid #dee2e6;
  padding-bottom: 6px;
}

.fbm-order-module .product-id-badge {
  background-color: #d1ecf1;
  color: #0c5460;
  padding: 4px 10px;
  border-radius: 4px;
  display: inline-block;
  font-weight: 600;
  font-size: 0.85rem;
  margin-bottom: 8px;
}

/* Individual Dispensed Product Items */
.fbm-order-module .dispensed-product-item {
  background-color: #ffffff;
  border: 1px solid #e9ecef;
  border-radius: 4px;
  padding: 10px;
  margin-bottom: 8px;
  transition: box-shadow 0.2s ease;
}

.fbm-order-module .dispensed-product-item:hover {
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.fbm-order-module .dispensed-product-item:last-child {
  margin-bottom: 0;
}

.fbm-order-module .dispensed-detail {
  font-size: 0.8rem;
  color: #495057;
  margin-bottom: 4px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.fbm-order-module .dispensed-detail strong {
  color: #212529;
  font-weight: 600;
  min-width: 90px;
  margin-right: 8px;
}

/* Separator between dispensed products */
.fbm-order-module .dispensed-separator {
  border: none;
  border-top: 1px solid #dee2e6;
  margin: 8px 0;
  opacity: 0.7;
}

/* Enhanced Quantity Badge with Dispensed Count */
.fbm-order-module .quantity-badge {
  background-color: #e9ecef;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 0.75rem;
  margin-left: 6px;
  color: #495057;
  font-weight: 500;
  display: inline-block;
}

.fbm-order-module .dispensed-count {
  color: #28a745;
  font-weight: 600;
  margin-left: 4px;
}

/* Checkbox styles */
.fbm-order-module .item-dispense-checkbox {
    margin-right: 8px;
    cursor: pointer;
    width: 16px;
    height: 16px;
}

.fbm-order-module .mobile-item-dispense-checkbox {
    margin-right: 8px;
    cursor: pointer;
    width: 16px;
    height: 16px;
}

/* Mobile product title with checkbox */
.fbm-order-module .mobile-product-title-row {
    display: flex;
    align-items: center;
    margin-bottom: 5px;
}

.fbm-order-module .mobile-product-title {
    font-weight: 600;
    font-size: 0.9rem;
    margin-left: 8px;
}

/* Enhanced Mobile Dispensed Products Display */
.fbm-order-module .quantity-badge-mobile {
  display: block;
  font-size: 0.8rem;
  color: #6c757d;
  font-weight: normal;
  margin-top: 3px;
}

.fbm-order-module .mobile-product-dispense {
  background-color: #f8f9fa;
  border-left: 3px solid #17a2b8;
  padding: 8px;
  margin-top: 8px;
  border-radius: 4px;
  margin-left: 24px; /* Align with text after checkbox */
}

.fbm-order-module .mobile-dispensed-header {
  font-weight: 600;
  color: #0c5460;
  margin-bottom: 6px;
  font-size: 0.85rem;
  border-bottom: 1px solid #dee2e6;
  padding-bottom: 3px;
}

.fbm-order-module .mobile-dispensed-item {
  background-color: #ffffff;
  border-radius: 3px;
  padding: 6px;
  margin-bottom: 6px;
  border: 1px solid #e9ecef;
  box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.fbm-order-module .mobile-dispensed-item:last-child {
  margin-bottom: 0;
}

.fbm-order-module .mobile-dispensed-detail {
  font-size: 0.75rem;
  color: #6c757d;
  margin-bottom: 2px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.fbm-order-module .mobile-dispensed-detail strong {
  color: #495057;
  font-weight: 600;
  min-width: 50px;
  margin-right: 4px;
}

/* For the detail view in the modal */
.fbm-order-module .order-item .item-details-dispensed {
  background-color: #f8f9fa;
  border-left: 3px solid #17a2b8;
  padding: 12px;
  margin-top: 15px;
  border-radius: 6px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.fbm-order-module .item-dispensed-title {
  font-weight: 600;
  margin-bottom: 10px;
  color: #0c5460;
  font-size: 1rem;
  border-bottom: 1px solid #dee2e6;
  padding-bottom: 5px;
}

.fbm-order-module .item-dispensed-detail {
  display: block;
}

.fbm-order-module .dispensed-product-modal {
  background-color: #ffffff;
  border-radius: 4px;
  padding: 10px;
  margin-bottom: 10px;
  border: 1px solid #e9ecef;
  box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.fbm-order-module .dispensed-product-modal:last-child {
  margin-bottom: 0;
}

.fbm-order-module .dispensed-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}

.fbm-order-module .dispensed-label {
  color: #6c757d;
  font-weight: 500;
  font-size: 0.85rem;
  min-width: 80px;
}

.fbm-order-module .dispensed-value {
  font-weight: 600;
  color: #212529;
  font-size: 0.85rem;
}

.fbm-order-module .fifo-note {
  font-size: 0.8rem;
  color: #6c757d;
  font-style: italic;
  margin-bottom: 10px;
  padding: 8px;
  background-color: #f8f9fa;
  border-radius: 4px;
  border-left: 3px solid #17a2b8;
}

.fbm-order-module .fifo-note i {
  margin-right: 5px;
  color: #17a2b8;
}

.fbm-order-module .address-preview {
    font-size: 0.8rem;
    color: #666;
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* New styles for the updated layout */
.fbm-order-module table th {
    background-color: #f5f5f5;
    padding: 10px;
    text-align: left;
    border: 1px solid #ddd;
}

.fbm-order-module table td {
    padding: 10px;
    vertical-align: top;
    border: 1px solid #ddd;
}

.fbm-order-module .order-details-cell {
    background-color: #fff;
}

.fbm-order-module .fbm-tag {
    color: #ff0000;
    font-weight: bold;
}

.fbm-order-module .edit-small-btn {
    background-color: #f8f9fa;
    border: 1px solid #ddd;
    padding: 1px 4px;
    border-radius: 3px;
    font-size: 0.7em;
    margin-left: 5px;
    cursor: pointer;
}

/* Action column styles */
.fbm-order-module .action-cell {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.fbm-order-module .action-select {
    width: 100%;
    padding: 5px;
    margin-bottom: 10px;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.fbm-order-module .action-buttons {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.fbm-order-module .action-buttons button {
    padding: 5px 10px;
    text-align: left;
    background-color: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 3px;
    cursor: pointer;
}

.fbm-order-module .btn-track {
    background-color: #e3f2fd !important;
    color: #0d47a1;
    font-weight: bold;
}

.fbm-order-module .btn-process {
    background-color: #28a745 !important;
    color: white !important; 
    display: flex;
    align-items: center;
}

.fbm-order-module .btn-auto-dispense {
    background-color: #6f42c1 !important;
    color: white !important;
    display: flex;
    align-items: center;
}

.fbm-order-module .btn-cancel-dispense {
    background-color: #fd7e14 !important;
    color: white !important;
    display: flex;
    align-items: center;
}

.fbm-order-module .btn-process i,
.fbm-order-module .btn-cancel-dispense i,
.fbm-order-module .btn-auto-dispense i {
    margin-right: 5px;
}

/* Auto Dispense Modal styles */
.fbm-order-module .auto-dispense-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    z-index: 1000;
    display: flex;
    justify-content: center;
    align-items: center;
}

.fbm-order-module .auto-dispense-modal-content {
    background-color: #fff;
    border-radius: 5px;
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    overflow-y: auto;
}

.fbm-order-module .auto-dispense-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #ddd;
}

.fbm-order-module .auto-dispense-modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
}

.fbm-order-module .auto-dispense-modal-body {
    padding: 15px;
}

.fbm-order-module .auto-dispense-modal-footer {
    padding: 15px;
    border-top: 1px solid #ddd;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.fbm-order-module .dispense-item {
    border: 1px solid #ddd;
    border-radius: 5px;
    margin-bottom: 15px;
    padding: 15px;
}

.fbm-order-module .ordered-item-details {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.fbm-order-module .matching-product {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.fbm-order-module .matching-product:hover {
    background-color: #f5f5f5;
    border-color: #adb5bd;
}

.fbm-order-module .matching-product.selected {
    border-color: #28a745;
    background-color: #f8fff8;
    box-shadow: 0 2px 4px rgba(40, 167, 69, 0.2);
}

.fbm-order-module .loading-dispense,
.fbm-order-module .no-matching-products {
    text-align: center;
    padding: 20px;
}

.fbm-order-module .btn-confirm-dispense {
    background-color: #6f42c1;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.fbm-order-module .btn-confirm-dispense:hover {
    background-color: #5a2d91;
}

.fbm-order-module .btn-confirm-dispense:disabled {
    background-color: #a084d0;
    cursor: not-allowed;
}

/* Enhanced Cancel Dispense Button Styles */
.fbm-order-module .btn-cancel-dispense-in-process {
    background-color: #fd7e14;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    margin-right: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    transition: background-color 0.2s ease;
}

.fbm-order-module .btn-cancel-dispense-in-process:hover {
    background-color: #e8630e;
}

.fbm-order-module .btn-cancel-dispense-in-process i {
    margin-right: 5px;
}

.fbm-order-module .cancel-dispense-button {
    background-color: #fd7e14;
    color: white;
    grid-column: span 2;
}

.fbm-order-module .cancel-dispense-button:hover {
    background-color: #e8630e;
}

/* Mobile view styles */
@media (max-width: 768px) {
    .fbm-order-module .mobile-actions {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 5px;
    }
    
    .fbm-order-module .mobile-btn {
        padding: 8px;
        font-size: 0.8rem;
        text-align: center;
        border-radius: 3px;
        border: none;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .fbm-order-module .mobile-btn:nth-child(1) {
        background-color: #007bff;
    }
    
    .fbm-order-module .mobile-btn:nth-child(2) {
        background-color: #28a745;
    }
    
    .fbm-order-module .mobile-btn i {
        margin-right: 3px;
        font-size: 0.7rem;
    }
    
    .fbm-order-module .mobile-btn:disabled {
        background-color: #6c757d;
        cursor: not-allowed;
        opacity: 0.65;
    }
    
    /* Enhanced mobile dispensed product display */
    .fbm-order-module .dispensed-detail {
        flex-direction: column;
        gap: 2px;
        align-items: flex-start;
    }
    
    .fbm-order-module .dispensed-detail strong {
        min-width: auto;
    }
    
    .fbm-order-module .mobile-dispensed-detail {
        flex-direction: column;
        gap: 1px;
        align-items: flex-start;
    }
    
    .fbm-order-module .mobile-dispensed-detail strong {
        min-width: auto;
    }
}

/* Integrated Auto Dispense Styles */
.fbm-order-module .process-auto-dispense-section {
  border-top: 1px solid #f0f0f0;
  padding-top: 15px;
  margin-top: 15px;
}

.fbm-order-module .btn-auto-dispense-from-process {
  background-color: #6f42c1;
  color: white;
  border: none;
  padding: 8px 15px;
  border-radius: 4px;
  margin-right: 10px;
  cursor: pointer;
  display: flex;
  align-items: center;
  transition: background-color 0.2s ease;
}

.fbm-order-module .btn-auto-dispense-from-process:hover {
    background-color: #5a2d91;
}

.fbm-order-module .btn-auto-dispense-from-process i,
.fbm-order-module .btn-back-to-process i,
.fbm-order-module .btn-confirm-dispense i,
.fbm-order-module .auto-dispense-button i {
  margin-right: 5px;
}

.fbm-order-module .auto-dispense-button {
  background-color: #6f42c1;
  color: white;
  border: none;
  padding: 8px 15px;
  border-radius: 4px;
  cursor: pointer;
  margin-bottom: 10px;
  transition: background-color 0.2s ease;
}

.fbm-order-module .auto-dispense-button:hover {
    background-color: #5a2d91;
}

.fbm-order-module .btn-back-to-process {
  background-color: #6c757d;
  color: white;
  border: none;
  padding: 8px 15px;
  border-radius: 4px;
  margin-right: 10px;
  cursor: pointer;
  display: flex;
  align-items: center;
  transition: background-color 0.2s ease;
}

.fbm-order-module .btn-back-to-process:hover {
    background-color: #5a6268;
}

.fbm-order-module .process-modal .matching-product {
  border: 1px solid #ddd;
  border-radius: 4px;
  padding: 10px;
  margin-bottom: 10px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.fbm-order-module .process-modal .matching-product:hover {
  background-color: #f5f5f5;
}

.fbm-order-module .process-modal .matching-product.selected {
  border-color: #28a745;
  background-color: #f8fff8;
}

.fbm-order-module .process-modal .loading-dispense,
.fbm-order-module .process-modal .no-matching-products {
  text-align: center;
  padding: 20px;
  font-style: italic;
  color: #6c757d;
}

.fbm-order-module .process-modal .loading-dispense i,
.fbm-order-module .process-modal .no-matching-products i {
  margin-right: 8px;
  color: #6c757d;
}

.fbm-order-module .process-modal .ordered-item-details {
  margin-bottom: 15px;
  padding-bottom: 10px;
  border-bottom: 1px solid #eee;
}

.fbm-order-module .process-modal .ordered-item-title {
  font-weight: 600;
  margin-bottom: 5px;
}

.fbm-order-module .process-modal .ordered-item-info {
  font-size: 0.85rem;
  color: #555;
}

.fbm-order-module .process-modal .matching-products-list {
  margin-bottom: 15px;
}

.fbm-order-module .process-modal .matching-product-title {
  font-weight: 600;
  margin-bottom: 5px;
}

.fbm-order-module .process-modal .matching-product-info {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  font-size: 0.8rem;
  gap: 5px;
}

.fbm-order-module .process-modal .matching-product-info > div {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.fbm-order-module .process-modal .dispense-item {
  border: 1px solid #ddd;
  border-radius: 5px;
  margin-bottom: 15px;
  padding: 15px;
  background-color: #f9f9f9;
}

/* Enhanced Process Modal Product Display */
.fbm-order-module .process-dispensed-product {
  background-color: #f8f9fa;
  border: 1px solid #e9ecef;
  border-radius: 4px;
  padding: 8px;
  margin-bottom: 8px;
}

.fbm-order-module .process-dispensed-product:last-child {
  margin-bottom: 0;
}

/* Style for product_id info */
.fbm-order-module .product-id-info {
  background-color: #e7f5ff;
  color: #004085;
  padding: 8px 12px;
  border-radius: 4px;
  display: block;
  font-weight: 500;
  margin-top: 8px;
  font-size: 0.8rem;
  border-left: 3px solid #007bff;
}

.fbm-order-module .product-id-info > div {
  margin-bottom: 3px;
}

.fbm-order-module .product-id-info div:last-child {
  margin-bottom: 0;
}

/* ====== MOBILE VIEW IMPROVEMENTS ====== */

/* Only apply these styles on mobile screens */
@media (max-width: 768px) {
  /* Better mobile display toggling */
  .fbm-order-module .desktop-view {
    display: none !important;
  }
  
  .fbm-order-module .mobile-view {
    display: block !important;
  }
  
  /* Better header layout on mobile */
  .fbm-order-module .top-header {
    flex-direction: column;
    padding: 10px;
  }
  
  .fbm-order-module .store-filter {
    flex-direction: column;
    width: 100%;
    gap: 10px;
    margin-top: 10px;
  }
  
  .fbm-order-module .store-filter label {
    margin-right: 5px;
    font-weight: 500;
  }
  
  .fbm-order-module .store-select,
  .fbm-order-module .status-select {
    width: 100%;
    padding: 8px;
    margin-bottom: 5px;
    border-radius: 4px;
    border: 1px solid #ccc;
  }
  
  .fbm-order-module .btn-refresh {
    width: 100%;
    justify-content: center;
    padding: 8px;
    border-radius: 4px;
    background-color: #0056b3;
    color: white;
    border: none;
    font-weight: 500;
  }
  
  /* Improved mobile card design */
  .fbm-order-module .mobile-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    margin-bottom: 15px;
    background-color: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
  }
  
  .fbm-order-module .mobile-card-header {
    display: flex;
    align-items: center;
    padding: 12px;
    background-color: #f5f5f5;
    border-bottom: 1px solid #ddd;
  }
  
  .fbm-order-module .mobile-order-id {
    font-weight: bold;
    flex-grow: 1;
    font-size: 1rem;
  }
  
  .fbm-order-module .mobile-status {
    padding: 5px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
  }
  
  .fbm-order-module .mobile-customer {
    padding: 12px;
    border-bottom: 1px solid #eee;
  }
  
  .fbm-order-module .mobile-customer-name {
    font-weight: 600;
    margin-bottom: 6px;
    font-size: 0.95rem;
  }
  
  .fbm-order-module .mobile-customer-address {
    font-size: 0.85rem;
    color: #555;
    line-height: 1.4;
  }
  
  /* Better mobile product display */
  .fbm-order-module .mobile-products {
    padding: 12px;
    border-bottom: 1px solid #eee;
  }
  
  .fbm-order-module .mobile-product-item {
    padding-bottom: 10px;
    margin-bottom: 10px;
    border-bottom: 1px solid #eee;
  }
  
  .fbm-order-module .mobile-product-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
  }
  
  .fbm-order-module .mobile-product-details {
    display: grid;
    grid-template-columns: 1fr;
    font-size: 0.85rem;
    color: #555;
    margin-bottom: 5px;
    margin-left: 24px; /* Align with text after checkbox */
  }
  
  .fbm-order-module .mobile-product-condition {
    font-size: 0.85rem;
    color: #555;
    margin-left: 24px; /* Align with text after checkbox */
  }
  
  /* Better order details display */
  .fbm-order-module .mobile-order-details {
    padding: 12px;
    border-bottom: 1px solid #eee;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
  }
  
  .fbm-order-module .mobile-detail {
    margin-bottom: 5px;
  }
  
  .fbm-order-module .mobile-detail-label {
    font-size: 0.75rem;
    color: #666;
    display: block;
    margin-bottom: 2px;
  }
  
  .fbm-order-module .mobile-detail-value {
    font-size: 0.85rem;
    font-weight: 500;
  }
  
  /* Improved action buttons */
  .fbm-order-module .mobile-actions {
    padding: 12px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
  }
  
  .fbm-order-module .mobile-btn {
    padding: 10px 8px;
    border-radius: 4px;
    font-weight: 500;
    font-size: 0.85rem;
    text-align: center;
    border: none;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: opacity 0.2s;
  }
  
  .fbm-order-module .mobile-btn i {
    margin-right: 5px;
  }
  
  /* Specific button colors */
  .fbm-order-module .mobile-btn:nth-child(1) {
    background-color: #17a2b8;
  }
  
  .fbm-order-module .mobile-btn:nth-child(2) {
    background-color: #28a745;
  }
  
  .fbm-order-module .mobile-btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
  }
  
  /* Better pagination on mobile */
  .fbm-order-module .pagination-wrapper {
    flex-direction: column;
    align-items: center;
  }
  
  .fbm-order-module .per-page-selector {
    margin-bottom: 10px;
  }
  
  .fbm-order-module .pagination {
    width: 100%;
    justify-content: space-between;
  }
}

/* ====== DETAILS MODAL IMPROVEMENTS ====== */

/* These styles apply to both mobile and desktop */
.fbm-order-module .order-details-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0,0,0,0.6);
  z-index: 1000;
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 15px;
}

.fbm-order-module .order-details-content {
  background-color: white;
  border-radius: 8px;
  width: 90%;
  max-width: 900px;
  height: 90vh;
  max-height: 900px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 5px 20px rgba(0,0,0,0.3);
}

.fbm-order-module .order-details-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 20px;
  background-color: #f8f9fa;
  border-bottom: 1px solid #dee2e6;
}

.fbm-order-module .order-details-header h2 {
  margin: 0;
  font-size: 1.5rem;
  color: #333;
}

.fbm-order-module .order-details-close {
  background: none;
  border: none;
  font-size: 1.8rem;
  color: #6c757d;
  cursor: pointer;
  padding: 0;
  line-height: 1;
}

.fbm-order-module .order-details-body {
  flex: 1;
  overflow-y: auto;
  padding: 20px;
}

.fbm-order-module .order-details-sections {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
  margin-bottom: 20px;
}

.fbm-order-module .order-details-section {
  background-color: #f8f9fa;
  border-radius: 8px;
  padding: 15px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.fbm-order-module .section-title {
  margin-top: 0;
  margin-bottom: 15px;
  font-size: 1.2rem;
  color: #333;
  padding-bottom: 8px;
  border-bottom: 2px solid #007bff;
}

.fbm-order-module .order-info-grid {
  display: grid;
  gap: 8px;
}

.fbm-order-module .info-row {
  display: grid;
  grid-template-columns: 130px 1fr;
  gap: 10px;
}

.fbm-order-module .info-label {
  font-weight: 600;
  color: #495057;
}

.fbm-order-module .info-value {
  color: #212529;
}

.fbm-order-module .customer-name {
  font-weight: 600;
  font-size: 1.1rem;
  margin-bottom: 5px;
}

.fbm-order-module .customer-address {
  white-space: pre-line;
  color: #495057;
  line-height: 1.4;
}

.fbm-order-module .tracking-info {
  margin-top: 15px;
  padding-top: 15px;
  border-top: 1px solid #dee2e6;
}

.fbm-order-module .tracking-info h4 {
  margin-top: 0;
  margin-bottom: 10px;
  color: #333;
  font-size: 1rem;
}

.fbm-order-module .tracking-item {
  background-color: white;
  padding: 10px;
  border-radius: 4px;
  margin-bottom: 8px;
  border: 1px solid #e9ecef;
}

.fbm-order-module .tracking-number {
  font-weight: 600;
  margin-bottom: 5px;
}

.fbm-order-module .tracking-status {
  color: #6c757d;
  font-size: 0.9rem;
}

.fbm-order-module .order-actions {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

.fbm-order-module .action-button {
  padding: 10px;
  border: none;
  border-radius: 4px;
  font-weight: 500;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  transition: background-color 0.2s;
}

.fbm-order-module .action-button i {
  font-size: 0.9rem;
}

.fbm-order-module .auto-dispense-button {
  background-color: #6f42c1;
  color: white;
  grid-column: span 2;
}

.fbm-order-module .auto-dispense-button:hover {
    background-color: #5a2d91;
}

.fbm-order-module .process-button {
  background-color: #28a745;
  color: white;
  grid-column: span 2;
}

.fbm-order-module .process-button:hover {
    background-color: #218838;
}

.fbm-order-module .packing-button,
.fbm-order-module .label-button {
  background-color: #17a2b8;
  color: white;
}

.fbm-order-module .packing-button:hover,
.fbm-order-module .label-button:hover {
    background-color: #138496;
}

.fbm-order-module .cancel-button {
  background-color: #dc3545;
  color: white;
  grid-column: span 2;
}

.fbm-order-module .cancel-button:hover {
    background-color: #c82333;
}

.fbm-order-module .order-items-section {
  background-color: #f8f9fa;
  border-radius: 8px;
  padding: 15px;
  margin-bottom: 20px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.fbm-order-module .order-item {
  background-color: white;
  border-radius: 5px;
  padding: 15px;
  margin-bottom: 15px;
  box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.fbm-order-module .order-item:last-child {
  margin-bottom: 0;
}

.fbm-order-module .item-details-grid {
  display: flex;
  gap: 20px;
}

.fbm-order-module .item-details-left {
  flex: 1;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px;
}

.fbm-order-module .item-info-row {
  display: flex;
  flex-direction: column;
  margin-bottom: 8px;
}

.fbm-order-module .item-label {
  font-size: 0.8rem;
  color: #6c757d;
  margin-bottom: 3px;
}

.fbm-order-module .item-value {
  font-weight: 500;
  color: #212529;
}

.fbm-order-module .order-notes-section {
  background-color: #f8f9fa;
  border-radius: 8px;
  padding: 15px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.fbm-order-module .order-notes pre {
  background-color: white;
  padding: 12px;
  border-radius: 5px;
  white-space: pre-wrap;
  margin: 0;
  font-family: inherit;
  font-size: 0.9rem;
  line-height: 1.5;
  box-shadow: 0 1px 2px rgba(0,0,0,0.1);
  color: #212529;
}

.fbm-order-module .order-details-footer {
  padding: 15px 20px;
  border-top: 1px solid #dee2e6;
  display: flex;
  justify-content: flex-end;
}

.fbm-order-module .close-details-button {
  background-color: #6c757d;
  color: white;
  border: none;
  padding: 8px 20px;
  border-radius: 4px;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.2s;
}

.fbm-order-module .close-details-button:hover {
  background-color: #5a6268;
}

/* Modal adjustments for mobile devices */
@media (max-width: 768px) {
  .fbm-order-module .order-details-content {
    width: 95%;
    height: 95vh;
  }
  
  .fbm-order-module .order-details-sections {
    grid-template-columns: 1fr;
  }
  
  .fbm-order-module .order-actions {
    grid-template-columns: 1fr;
  }
  
  .fbm-order-module .auto-dispense-button,
  .fbm-order-module .process-button,
  .fbm-order-module .cancel-dispense-button,
  .fbm-order-module .cancel-button,
  .fbm-order-module .packing-button,
  .fbm-order-module .label-button {
    grid-column: span 1;
  }
  
  .fbm-order-module .item-details-grid {
    flex-direction: column;
  }
  
  .fbm-order-module .item-details-left {
    grid-template-columns: 1fr;
  }
}

/* NEW STYLES FOR SELECTION SYSTEM */

/* Style for disabled checkboxes */
.fbm-order-module input[type="checkbox"]:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Style for checkbox tooltip */
.fbm-order-module .checkbox-disabled-tooltip {
  position: relative;
  display: inline-block;
}

.fbm-order-module .checkbox-disabled-tooltip:hover::after {
  content: "Cannot select orders without dispensed items";
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translateX(-50%);
  background-color: rgba(0, 0, 0, 0.8);
  color: white;
  padding: 5px 10px;
  border-radius: 4px;
  font-size: 12px;
  white-space: nowrap;
  z-index: 100;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
  animation: fadeIn 0.2s ease-in-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateX(-50%) translateY(5px);
  }
  to {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
  }
}

/* Item-specific tooltip message */
.fbm-order-module .product-title-row .checkbox-disabled-tooltip:hover::after,
.fbm-order-module .mobile-product-title-row .checkbox-disabled-tooltip:hover::after,
.fbm-order-module .item-title-row .checkbox-disabled-tooltip:hover::after {
  content: "Item must be dispensed before it can be selected";
}

/* Style for orders with dispensed items */
.fbm-order-module tr.has-dispensed-items {
  background-color: rgba(232, 244, 248, 0.3);
}

.fbm-order-module .mobile-card:has(.mobile-checkbox input[type="checkbox"]:not(:disabled)) {
  border-left: 3px solid #28a745;
}

/* Style for items that have dispense information */
.fbm-order-module .product-item:has(.item-dispense-checkbox:not(:disabled)) {
  background-color: rgba(232, 244, 248, 0.15);
  border-left: 3px solid #28a745;
  padding-left: 5px;
  margin-left: -8px;
}

.fbm-order-module .mobile-product-item:has(.mobile-item-dispense-checkbox:not(:disabled)) {
  background-color: rgba(232, 244, 248, 0.15);
  border-left: 3px solid #28a745;
  padding-left: 5px;
}

.fbm-order-module .order-item:has(.item-dispense-checkbox:not(:disabled)) {
  border-left: 3px solid #28a745;
}

/* Selection Status Bar Styles */
.fbm-order-module .selection-status-bar {
  background-color: #e8f4f8;
  padding: 10px 15px;
  margin: 10px 0;
  border-radius: 5px;
  border-left: 4px solid #17a2b8;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.fbm-order-module .selection-info {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 500;
  color: #333;
}

.fbm-order-module .selection-info i {
  color: #17a2b8;
}

.fbm-order-module .btn-clear-selection {
  background-color: transparent;
  border: none;
  color: #6c757d;
  font-size: 0.85rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 3px 8px;
  border-radius: 3px;
  transition: background-color 0.2s;
}

.fbm-order-module .btn-clear-selection:hover {
  background-color: rgba(0,0,0,0.05);
  color: #495057;
}

.fbm-order-module .selection-actions {
  display: flex;
  gap: 10px;
}

.fbm-order-module .btn-action {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 5px 10px;
  border: none;
  border-radius: 4px;
  background-color: #17a2b8;
  color: white;
  font-size: 0.85rem;
  cursor: pointer;
  transition: background-color 0.2s;
}

.fbm-order-module .btn-action:hover {
  background-color: #138496;
}

/* For mobile screens */
@media (max-width: 768px) {
  .fbm-order-module .selection-status-bar {
    flex-direction: column;
    gap: 10px;
  }
  
  .fbm-order-module .selection-actions {
    width: 100%;
    justify-content: space-between;
  }
  
  .fbm-order-module .btn-action {
    flex: 1;
    justify-content: center;
    font-size: 0.75rem;
    padding: 6px 8px;
  }
}

/* Enhanced Auto Dispense Features */
.fbm-order-module .product-selection-slot {
  border: 1px solid #ddd;
  border-radius: 5px;
  padding: 10px;
  margin-bottom: 10px;
  background-color: #ffffff;
}

.fbm-order-module .already-dispensed-section {
  margin-top: 10px;
  padding: 8px;
  background-color: #f0f0f0;
  border-radius: 4px;
  border-left: 3px solid #28a745;
}

.fbm-order-module .dispensed-id-tag {
  background-color: #e9ecef;
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 0.8rem;
  margin-right: 5px;
  display: inline-block;
  margin-bottom: 3px;
}

.fbm-order-module .fully-dispensed-message {
  text-align: center;
  padding: 15px;
  color: #28a745;
  font-weight: 600;
  background-color: #f8fff8;
  border-radius: 4px;
  border: 1px solid #d4edda;
}

.fbm-order-module .fully-dispensed-message i {
  margin-right: 8px;
  font-size: 1.2rem;
}

/* Process modal enhancements */
.fbm-order-module .process-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0,0,0,0.5);
  z-index: 1000;
  display: flex;
  justify-content: center;
  align-items: center;
}

.fbm-order-module .process-modal-content {
  background-color: #fff;
  border-radius: 8px;
  width: 90%;
  max-width: 900px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 5px 20px rgba(0,0,0,0.3);
}

.fbm-order-module .process-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 20px;
  border-bottom: 1px solid #ddd;
  background-color: #f8f9fa;
}

.fbm-order-module .process-modal-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: #6c757d;
}

.fbm-order-module .process-modal-body {
  padding: 20px;
}

.fbm-order-module .process-modal-footer {
  padding: 15px 20px;
  border-top: 1px solid #ddd;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  background-color: #f8f9fa;
}
</style>