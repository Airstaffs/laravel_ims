<template>
    <!-- Split Modal -->
    <div 
        v-if="showModal" 
        class="modal split-modal"
        :class="{ show: showModal }"
        @click.self="closeModal($event)"
    >
        <div 
            class="modal-overlay" 
            @click.stop="closeModal($event)"
        ></div>
        
        <div 
            class="modal-content" 
            @click.stop
        >
            <div class="modal-header">
                <h3>Split Item into Individual Units</h3>
                <button 
                    class="btn btn-modal-close" 
                    @click.stop="closeModal($event)" 
                    type="button"
                    :disabled="isSplitting"
                >
                    &times;
                </button>
            </div>

            <div class="modal-body">
                <div v-if="splitItem" class="split-info">
                    <div class="split-product-info">
                        <h4>Product Information</h4>
                        <p><strong>RT Counter:</strong> {{ splitItem.rtcounter }}</p>
                        <p><strong>Product Title:</strong> {{ splitItem.ProductTitle || splitItem.AStitle }}</p>
                        <p><strong>Current Quantity:</strong> {{ splitItem.quantity }}</p>
                        
                        <!-- Enhanced price display for ALL THREE price fields -->
                        <div class="price-breakdown">
                            <h5>Price Information</h5>
                            <div v-if="getTotalPrice() > 0">
                                <div class="price-fields-breakdown">
                                    <div v-if="getPriceBreakdown().hasPrice" class="price-field-info">
                                        <div class="price-field-header">
                                            <i class="fas fa-tag"></i>
                                            <strong>Price Field</strong>
                                        </div>
                                        <p>Current Total: ${{ getPriceBreakdown().originalPrice.toFixed(2) }}</p>
                                        <p>Price per Unit: ${{ getPriceBreakdown().unitPrice.toFixed(2) }}</p>
                                    </div>
                                    
                                    <div v-if="getPriceBreakdown().hasPriceShipping" class="price-field-info">
                                        <div class="price-field-header">
                                            <i class="fas fa-shipping-fast"></i>
                                            <strong>Shipping Price Field</strong>
                                        </div>
                                        <p>Current Total: ${{ getPriceBreakdown().originalPriceShipping.toFixed(2) }}</p>
                                        <p>Price per Unit: ${{ getPriceBreakdown().unitPriceShipping.toFixed(2) }}</p>
                                    </div>

                                    <div v-if="getPriceBreakdown().hasTax" class="price-field-info">
                                        <div class="price-field-header">
                                            <i class="fas fa-receipt"></i>
                                            <strong>Tax Field</strong>
                                        </div>
                                        <p>Current Total: ${{ getPriceBreakdown().originalTax.toFixed(2) }}</p>
                                        <p>Tax per Unit: ${{ getPriceBreakdown().unitTax.toFixed(2) }}</p>
                                    </div>
                                </div>
                                
                                <div class="combined-total">
                                    <div class="price-field-header combined">
                                        <i class="fas fa-calculator"></i>
                                        <strong>Combined Total</strong>
                                    </div>
                                    <p><strong>Current Combined Total:</strong> ${{ getTotalPrice().toFixed(2) }}</p>
                                    <p><strong>Combined Price per Unit:</strong> ${{ calculateUnitPrice().toFixed(2) }}</p>
                                </div>
                            </div>
                            <div v-else class="no-price-warning">
                                <p class="text-warning">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    <strong>No price set</strong> - All price fields (price, shipping, tax) are empty or $0.00
                                </p>
                                <p class="text-muted small">Each split item will have $0.00 in all price fields</p>
                            </div>
                        </div>
                    </div>

                    <div class="split-details">
                        <h4>Split Operation Details</h4>
                        <p>This will split the item into <strong>{{ splitItem.quantity }}</strong> individual items:</p>
                        <ul>
                            <li>Original item (RT: {{ splitItem.rtcounter }}) will have quantity = 1</li>
                            <li>{{ parseInt(splitItem.quantity) - 1 }} new items will be created with new RT counters</li>
                            <li v-if="getTotalPrice() > 0" class="price-split-details">
                                Each item will have quantity = 1 with proportionally split prices:
                                <ul class="price-split-list">
                                    <li v-if="getPriceBreakdown().hasPrice">
                                        Price: ${{ getPriceBreakdown().unitPrice.toFixed(2) }}
                                    </li>
                                    <li v-if="getPriceBreakdown().hasPriceShipping">
                                        Shipping Price: ${{ getPriceBreakdown().unitPriceShipping.toFixed(2) }}
                                    </li>
                                    <li v-if="getPriceBreakdown().hasTax">
                                        Tax: ${{ getPriceBreakdown().unitTax.toFixed(2) }}
                                    </li>
                                    <li class="combined-price">
                                        <strong>Combined: ${{ calculateUnitPrice().toFixed(2) }}</strong>
                                    </li>
                                </ul>
                            </li>
                            <li v-else class="text-warning">
                                Each item will have quantity = 1 and all price fields = $0.00 (no price to split)
                            </li>
                            <li>All items will remain in the Labeling module</li>
                        </ul>
                    </div>

                    <div class="split-warning">
                        <p><strong>⚠️ Warning:</strong> This action cannot be undone. Are you sure you want to proceed?</p>
                        <p v-if="getTotalPrice() <= 0" class="text-warning">
                            <strong>Note:</strong> Since there is no price set, all split items will have $0.00 in all price fields.
                        </p>
                        <p v-else class="price-split-warning">
                            <strong>Price Split:</strong> Price, shipping price, and tax fields will all be divided equally among all units.
                        </p>
                    </div>
                </div>
                
                <!-- Loading indicator -->
                <div v-if="isSplitting" class="split-loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Splitting item, please wait...</span>
                </div>
            </div>

            <div class="modal-footer">
                <button 
                    class="btn-cancel" 
                    @click.stop="closeModal($event)" 
                    type="button" 
                    :disabled="isSplitting"
                >
                    Cancel
                </button>
                <button
                    class="btn-confirm btn-split"
                    @click.stop="performSplit"
                    type="button"
                    :disabled="isSplitting"
                >
                    <i v-if="isSplitting" class="fas fa-spinner fa-spin"></i>
                    {{ isSplitting ? 'Splitting...' : 'Yes, Split Item' }}
                </button>
            </div>
        </div>
    </div>
</template>
<script>
import splittingjs from "./splitting.js";
export default splittingjs;
</script>

<style scoped>
/* Enhanced Split Modal Price Display Styles */

.split-info {
    max-height: 60vh;
    overflow-y: auto;
    padding-right: 10px;
}

.split-product-info {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.price-breakdown {
    background-color: #ffffff;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 15px;
    margin-top: 15px;
}

.price-breakdown h5 {
    color: #495057;
    margin-bottom: 15px;
    font-size: 1.1em;
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 5px;
}

.price-fields-breakdown {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.price-field-info {
    background-color: #f8f9fa;
    border-left: 3px solid #007bff;
    padding: 12px;
    border-radius: 0 4px 4px 0;
}

.price-field-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    color: #495057;
    font-weight: 600;
}

.price-field-header i {
    color: #007bff;
    font-size: 0.9em;
}

.price-field-header.combined {
    color: #28a745;
}

.price-field-header.combined i {
    color: #28a745;
}

.combined-total {
    background-color: #e8f5e8;
    border: 1px solid #c3e6c3;
    border-radius: 6px;
    padding: 12px;
    margin-top: 15px;
}

.price-field-info p,
.combined-total p {
    margin-bottom: 4px;
    font-size: 0.95em;
}

.price-field-info p:last-child,
.combined-total p:last-child {
    margin-bottom: 0;
}

.no-price-warning {
    background-color: #fff3cd;
    border: 1px solid #ffeaa7;
    border-left: 4px solid #ffc107;
    padding: 15px;
    border-radius: 6px;
    margin: 10px 0;
}

.no-price-warning .fas {
    color: #856404;
    margin-right: 8px;
}

.no-price-warning .text-warning {
    color: #856404 !important;
}

.split-details {
    background-color: #e3f2fd;
    border: 1px solid #bbdefb;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.split-details h4 {
    color: #1976d2;
    margin-bottom: 12px;
}

.split-details ul {
    margin-bottom: 0;
    padding-left: 20px;
}

.split-details li {
    margin-bottom: 8px;
    line-height: 1.4;
}

.price-split-details {
    color: #1976d2 !important;
    font-weight: 500;
}

.price-split-list {
    margin-top: 8px;
    margin-bottom: 0;
    padding-left: 20px;
}

.price-split-list li {
    margin-bottom: 4px;
    font-size: 0.95em;
}

.price-split-list .combined-price {
    color: #28a745;
    font-weight: 600;
}

.split-details li.text-warning {
    color: #856404 !important;
    font-weight: 500;
}

.split-warning {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    border-left: 4px solid #dc3545;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 20px;
}

.split-warning p {
    margin-bottom: 8px;
    color: #721c24;
}

.split-warning p:last-child {
    margin-bottom: 0;
}

.price-split-warning {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724 !important;
    padding: 8px;
    border-radius: 4px;
    margin-top: 8px;
    font-size: 0.95em;
}

.split-loading {
    text-align: center;
    padding: 20px;
    background-color: #f8f9fa;
    border-radius: 8px;
    margin-top: 20px;
}

.split-loading .fas {
    font-size: 1.5em;
    color: #007bff;
    margin-right: 10px;
}

.split-loading span {
    font-weight: 500;
    color: #495057;
}

/* Modal enhancements */
.split-modal .modal-content {
    max-width: 650px;
    max-height: 90vh;
    overflow: hidden;
}

.split-modal .modal-body {
    max-height: calc(90vh - 120px);
    overflow-y: auto;
}

/* Button enhancements for split modal */
.split-modal .btn-confirm.btn-split {
    background-color: #28a745;
    border-color: #28a745;
    color: white;
    font-weight: 500;
    padding: 10px 20px;
    min-width: 140px;
}

.split-modal .btn-confirm.btn-split:hover:not(:disabled) {
    background-color: #218838;
    border-color: #1e7e34;
}

.split-modal .btn-confirm.btn-split:disabled {
    background-color: #6c757d;
    border-color: #6c757d;
    opacity: 0.8;
}

.split-modal .btn-cancel {
    background-color: #6c757d;
    border-color: #6c757d;
    color: white;
    font-weight: 500;
    padding: 10px 20px;
    min-width: 100px;
}

.split-modal .btn-cancel:hover:not(:disabled) {
    background-color: #5a6268;
    border-color: #545b62;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .split-modal .modal-content {
        max-width: 95%;
        margin: 20px auto;
    }
    
    .price-fields-breakdown {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    
    .split-info {
        max-height: 50vh;
    }
    
    .split-product-info,
    .split-details,
    .split-warning {
        padding: 12px;
    }
    
    .price-breakdown {
        padding: 12px;
    }
}
</style>