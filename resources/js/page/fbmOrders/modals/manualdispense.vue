<template>
    <!-- Manual Dispense Modal -->
    <Dialog v-model:visible="isVisible"
        header="Manual Dispense - Select Product" 
        modal
        :style="{ width: '95%', maxWidth: '900px' }"
        :pt="{ root: { class: 'mobile-fullscreen-dialog' } }"
        @update:visible="handleClose">
        
        <div v-if="item" class="flex flex-column gap-4">
            <!-- Item Information -->
            <Panel header="Order Item Details" class="w-full">
                <div class="grid gap-2">
                    <div><strong>Title:</strong> {{ item.platform_title }}</div>
                    <div><strong>ASIN:</strong> {{ item.platform_asin }}</div>
                    <div><strong>SKU:</strong> {{ item.platform_sku }}</div>
                    <div><strong>Condition:</strong> {{ getConditionDisplay(item) }}</div>
                    <div>
                        <strong>Quantity:</strong> 
                        {{ getDispensedProductCount(item) }} / 
                        {{ item.quantity_ordered }} dispensed
                    </div>
                    <div>
                        <Tag :value="`${getRemainingQuantityNeeded(item)} more needed`" 
                             severity="info" />
                    </div>
                </div>
            </Panel>

            <!-- Search Bar -->
            <div class="w-full">
                <InputText 
                    v-model="searchQuery"
                    placeholder="Search by Title, ASIN, MSKU, Product ID, Location..."
                    class="w-full"
                    size="large" />
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex align-items-center justify-content-center p-4">
                <i class="pi pi-spin pi-spinner text-4xl"></i>
                <span class="ml-3">Loading available products...</span>
            </div>

            <!-- No Products Found -->
            <div v-else-if="products.length === 0" class="w-full">
                <Message severity="warn" :closable="false">
                    No matching products found in inventory for this item.
                </Message>
            </div>

            <!-- Products List -->
            <div v-else class="flex flex-column gap-3" style="max-height: 500px; overflow-y: auto;">
                <Message severity="info" :closable="false" class="mb-2">
                    <i class="pi pi-info-circle"></i> 
                    Found {{ filteredProducts.length }} available product(s). 
                    Click on a product to select it.
                </Message>

                <div v-for="(product, index) in filteredProducts"
                    :key="'manual-product-' + index"
                    @click="selectProduct(product)"
                    :class="[
                        'border-round p-4 cursor-pointer transition-all',
                        selectedProduct?.ProductID === product.ProductID
                            ? 'bg-primary-100 border-2 border-primary shadow-3'
                            : 'bg-surface-50 hover:surface-100 border-1 border-surface-border'
                    ]">
                    
                    <div class="flex align-items-center gap-3 mb-3">
                        <i :class="[
                            'pi text-2xl',
                            selectedProduct?.ProductID === product.ProductID 
                                ? 'pi-check-circle text-primary' 
                                : 'pi-circle text-surface-400'
                        ]"></i>
                        <div class="flex-1">
                            <div class="font-bold text-lg">{{ product.title }}</div>
                            <div class="text-sm text-surface-600 mt-1">
                                Product ID: {{ product.ProductID }}
                            </div>
                        </div>
                        <Tag v-if="product.fbm_available" 
                             :value="`${product.fbm_available} available`" 
                             severity="success" />
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div><strong>ASIN:</strong> {{ product.asin || 'N/A' }}</div>
                        <div><strong>MSKU:</strong> {{ product.msku || 'N/A' }}</div>
                        <div><strong>Condition:</strong> {{ product.condition || 'N/A' }}</div>
                        <div><strong>Store:</strong> {{ product.store || 'N/A' }}</div>
                        <div><strong>Location:</strong> {{ product.warehouseLocation || 'N/A' }}</div>
                        <div><strong>FNSKU:</strong> {{ product.fnsku || 'N/A' }}</div>
                        <div v-if="product.serialNumber">
                            <strong>Serial #:</strong> {{ product.serialNumber }}
                        </div>
                        <div v-if="product.rtCounter">
                            <strong>RT Counter:</strong> {{ product.rtCounter }}
                        </div>
                        <div v-if="product.stockroom_insert_date">
                            <strong>Stockroom Date:</strong> {{ formatDate(product.stockroom_insert_date) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex gap-2 justify-content-end">
                <Button 
                    label="Cancel" 
                    icon="pi pi-times" 
                    severity="secondary"
                    @click="handleClose" />
                <Button 
                    label="Confirm Dispense" 
                    icon="pi pi-check" 
                    severity="success"
                    :disabled="!selectedProduct"
                    @click="confirmDispense" />
            </div>
        </template>
    </Dialog>
</template>

<script>
import { Dialog, Button, Panel, InputText, Message, Tag } from 'primevue';

const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: 'ManualDispenseModal',
    components: {
        Dialog,
        Button,
        Panel,
        InputText,
        Message,
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
            loading: false
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
        }
    },
    watch: {
        visible(newVal) {
            if (newVal && this.item) {
                this.loadProducts();
            } else if (!newVal) {
                this.resetModal();
            }
        }
    },
    methods: {
        async loadProducts() {
            if (!this.item) return;
            
            this.loading = true;
            
            try {
                console.log('📦 Loading manual dispense products for item:', this.item.outboundorderitemid);
                
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
                
                console.log('Manual dispense products response:', response.data);
                
                if (response.data && response.data.success && response.data.data.length > 0) {
                    const itemData = response.data.data[0];
                    
                    // Combine both auto-selected and matching products
                    let allProducts = [];
                    
                    if (itemData.auto_selected_products && itemData.auto_selected_products.length > 0) {
                        allProducts = [...itemData.auto_selected_products];
                    }
                    
                    if (itemData.matching_products && itemData.matching_products.length > 0) {
                        // Add remaining products that weren't auto-selected
                        const autoSelectedIds = allProducts.map(p => p.ProductID);
                        const remainingProducts = itemData.matching_products.filter(
                            p => !autoSelectedIds.includes(p.ProductID)
                        );
                        allProducts = [...allProducts, ...remainingProducts];
                    }
                    
                    this.products = allProducts;
                    
                    console.log(`✅ Found ${allProducts.length} products for manual selection`);
                } else {
                    this.products = [];
                    console.log('⚠️ No products found for manual dispense');
                }
            } catch (error) {
                console.error('Error loading manual dispense products:', error);
                alert('Failed to load products. Please try again.');
                this.products = [];
            } finally {
                this.loading = false;
            }
        },
        
        selectProduct(product) {
            if (this.selectedProduct?.ProductID === product.ProductID) {
                this.selectedProduct = null;
            } else {
                this.selectedProduct = product;
            }
            console.log('Selected product:', this.selectedProduct);
        },
        
        async confirmDispense() {
            if (!this.selectedProduct || !this.item) {
                alert('Please select a product to dispense');
                return;
            }
            
            try {
                console.log('🔧 Confirming manual dispense:', {
                    order_id: this.orderId,
                    item_id: this.item.outboundorderitemid,
                    product_id: this.selectedProduct.ProductID
                });
                
                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/dispense`,
                    {
                        order_id: this.orderId,
                        dispense_items: [{
                            item_id: this.item.outboundorderitemid,
                            product_id: this.selectedProduct.ProductID
                        }]
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
                
                if (response.data && response.data.success) {
                    alert(`Product ${this.selectedProduct.ProductID} dispensed successfully!`);
                    
                    // Emit event to parent for refresh
                    this.$emit('dispense-complete', {
                        orderId: this.orderId,
                        itemId: this.item.outboundorderitemid,
                        productId: this.selectedProduct.ProductID
                    });
                    
                    // Close modal
                    this.handleClose();
                } else {
                    alert(`Error: ${response.data.message || 'Failed to dispense product'}`);
                }
            } catch (error) {
                console.error('Error confirming manual dispense:', error);
                alert('Failed to dispense product. Please try again.');
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
        },
        
        // Helper methods
        getConditionDisplay(item) {
            if (!item) return 'N/A';
            if (item.condition) return item.condition;
            if (item.ordered_condition) return item.ordered_condition;
            
            const conditionId = item.ConditionId || '';
            const subtypeId = item.ConditionSubtypeId || '';
            
            return `${conditionId}${subtypeId}`;
        },
        
        getDispensedProductCount(item) {
            if (!item) return 0;
            
            if (item.dispensed_count !== undefined) {
                return item.dispensed_count;
            }
            
            if (item.dispensed_products && Array.isArray(item.dispensed_products)) {
                return item.dispensed_products.length;
            }
            
            return item.product_id ? 1 : 0;
        },
        
        getRemainingQuantityNeeded(item) {
            if (!item) return 0;
            const dispensed = this.getDispensedProductCount(item);
            return Math.max(0, item.quantity_ordered - dispensed);
        },
        
        formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            const date = new Date(dateStr);
            return date.toLocaleString();
        }
    }
};
</script>

<style scoped>
.manual-dispense-product-card {
    transition: all 0.2s ease;
}

.manual-dispense-product-card:hover {
    transform: translateY(-2px);
}

.manual-dispense-product-card.selected {
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

/* Ensure scrollable area works on mobile */
@media (max-width: 768px) {
    .manual-dispense-products-scroll {
        max-height: 400px;
    }
}
</style>