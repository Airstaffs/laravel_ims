<template>
    <!-- Manual Dispense Modal -->
    <Dialog v-model:visible="isVisible"
        header="Manual Dispense - Select Product" 
        modal
        :style="{ width: '95%', maxWidth: '1400px' }"
        :pt="{ 
            root: { class: 'manual-dispense-modal' },
        }"
        @update:visible="handleClose">
        
        <div v-if="item" class="dispense-modal-body">
            <!-- Item Information Section -->
            <div class="info-banner">
                <div class="info-grid">
                    <div class="info-col">
                        <span class="info-label">Title:</span>
                        <span class="info-value">{{ item.platform_title }}</span>
                    </div>
                    <div class="info-col">
                        <span class="info-label">ASIN:</span>
                        <span class="info-value">{{ item.platform_asin }}</span>
                    </div>
                    <div class="info-col">
                        <span class="info-label">SKU:</span>
                        <span class="info-value">{{ item.platform_sku }}</span>
                    </div>
                    <div class="info-col">
                        <span class="info-label">Condition:</span>
                        <span class="info-value">{{ getConditionDisplay(item) }}</span>
                    </div>
                    <div class="info-col">
                        <span class="info-label">Progress:</span>
                        <div class="progress-tags">
                            <Tag :value="`${getDispensedProductCount(item)} / ${item.quantity_ordered} dispensed`" 
                                 severity="success" />
                            <Tag :value="`${getRemainingQuantityNeeded(item)} needed`" 
                                 severity="info" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Stats Bar -->
            <div class="search-bar">
                <div class="search-wrapper">
                    <i class="pi pi-search search-icon-left"></i>
                    <InputText 
                        v-model="searchQuery"
                        placeholder="Search by Title, ASIN, MSKU, Product ID, Location, Serial Number..."
                        class="search-field" />
                    <Button 
                        v-if="searchQuery"
                        icon="pi pi-times" 
                        text 
                        rounded
                        severity="secondary"
                        size="small"
                        class="clear-btn"
                        @click="searchQuery = ''" />
                </div>
                <div class="results-count">
                    <i class="pi pi-box"></i>
                    <span><strong>{{ filteredProducts.length }}</strong> products found</span>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="state-container">
                <i class="pi pi-spin pi-spinner" style="font-size: 3rem; color: #6366f1;"></i>
                <p class="state-text">Loading products...</p>
            </div>

            <!-- Empty State -->
            <div v-else-if="products.length === 0" class="state-container">
                <i class="pi pi-inbox" style="font-size: 4rem; color: #94a3b8;"></i>
                <p class="state-text">No products found</p>
            </div>

            <!-- Products Grid -->
            <div v-else class="products-container">
                <div class="products-grid">
                    <div v-for="(product, index) in paginatedProducts"
                        :key="product.ProductID"
                        @click="selectProduct(product)"
                        :class="['product-card', { 'selected': selectedProduct?.ProductID === product.ProductID }]">
                        
                        <!-- Selection Badge -->
                        <div class="selection-badge">
                            <i :class="selectedProduct?.ProductID === product.ProductID ? 'pi pi-check-circle' : 'pi pi-circle'"></i>
                        </div>

                        <!-- Product Header -->
                        <div class="product-header">
                            <h4 class="product-name">{{ product.title }}</h4>
                            <span class="product-id-badge">ID: {{ product.ProductID }}</span>
                        </div>

                        <!-- Stock Badge -->
                        <Tag v-if="product.fbm_available" 
                             :value="`${product.fbm_available} in stock`" 
                             severity="success"
                             class="mb-3" />

                        <!-- Product Info -->
                        <div class="product-info">
                            <div class="info-row">
                                <i class="pi pi-tag"></i>
                                <span class="label">ASIN:</span>
                                <span class="value">{{ product.asin || 'N/A' }}</span>
                            </div>
                            <div class="info-row">
                                <i class="pi pi-barcode"></i>
                                <span class="label">MSKU:</span>
                                <span class="value">{{ product.msku || 'N/A' }}</span>
                            </div>
                            <div class="info-row">
                                <i class="pi pi-star"></i>
                                <span class="label">Condition:</span>
                                <span class="value">{{ product.condition || 'N/A' }}</span>
                            </div>
                            <div class="info-row highlight">
                                <i class="pi pi-map-marker"></i>
                                <span class="label">Location:</span>
                                <span class="value">{{ product.warehouseLocation || 'N/A' }}</span>
                            </div>
                            <div class="info-row">
                                <i class="pi pi-building"></i>
                                <span class="label">Store:</span>
                                <span class="value">{{ product.store || 'N/A' }}</span>
                            </div>
                            <div v-if="product.serialNumber" class="info-row">
                                <i class="pi pi-hashtag"></i>
                                <span class="label">Serial:</span>
                                <span class="value">{{ product.serialNumber }}</span>
                            </div>
                            <div v-if="product.stockroom_insert_date" class="info-row">
                                <i class="pi pi-calendar"></i>
                                <span class="label">Date:</span>
                                <span class="value">{{ formatDate(product.stockroom_insert_date) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="totalPages > 1" class="pagination-bar">
                    <Button 
                        icon="pi pi-angle-double-left" 
                        text 
                        size="small"
                        :disabled="currentPage === 1"
                        @click="goToPage(1)" />
                    <Button 
                        icon="pi pi-angle-left" 
                        text 
                        size="small"
                        :disabled="currentPage === 1"
                        @click="prevPage" />
                    
                    <div class="page-buttons">
                        <Button 
                            v-for="page in visiblePages"
                            :key="page"
                            :label="page.toString()"
                            text
                            size="small"
                            :class="{ 'page-active': currentPage === page }"
                            @click="goToPage(page)" />
                    </div>
                    
                    <Button 
                        icon="pi pi-angle-right" 
                        text 
                        size="small"
                        :disabled="currentPage === totalPages"
                        @click="nextPage" />
                    <Button 
                        icon="pi pi-angle-double-right" 
                        text 
                        size="small"
                        :disabled="currentPage === totalPages"
                        @click="goToPage(totalPages)" />
                    
                    <span class="page-label">Page {{ currentPage }} of {{ totalPages }}</span>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="footer-content">
                <div class="selected-info">
                    <span v-if="selectedProduct">
                        <i class="pi pi-check-circle" style="color: #10b981;"></i>
                        Product <strong>{{ selectedProduct.ProductID }}</strong> selected
                    </span>
                </div>
                <div class="footer-actions">
                    <Button 
                        label="Cancel" 
                        icon="pi pi-times" 
                        severity="secondary"
                        outlined
                        @click="handleClose" />
                    <Button 
                        label="Confirm Dispense" 
                        icon="pi pi-check" 
                        severity="success"
                        :disabled="!selectedProduct"
                        @click="confirmDispense" />
                </div>
            </div>
        </template>
    </Dialog>
</template>

<script>
import { Dialog, Button, InputText, Tag } from 'primevue';
import Swal from 'sweetalert2';

const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: 'ManualDispenseModal',
    components: {
        Dialog,
        Button,
        InputText,
        Tag
    },
    props: {
        visible: {
            type: Boolean,
            required: true
        },
        item: {
            type: Object,
            default: null
        },
        orderId: {
            type: Number,
            required: true
        }
    },
    emits: ['update:visible', 'dispense-complete'],
    data() {
        return {
            products: [],
            selectedProduct: null,
            searchQuery: '',
            loading: false,
            currentPage: 1,
            itemsPerPage: 9
        };
    },
    computed: {
        isVisible: {
            get() {
                return this.visible;
            },
            set(value) {
                this.$emit('update:visible', value);
            }
        },
        filteredProducts() {
            if (!this.searchQuery) {
                return this.products;
            }
            
            const query = this.searchQuery.toLowerCase();
            return this.products.filter(product => {
                return (
                    product.title?.toLowerCase().includes(query) ||
                    product.asin?.toLowerCase().includes(query) ||
                    product.msku?.toLowerCase().includes(query) ||
                    product.ProductID?.toString().includes(query) ||
                    product.warehouseLocation?.toLowerCase().includes(query) ||
                    product.serialNumber?.toLowerCase().includes(query)
                );
            });
        },
        totalPages() {
            return Math.ceil(this.filteredProducts.length / this.itemsPerPage);
        },
        paginatedProducts() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            return this.filteredProducts.slice(start, start + this.itemsPerPage);
        },
        visiblePages() {
            const pages = [];
            const maxVisible = 5;
            const half = Math.floor(maxVisible / 2);
            
            let start = Math.max(1, this.currentPage - half);
            let end = Math.min(this.totalPages, start + maxVisible - 1);
            
            if (end - start + 1 < maxVisible) {
                start = Math.max(1, end - maxVisible + 1);
            }
            
            for (let i = start; i <= end; i++) {
                pages.push(i);
            }
            return pages;
        }
    },
    watch: {
        visible(newVal) {
            if (newVal && this.item) {
                this.loadProducts();
            } else if (!newVal) {
                this.resetModal();
            }
        },
        searchQuery() {
            this.currentPage = 1;
        }
    },
    methods: {
        async loadProducts() {
            if (!this.item) return;
            
            this.loading = true;
            
            try {
                console.log('📦 Loading manual dispense products for item:', this.item.outboundorderitemid);
                console.log('📦 Order ID:', this.orderId);
                
                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/find-dispense-products`,
                    {
                        order_id: this.orderId,
                        item_ids: [this.item.outboundorderitemid]
                    },
                    {
                        withCredentials: true,
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                        }
                    }
                );
                
                console.log('📦 Full API Response:', response.data);
                
                if (response.data?.success && response.data.data?.length > 0) {
                    const itemData = response.data.data[0];
                    console.log('📦 Item Data:', itemData);
                    
                    let allProducts = [];
                    
                    // ✅ CRITICAL FIX: Get already dispensed product IDs to exclude them
                    const alreadyDispensedIds = itemData.already_dispensed_products || [];
                    console.log('🚫 Already dispensed product IDs:', alreadyDispensedIds);
                    
                    // Get matching products (these are the available ones)
                    if (itemData.matching_products?.length > 0) {
                        // ✅ Filter out already dispensed products
                        const availableProducts = itemData.matching_products.filter(
                            p => !alreadyDispensedIds.includes(p.ProductID)
                        );
                        allProducts = [...availableProducts];
                        console.log('✅ Matching products after filtering:', availableProducts.length);
                    }
                    
                    // Auto-selected products - also filter them
                    if (itemData.auto_selected_products?.length > 0) {
                        const matchingIds = allProducts.map(p => p.ProductID);
                        const autoProducts = itemData.auto_selected_products.filter(
                            p => !matchingIds.includes(p.ProductID) && !alreadyDispensedIds.includes(p.ProductID)
                        );
                        if (autoProducts.length > 0) {
                            allProducts = [...autoProducts, ...allProducts];
                            console.log('✅ Auto-selected products after filtering:', autoProducts.length);
                        }
                    }
                    
                    this.products = allProducts;
                    console.log(`✅✅ Total products available (after filtering dispensed): ${allProducts.length}`);
                    
                    if (allProducts.length === 0) {
                        await Swal.fire({
                            icon: 'warning',
                            title: 'No Products Available',
                            html: `
                                <p>No available products found that match:</p>
                                <ul style="text-align: left; margin: 1rem auto; max-width: 400px;">
                                    <li><strong>ASIN:</strong> ${this.item.platform_asin}</li>
                                    <li><strong>Condition:</strong> ${this.getConditionDisplay(this.item)}</li>
                                </ul>
                                <p style="color: #64748b; font-size: 0.9em;">All matching products are already assigned to orders.</p>
                            `,
                            confirmButtonText: 'OK'
                        });
                    }
                } else {
                    this.products = [];
                    console.log('⚠️ No data returned from API');
                }
            } catch (error) {
                console.error('❌ Error loading manual dispense products:', error);
                console.error('❌ Error details:', error.response?.data);
                
                await Swal.fire({
                    icon: 'error',
                    title: 'Failed to Load Products',
                    text: error.response?.data?.message || 'Could not load available products. Please try again.',
                    confirmButtonText: 'OK'
                });
                
                this.products = [];
            } finally {
                this.loading = false;
            }
        },
        
        selectProduct(product) {
            this.selectedProduct = this.selectedProduct?.ProductID === product.ProductID ? null : product;
        },
        
        async confirmDispense() {
            if (!this.selectedProduct || !this.item) {
                await Swal.fire({
                    icon: 'warning',
                    title: 'No Product Selected',
                    text: 'Please select a product to dispense',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            console.log('🔧 Starting manual dispense...');
            console.log('🔧 Order ID:', this.orderId);
            console.log('🔧 Item ID:', this.item.outboundorderitemid);
            console.log('🔧 Product ID:', this.selectedProduct.ProductID);
            console.log('🔧 Selected Product Details:', this.selectedProduct);
            
            // Show loading
            Swal.fire({
                title: 'Dispensing Product...',
                html: '<div style="padding: 20px;">Please wait...</div>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            try {
                const requestPayload = {
                    order_id: this.orderId,
                    dispense_items: [{
                        item_id: this.item.outboundorderitemid,
                        product_id: this.selectedProduct.ProductID
                    }]
                };
                
                console.log('🔧 Request Payload:', JSON.stringify(requestPayload, null, 2));
                
                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/dispense`,
                    requestPayload,
                    {
                        withCredentials: true,
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                        }
                    }
                );
                
                console.log('✅ Dispense Response:', response.data);
                
                if (response.data?.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        html: `
                            <p>Product <strong>${this.selectedProduct.ProductID}</strong> dispensed successfully!</p>
                            <p style="margin-top: 10px; color: #64748b;">Location: ${this.selectedProduct.warehouseLocation || 'N/A'}</p>
                        `,
                        confirmButtonText: 'OK'
                    });
                    
                    console.log('✅ Emitting dispense-complete event');
                    
                    // Emit event to parent for refresh
                    this.$emit('dispense-complete', {
                        orderId: this.orderId,
                        itemId: this.item.outboundorderitemid,
                        productId: this.selectedProduct.ProductID
                    });
                    
                    // Close modal
                    this.handleClose();
                } else {
                    throw new Error(response.data.message || 'Failed to dispense');
                }
            } catch (error) {
                console.error('❌ Dispense Error:', error);
                console.error('❌ Error Response:', error.response?.data);
                
                let errorMessage = 'Failed to dispense product. Please try again.';
                
                if (error.response?.data?.message) {
                    errorMessage = error.response.data.message;
                } else if (error.message) {
                    errorMessage = error.message;
                }
                
                await Swal.fire({
                    icon: 'error',
                    title: 'Dispense Failed',
                    html: `
                        <p>${errorMessage}</p>
                        ${error.response?.data?.error ? `<p style="margin-top: 10px; font-size: 0.9em; color: #64748b;">${error.response.data.error}</p>` : ''}
                    `,
                    confirmButtonText: 'OK'
                });
            }
        },
        
        handleClose() {
            this.$emit('update:visible', false);
            this.resetModal();
        },
        
        resetModal() {
            this.products = [];
            this.selectedProduct = null;
            this.searchQuery = '';
            this.loading = false;
            this.currentPage = 1;
        },
        
        goToPage(page) {
            if (page >= 1 && page <= this.totalPages) {
                this.currentPage = page;
            }
        },
        
        prevPage() {
            if (this.currentPage > 1) this.currentPage--;
        },
        
        nextPage() {
            if (this.currentPage < this.totalPages) this.currentPage++;
        },
        
        getConditionDisplay(item) {
            if (!item) return 'N/A';
            if (item.condition) return item.condition;
            if (item.ordered_condition) return item.ordered_condition;
            return `${item.ConditionId || ''}${item.ConditionSubtypeId || ''}`;
        },
        
        getDispensedProductCount(item) {
            if (!item) return 0;
            if (item.dispensed_count !== undefined) return item.dispensed_count;
            if (item.dispensed_products?.length) return item.dispensed_products.length;
            return item.product_id ? 1 : 0;
        },
        
        getRemainingQuantityNeeded(item) {
            if (!item) return 0;
            return Math.max(0, item.quantity_ordered - this.getDispensedProductCount(item));
        },
        
        formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            return new Date(dateStr).toLocaleString();
        }
    }
};
</script>

<style scoped>
/* Modal Body */
.dispense-modal-body {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    padding: 0.5rem;
}

/* Info Banner */
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

/* Search Bar */
.search-bar {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.search-wrapper {
    flex: 1;
    position: relative;
    min-width: 300px;
}

.search-icon-left {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
}

.search-field {
    width: 100%;
    padding: 0.75rem 3rem 0.75rem 3rem;
    font-size: 1rem;
    border-radius: 8px;
    border: 2px solid #e2e8f0;
}

.search-field:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.clear-btn {
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
}

.results-count {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #64748b;
    font-size: 0.95rem;
    white-space: nowrap;
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

/* Products Container */
.products-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 1.25rem;
    max-height: 600px;
    overflow-y: auto;
    padding: 0.5rem;
}

/* Product Card */
.product-card {
    position: relative;
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: 1.25rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.product-card:hover {
    border-color: #6366f1;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
    transform: translateY(-2px);
}

.product-card.selected {
    border-color: #6366f1;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(139, 92, 246, 0.05) 100%);
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.2);
}

.selection-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 1.5rem;
    color: #cbd5e1;
    transition: all 0.2s;
}

.product-card.selected .selection-badge {
    color: #6366f1;
}

.product-header {
    margin-bottom: 0.75rem;
    padding-right: 2rem;
}

.product-name {
    font-size: 1rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 0.25rem 0;
    line-height: 1.4;
}

.product-id-badge {
    font-size: 0.75rem;
    color: #64748b;
    font-weight: 500;
}

.product-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.info-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
}

.info-row i {
    color: #94a3b8;
    font-size: 0.75rem;
    flex-shrink: 0;
}

.info-row .label {
    color: #64748b;
    font-weight: 500;
    flex-shrink: 0;
}

.info-row .value {
    color: #1e293b;
    font-weight: 600;
    margin-left: auto;
    text-align: right;
}

.info-row.highlight .value {
    color: #6366f1;
}

/* Pagination */
.pagination-bar {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
}

.page-buttons {
    display: flex;
    gap: 0.25rem;
}

.page-active {
    background: #6366f1 !important;
    color: white !important;
}

.page-label {
    margin-left: 1rem;
    font-size: 0.875rem;
    color: #64748b;
}

/* Footer */
.footer-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.selected-info {
    flex: 1;
    font-size: 0.95rem;
}

.footer-actions {
    display: flex;
    gap: 0.75rem;
}

/* Scrollbar */
.products-grid::-webkit-scrollbar {
    width: 8px;
}

.products-grid::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}

.products-grid::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.products-grid::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Responsive */
@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .search-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-wrapper {
        min-width: 100%;
    }
    
    .products-grid {
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