import { eventBus } from '../../components/eventBus';
import '../../../css/modules.css';
import axios from 'axios';

export default {
  name: 'FbmOrderModule',
  components: {},
  data() {
    return {
      apiBaseUrl: window.location.origin,
      orders: [],
      loading: true,
      currentPage: 1,
      totalPages: 1,
      perPage: 10,
      selectAll: false,
      
      // For sorting and filtering
      sortColumn: "purchase_date",
      sortOrder: "desc",
      
      // Store filter
      stores: [],
      selectedStore: '',
      statusFilter: '',
      
      // For order details modal
      showOrderDetailsModal: false,
      selectedOrder: null,
      
      // For process modal
      showProcessModal: false,
      currentProcessOrder: null,
      selectedItems: [],
      processData: {
        shipmentType: 'Standard',
        trackingNumber: '',
        notes: ''
      },
      isProcessing: false,
      
      // For auto dispense (standalone and in process modal)
      showAutoDispenseModal: false,
      autoDispenseOrder: null,
      dispenseProducts: [],
      selectedDispenseProducts: {},
      loadingDispenseProducts: false,
      processingAutoDispense: false,
      
      // NEW: For persistent order selection across pagination
      persistentSelectedOrderIds: [],
      dispenseItemsSelected: [],
    };
  },
  computed: {
    // For global search
    searchQuery() {
      return eventBus.searchQuery || '';
    },
    
    // Check if any orders are selected
    hasSelectedOrders() {
      return this.persistentSelectedOrderIds.length > 0;
    },
    
    // Form validation for processing
    isProcessFormValid() {
      return this.selectedItems.length > 0 && 
             this.processData.trackingNumber.trim() !== '' &&
             this.processData.shipmentType !== '';
    },
    
    // Check if we can confirm auto dispense
    canConfirmDispense() {
      return Object.keys(this.selectedDispenseProducts).length > 0;
    },
    
    // Check if current order has items without product_id assigned
    currentOrderHasUnassignedItems() {
      if (!this.currentProcessOrder || !this.currentProcessOrder.items) return false;
      return this.currentProcessOrder.items.some(item => !item.product_id);
    },
    
    // NEW: Check if an order can be selected (has dispensed items)
    canSelectOrder() {
      return (order) => {
        return this.hasDispensedItems(order);
      };
    },
    
    // NEW: Get only valid dispensed items
    validDispenseItems() {
      // Check if any selected items don't have dispense information
      return this.dispenseItemsSelected.filter(itemId => {
        // Find this item across all orders
        let foundItem = null;
        this.orders.forEach(order => {
          if (order.items) {
            const item = order.items.find(i => i.outboundorderitemid === itemId);
            if (item) {
              foundItem = item;
            }
          }
        });
        
        // If the item was found and has a product_id, it's valid
        return foundItem && foundItem.product_id;
      });
    }
  },
  methods: {
    // Normalize store name for consistent comparison
    normalizeStoreName(storeName) {
      if (!storeName) return '';
      
      // Remove spaces, hyphens, underscores and convert to lowercase
      return storeName.toLowerCase().replace(/[\s\-_]+/g, '');
    },
    
    // Format date for display
    formatDate(dateStr) {
      if (!dateStr) return 'N/A';
      const date = new Date(dateStr);
      return date.toLocaleString();
    },
    
    // Format address for display
    formatAddress(address, fullFormat = false) {
      if (!address) return 'N/A';
      
      if (fullFormat) {
        // Return multi-line address for modals
        return address.split(', ').join('\n');
      }
      
      // Return truncated address for tables
      return address;
    },
    
    // Get CSS class for status badges
    getStatusClass(status) {
      switch (status) {
        case 'Pending':
          return 'status-badge status-pending';
        case 'Unshipped':
          return 'status-badge status-pending';
        case 'Shipped':
          return 'status-badge status-shipped';
        case 'Canceled':
          return 'status-badge status-canceled';
        default:
          return 'status-badge';
      }
    },

    // HELPER METHODS FOR NULL CHECKING
    hasTrackingNumber(order) {
      return order && order.items && Array.isArray(order.items) && 
            order.items.some(item => item.tracking_number);
    },
    
    getShipStatus(order) {
      if (!order || !order.items || !Array.isArray(order.items)) {
        return 'Not Shipped';
      }
      return order.items.some(item => item.tracking_number) ? 'Shipped' : 'Not Shipped';
    },
    
    getTrackingStatus(order) {
      if (!order || !order.items || !Array.isArray(order.items)) {
        return 'Not Available';
      }
      const trackedItem = order.items.find(item => item.tracking_status);
      return trackedItem ? trackedItem.tracking_status : 'Not Available';
    },
    
    formatShipByDate(date) {
      if (!date) return 'N/A';
      return this.formatDate(date);
    },
    
    formatDeliveryDate(date) {
      if (!date) return 'N/A';
      return this.formatDate(date);
    },
    
    // Check if order has any dispensed items
    hasDispensedItems(order) {
      return order && order.items && Array.isArray(order.items) &&
             order.items.some(item => item.product_id);
    },
    
    // Check if a specific item is dispensed
    isItemDispensed(item) {
      return item && item.product_id;
    },
    
    // Get condition display text
    getConditionDisplay(item) {
      if (!item) return 'N/A';
      
      // If the condition is already formatted, use it directly
      if (item.condition) return item.condition;
      
      // If we have the ordered_condition field (from the API)
      if (item.ordered_condition) return item.ordered_condition;
      
      // Otherwise, format from condition parts
      const conditionId = item.ConditionId || '';
      const subtypeId = item.ConditionSubtypeId || '';
      
      return `${conditionId}${subtypeId}`;
    },
    
    // Check if a product's condition is valid for the item's condition (store-specific)
    isConditionValid(itemCondition, productCondition, storeName) {
      // Normalize store name for consistent comparison
      const normalizedStore = this.normalizeStoreName(storeName);
      
      // For All Renewed store (handles both "All Renewed" and "Allrenewed")
      if (normalizedStore === 'allrenewed') {
        // Allow matching logic: higher quality items can fulfill lower quality orders
        const conditionHierarchy = {
          'Refurbished - Excellent': 3,
          'Refurbished - Good': 2,
          'Refurbished - Acceptable': 1
        };
        
        const itemRank = conditionHierarchy[itemCondition] || 0;
        const productRank = conditionHierarchy[productCondition] || 0;
        
        // Product can fulfill if it's the same or higher quality
        return productRank >= itemRank;
      } 
      
      // For other stores, direct match
      return itemCondition === productCondition;
    },
    
    // Format store-specific condition
    formatStoreSpecificCondition(conditionId, conditionSubtypeId, storeName) {
      // Normalize store name for consistent comparison
      const normalizedStore = this.normalizeStoreName(storeName);
      
      // Special handling for AllRenewed store (handles both "All Renewed" and "Allrenewed")
      if (normalizedStore === 'allrenewed') {
        const combinedCondition = conditionId + conditionSubtypeId;
        
        switch (combinedCondition) {
          case 'NewNew':
            return 'Refurbished - Excellent';
          case 'NewGood':
            return 'Refurbished - Good';
          case 'NewAcceptable':
            return 'Refurbished - Acceptable';
          default:
            // Fallback to normal formatting 
            return combinedCondition;
        }
      }
      
      // Default format for other stores
      return conditionId + conditionSubtypeId;
    },
    
    // Open scanner modal method
    openScannerModal() {
      if (this.$refs.scanner) {
        this.$refs.scanner.openScannerModal();
      }
    },
    
    // NEW: Initialize dispenseItemsSelected on component mount
    initializeDispenseItems() {
      // Reset the dispenseItemsSelected array
      this.dispenseItemsSelected = [];
      
      // Get all items with dispense information
      this.orders.forEach(order => {
        if (order.items) {
          order.items.forEach(item => {
            // If the item has dispense information, add its ID to the array
            if (this.isItemDispensed(item)) {
              this.dispenseItemsSelected.push(item.outboundorderitemid);
            }
          });
        }
      });
    },
    
    // NEW: Handle order checkbox change event
    handleOrderCheckChange(order) {
      // Only allow checking if the order has dispensed items
      if (!this.hasDispensedItems(order)) {
        order.checked = false;
        return;
      }
      
      // Update the persistent selection array
      if (order.checked) {
        // Add to persistent array if not already there
        if (!this.persistentSelectedOrderIds.includes(order.outboundorderid)) {
          this.persistentSelectedOrderIds.push(order.outboundorderid);
        }
      } else {
        // Remove from persistent array
        this.persistentSelectedOrderIds = this.persistentSelectedOrderIds.filter(
          id => id !== order.outboundorderid
        );
      }
    },
    
    // MODIFIED: Fetch orders from the API with persistent selection
    async fetchOrders() {
      this.loading = true;
      
      try {
        console.log('Fetching orders with params:', {
          search: this.searchQuery,
          page: this.currentPage,
          per_page: this.perPage,
          store: this.selectedStore,
          status: this.statusFilter,
          sort_column: this.sortColumn,
          sort_order: this.sortOrder
        });

        const response = await axios.get(`${this.apiBaseUrl}/api/fbm-orders`, {
          params: {
            search: this.searchQuery,
            page: this.currentPage,
            per_page: this.perPage,
            store: this.selectedStore,
            status: this.statusFilter,
            sort_column: this.sortColumn,
            sort_order: this.sortOrder
          },
          withCredentials: true
        });
        
        console.log('API Response:', response);
        
        // Check if response is valid
        if (response.data && response.data.success) {
          // Process orders and ensure any dispensed items have full details
          this.orders = (response.data.data || []).map(order => {
            // Ensure items array exists and process each item
            const processedItems = Array.isArray(order.items) ? order.items.map(item => {
              // For items with product_id, ensure we have the product details fields
              if (item.product_id) {
                return {
                  ...item,
                  // Map backend field names to frontend field names if necessary
                  warehouseLocation: item.warehouseLocation || '',
                  serialNumber: item.serialNumber || '',
                  rtCounter: item.rtCounter || '',
                  FNSKU: item.FNSKU || ''
                };
              }
              return item;
            }) : [];
            
            // Check if this order is in the persistent selection
            const isChecked = this.persistentSelectedOrderIds.includes(order.outboundorderid);
            
            return {
              ...order,
              checked: isChecked,
              items: processedItems
            };
          });
          
          console.log('Processed orders with dispensed items:', this.orders);
          
          this.totalPages = response.data.last_page || 1;
          
          // Initialize dispense items selection
          this.initializeDispenseItems();
        } else {
          console.error("Invalid response format:", response.data);
          this.orders = [];
          this.totalPages = 1;
        }
      } catch (error) {
        console.error("Error fetching orders:", error);
        this.orders = [];
        this.totalPages = 1;
      } finally {
        this.loading = false;
      }
    },
    
    // Fetch stores for dropdown
    async fetchStores() {
      try {
        console.log('Fetching stores');
        const response = await axios.get(`${this.apiBaseUrl}/api/fbm-orders/stores`, {
          withCredentials: true
        });
        console.log('Stores response:', response);
        this.stores = response.data || [];
      } catch (error) {
        console.error("Error fetching stores:", error);
        this.stores = [];
      }
    },
    
    // MODIFIED: Change store filter and clear selections
    changeStore() {
      this.currentPage = 1;
      this.clearAllSelections();
      this.fetchOrders();
    },
    
    // MODIFIED: Change status filter and clear selections
    changeStatusFilter() {
      this.currentPage = 1;
      this.clearAllSelections();
      this.fetchOrders();
    },
    
    // Refresh data
    refreshData() {
      this.fetchOrders();
    },
    
    // Pagination methods
    changePerPage() {
      this.currentPage = 1;
      this.fetchOrders();
    },
    
    prevPage() {
      if (this.currentPage > 1) {
        this.currentPage--;
        this.fetchOrders();
      }
    },
    
    nextPage() {
      if (this.currentPage < this.totalPages) {
        this.currentPage++;
        this.fetchOrders();
      }
    },
    
    // MODIFIED: Toggle select all orders (respecting dispense status)
    toggleAll() {
      const newValue = this.selectAll;
      
      // Apply to all orders but only if they have dispensed items
      this.orders.forEach(order => {
        if (this.hasDispensedItems(order)) {
          order.checked = newValue;
          this.handleOrderCheckChange(order);
        } else {
          order.checked = false;
        }
      });
    },
    
    // NEW: Clear all selections
    clearAllSelections() {
      this.persistentSelectedOrderIds = [];
      this.selectAll = false;
      this.orders.forEach(order => {
        order.checked = false;
      });
    },
    
    // Sorting method
    sortBy(column) {
      if (this.sortColumn === column) {
        this.sortOrder = this.sortOrder === "asc" ? "desc" : "asc";
      } else {
        this.sortColumn = column;
        this.sortOrder = "asc";
      }
      
      this.fetchOrders();
    },
    
    // Open order details modal
    viewOrderDetails(order) {
      this.selectedOrder = order;
      this.showOrderDetailsModal = true;
    },
    
    // Close order details modal
    closeOrderDetailsModal() {
      this.showOrderDetailsModal = false;
      this.selectedOrder = null;
    },
    
    // Process modal functions
    openProcessModal(order) {
      this.currentProcessOrder = order;
      // Safe check for items and select all by default
      this.selectedItems = order && order.items && Array.isArray(order.items) 
        ? order.items.map(item => item.outboundorderitemid) 
        : [];
      this.resetProcessData();
      this.showProcessModal = true;
    },
    
    // Open process modal from details
    openProcessModalFromDetails(order) {
      this.closeOrderDetailsModal();
      this.openProcessModal(order);
    },
    
    // Reset process form data
    resetProcessData() {
      this.processData = {
        shipmentType: 'Standard',
        trackingNumber: '',
        notes: ''
      };
    },
    
    // Close process modal
    closeProcessModal() {
      this.showProcessModal = false;
      this.currentProcessOrder = null;
      this.selectedItems = [];
      this.processingAutoDispense = false;
    },
    
    // Submit process order
    async submitProcessOrder() {
      if (!this.isProcessFormValid) return;
      
      try {
        this.isProcessing = true;
        
        const processData = {
          order_id: this.currentProcessOrder.outboundorderid,
          item_ids: this.selectedItems,
          shipment_type: this.processData.shipmentType,
          tracking_number: this.processData.trackingNumber,
          notes: this.processData.notes
        };
        
        console.log('Processing order with data:', processData);
        
        const response = await axios.post(`${this.apiBaseUrl}/api/fbm-orders/process`, processData, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
          }
        });
        
        console.log('Process response:', response);
        
        if (response.data && response.data.success) {
          alert("Order processed successfully");
          this.closeProcessModal();
          this.fetchOrders(); // Refresh data after processing
        } else {
          alert(`Error: ${response.data.message || 'Failed to process order'}`);
        }
      } catch (error) {
        console.error("Error processing order:", error);
        alert("Failed to process order. Please try again.");
      } finally {
        this.isProcessing = false;
      }
    },
    
    // Auto Dispense Functions
    
    // Open Auto Dispense Modal
    autoDispense(order) {
      this.autoDispenseOrder = order;
      this.dispenseProducts = [];
      this.selectedDispenseProducts = {};
      this.showAutoDispenseModal = true;
      this.loadMatchingProducts();
    },
    
    // Close Auto Dispense Modal
    closeAutoDispenseModal() {
      this.showAutoDispenseModal = false;
      this.autoDispenseOrder = null;
      this.dispenseProducts = [];
      this.selectedDispenseProducts = {};
    },
    
    // Load matching products for dispense
    async loadMatchingProducts() {
      if (!this.autoDispenseOrder) return;
      
      this.loadingDispenseProducts = true;
      
      try {
        // Get item IDs
        const itemIds = this.autoDispenseOrder.items
          .filter(item => !item.product_id) // Only get items without product_id
          .map(item => item.outboundorderitemid);
        
        if (itemIds.length === 0) {
          this.dispenseProducts = [];
          this.loadingDispenseProducts = false;
          return;
        }
        
        const requestData = {
          order_id: this.autoDispenseOrder.outboundorderid,
          item_ids: itemIds
        };
        
        console.log('Auto Dispense Request Data:', requestData);
        console.log('Store Name:', this.autoDispenseOrder.storename);
        console.log('Normalized Store Name:', this.normalizeStoreName(this.autoDispenseOrder.storename));

        const response = await axios.post(`${this.apiBaseUrl}/api/fbm-orders/find-dispense-products`, requestData, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
          }
        });
        
        console.log('Matching products response:', response);
        
        if (response.data && response.data.success) {
          this.dispenseProducts = response.data.data || [];
          
          // Auto-select first matching product for each item
          this.dispenseProducts.forEach(item => {
            if (item.matching_products && item.matching_products.length > 0) {
              this.selectedDispenseProducts[item.item_id] = item.matching_products[0];
              console.log(`Auto-selected product: ${item.matching_products[0].title} (${item.matching_products[0].condition}) for item with condition: ${item.ordered_condition}`);
            }
          });
        } else {
          console.error("Failed to find matching products:", response.data);
          this.dispenseProducts = [];
        }
      } catch (error) {
        console.error("Error loading matching products:", error);
        console.error("Error details:", error.response ? error.response.data : error.message);
        this.dispenseProducts = [];
      } finally {
        this.loadingDispenseProducts = false;
      }
    },
    
    // Select a product for dispense
    selectDispenseProduct(itemId, product) {
      console.log(`Selected product: ${product.title} (${product.condition}) for item ID: ${itemId}`);
      this.selectedDispenseProducts[itemId] = product;
    },
    
    // Confirm Auto Dispense
    async confirmAutoDispense() {
      if (!this.canConfirmDispense) return;
      
      try {
        // Format dispense items for API
        const dispenseItems = Object.entries(this.selectedDispenseProducts).map(([itemId, product]) => ({
          item_id: itemId,
          product_id: product.ProductID
        }));
        
        console.log('Confirming dispense with items:', dispenseItems);
        
        const response = await axios.post(`${this.apiBaseUrl}/api/fbm-orders/dispense`, {
          order_id: this.autoDispenseOrder.outboundorderid,
          dispense_items: dispenseItems
        }, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
          }
        });
        
        console.log('Dispense response:', response);
        
        if (response.data && response.data.success) {
          alert("Items dispensed successfully");
          this.closeAutoDispenseModal();
          
          // Important: Refresh data to show updated product details
          await this.fetchOrders();
          
          // If in the context of a specific order, refresh that order's data
          if (this.selectedOrder && this.selectedOrder.outboundorderid === this.autoDispenseOrder.outboundorderid) {
            const updatedOrders = this.orders.filter(order => 
              order.outboundorderid === this.selectedOrder.outboundorderid);
            if (updatedOrders.length > 0) {
              this.selectedOrder = {...updatedOrders[0]};
            }
          }
        } else {
          alert(`Error: ${response.data.message || 'Failed to dispense items'}`);
        }
      } catch (error) {
        console.error("Error confirming dispense:", error);
        alert("Failed to dispense items. Please try again.");
      }
    },
    
    // Cancel Dispense for an order
    async cancelDispense(order) {
      if (!this.hasDispensedItems(order)) return;
      
      if (!confirm("Are you sure you want to cancel dispense for this order?")) {
        return;
      }
      
      try {
        // Get items with product_id
        const itemIds = order.items
          .filter(item => item.product_id)
          .map(item => item.outboundorderitemid);
        
        if (itemIds.length === 0) return;
        
        console.log('Canceling dispense for items:', itemIds);
        
        const response = await axios.post(`${this.apiBaseUrl}/api/fbm-orders/cancel-dispense`, {
          order_id: order.outboundorderid,
          item_ids: itemIds
        }, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
          }
        });
        
        console.log('Cancel dispense response:', response);
        
        if (response.data && response.data.success) {
          alert("Dispense canceled successfully");
          
          // Important: Refresh data to remove product details
          await this.fetchOrders();
          
          // If in details view, refresh the selected order
          if (this.selectedOrder && this.selectedOrder.outboundorderid === order.outboundorderid) {
            const updatedOrders = this.orders.filter(o => 
              o.outboundorderid === this.selectedOrder.outboundorderid);
            if (updatedOrders.length > 0) {
              this.selectedOrder = {...updatedOrders[0]};
            }
          }
          
          // If in process modal, refresh the current process order
          if (this.currentProcessOrder && this.currentProcessOrder.outboundorderid === order.outboundorderid) {
            await this.refreshCurrentProcessOrder();
          }
        } else {
          alert(`Error: ${response.data.message || 'Failed to cancel dispense'}`);
        }
      } catch (error) {
        console.error("Error canceling dispense:", error);
        alert("Failed to cancel dispense. Please try again.");
      }
    },
    
    // INTEGRATED AUTO DISPENSE IN PROCESS MODAL
    
    // Start auto dispense within the process modal
    async startAutoDispenseInProcess() {
      this.processingAutoDispense = true;
      this.dispenseProducts = [];
      this.selectedDispenseProducts = {};
      
      // Only get items without product_id
      const itemsToDispense = this.currentProcessOrder.items
        .filter(item => !item.product_id)
        .map(item => item.outboundorderitemid);
      
      await this.loadDispenseProductsForProcess(itemsToDispense);
    },
    
    // Load matching products for items in process modal
    async loadDispenseProductsForProcess(itemIds) {
      this.loadingDispenseProducts = true;
      
      try {
        const requestData = {
          order_id: this.currentProcessOrder.outboundorderid,
          item_ids: itemIds
        };
        
        console.log('Auto Dispense in Process Request Data:', requestData);
        console.log('Current Process Order:', this.currentProcessOrder);
        console.log('Store Name:', this.currentProcessOrder.storename);
        console.log('Normalized Store Name:', this.normalizeStoreName(this.currentProcessOrder.storename));
        
        const response = await axios.post(`${this.apiBaseUrl}/api/fbm-orders/find-dispense-products`, requestData, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
          }
        });
        
        console.log('Matching products response:', response);
        
        if (response.data && response.data.success) {
          this.dispenseProducts = response.data.data || [];
          
          // Auto-select first matching product for each item
          this.dispenseProducts.forEach(item => {
            if (item.matching_products && item.matching_products.length > 0) {
              this.selectedDispenseProducts[item.item_id] = item.matching_products[0];
              console.log(`Auto-selected product: ${item.matching_products[0].title} (${item.matching_products[0].condition}) for item with condition: ${item.ordered_condition}`);
            }
          });
        } else {
          console.error("Failed to find matching products:", response.data);
          this.dispenseProducts = [];
        }
      } catch (error) {
        console.error("Error loading matching products:", error);
        console.error("Error details:", error.response ? error.response.data : error.message);
        console.error("Error stack:", error.stack);
        this.dispenseProducts = [];
      } finally {
        this.loadingDispenseProducts = false;
      }
    },
    
    // Cancel auto dispense in process modal and return to regular process view
    cancelAutoDispenseProcess() {
      this.processingAutoDispense = false;
      this.dispenseProducts = [];
      this.selectedDispenseProducts = {};
    },
    
    // Confirm auto dispense in process modal
    async confirmAutoDispenseInProcess() {
      if (!this.canConfirmDispense) return;
      
      try {
        // Format dispense items for API
        const dispenseItems = Object.entries(this.selectedDispenseProducts).map(([itemId, product]) => ({
          item_id: itemId,
          product_id: product.ProductID
        }));
        
        console.log('Confirming dispense with items:', dispenseItems);
        
        const response = await axios.post(`${this.apiBaseUrl}/api/fbm-orders/dispense`, {
          order_id: this.currentProcessOrder.outboundorderid,
          dispense_items: dispenseItems
        }, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
          }
        });
        
        console.log('Dispense response:', response);
        
        if (response.data && response.data.success) {
          alert("Items dispensed successfully");
          
          // Exit auto dispense mode
          this.processingAutoDispense = false;
          
          // Refresh the order data
          await this.refreshCurrentProcessOrder();
        } else {
          alert(`Error: ${response.data.message || 'Failed to dispense items'}`);
        }
      } catch (error) {
        console.error("Error confirming dispense:", error);
        alert("Failed to dispense items. Please try again.");
      }
    },
    
    // Refresh the current process order data
    async refreshCurrentProcessOrder() {
      if (!this.currentProcessOrder) return;
      
      try {
        // Get the updated order
        const response = await axios.get(`${this.apiBaseUrl}/api/fbm-orders`, {
          params: {
            page: 1,
            per_page: 1,
            search: this.currentProcessOrder.platform_order_id
          },
          withCredentials: true
        });
        
        if (response.data && response.data.success && response.data.data.length > 0) {
          // Update the current process order with fresh data
          const updatedOrder = response.data.data[0];
          
          // Process the order items to ensure product details field mapping
          const processedItems = Array.isArray(updatedOrder.items) ? updatedOrder.items.map(item => {
            if (item.product_id) {
              return {
                ...item,
                // Map backend field names to frontend field names if necessary
                warehouseLocation: item.warehouseLocation || '',
                serialNumber: item.serialNumber || '',
                rtCounter: item.rtCounter || '',
                FNSKU: item.FNSKU || ''
              };
            }
            return item;
          }) : [];
          
          this.currentProcessOrder = {
            ...updatedOrder,
            checked: false,
            items: processedItems
          };
          
          // Also update the corresponding order in the orders array
          const orderIndex = this.orders.findIndex(o => o.outboundorderid === this.currentProcessOrder.outboundorderid);
          if (orderIndex !== -1) {
            this.orders[orderIndex] = {
              ...this.currentProcessOrder,
              checked: this.orders[orderIndex].checked
            };
          }
          
          // Reset selectedItems to include all items
          this.selectedItems = this.currentProcessOrder.items.map(item => item.outboundorderitemid);
          
          // Reinitialize dispense items
          this.initializeDispenseItems();
        }
      } catch (error) {
        console.error("Error refreshing order data:", error);
      }
    },
    
    // MODIFIED: Process selected orders using persistentSelectedOrderIds
    processSelectedOrders() {
      // Get all orders that match the persistent selection IDs
      const selectedOrderIds = this.persistentSelectedOrderIds;
      
      if (selectedOrderIds.length === 0) {
        alert("Please select at least one order to process");
        return;
      }
      
      // Find the first selected order that's currently visible
      const visibleSelectedOrder = this.orders.find(order => selectedOrderIds.includes(order.outboundorderid));
      
      if (visibleSelectedOrder) {
        // Process the first visible selected order
        this.openProcessModal(visibleSelectedOrder);
      } else {
        // If no selected orders are visible on the current page, fetch the first one
        this.fetchSelectedOrderForProcessing(selectedOrderIds[0]);
      }
    },
    
    // NEW: Fetch an order by ID for processing
    async fetchSelectedOrderForProcessing(orderId) {
      try {
        this.loading = true;
        
        // Fetch the specific order by ID
        const response = await axios.get(`${this.apiBaseUrl}/api/fbm-orders/detail`, {
          params: { order_id: orderId },
          withCredentials: true
        });
        
        if (response.data && response.data.success) {
          const order = response.data.data;
          
          // Process the order items to ensure product details
          const processedItems = Array.isArray(order.items) ? order.items.map(item => {
            if (item.product_id) {
              return {
                ...item,
                warehouseLocation: item.warehouseLocation || '',
                serialNumber: item.serialNumber || '',
                rtCounter: item.rtCounter || '',
                FNSKU: item.FNSKU || ''
              };
            }
            return item;
          }) : [];
          
          const processedOrder = {
            ...order,
            checked: true,
            items: processedItems
          };
          
          // Open the process modal with this order
          this.openProcessModal(processedOrder);
        } else {
          alert("Could not fetch the selected order. Please try again.");
        }
      } catch (error) {
        console.error("Error fetching order for processing:", error);
        alert("Error fetching the selected order. Please try again.");
      } finally {
        this.loading = false;
      }
    },
    
    // Generate packing slip
    async generatePackingSlip(orderId) {
      try {
        console.log('Generating packing slip for:', orderId);
        const response = await axios.post(`${this.apiBaseUrl}/api/fbm-orders/packing-slip`, {
          order_id: orderId
        }, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
          }
        });
        
        console.log('Packing slip response:', response);
        
        if (response.data && response.data.success) {
          alert("Packing slip generated successfully");
          
          // If the API returns a URL to the generated PDF, open it
          if (response.data.pdf_url) {
            window.open(response.data.pdf_url, '_blank');
          }
        } else {
          alert(`Error: ${response.data.message || 'Failed to generate packing slip'}`);
        }
      } catch (error) {
        console.error("Error generating packing slip:", error);
        alert("Failed to generate packing slip. Please try again.");
      }
    },
    
    // Print shipping label
    async printShippingLabel(orderId) {
      try {
        console.log('Printing shipping label for:', orderId);
        const response = await axios.post(`${this.apiBaseUrl}/api/fbm-orders/shipping-label`, {
          order_id: orderId
        }, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
          }
        });
        
        console.log('Shipping label response:', response);
        
        if (response.data && response.data.success) {
          alert("Shipping label generated successfully");
          
          // If the API returns a URL to the generated label, open it
          if (response.data.label_url) {
            window.open(response.data.label_url, '_blank');
          }
        } else {
          alert(`Error: ${response.data.message || 'Failed to generate shipping label'}`);
        }
      } catch (error) {
        console.error("Error generating shipping label:", error);
        alert("Failed to generate shipping label. Please try again.");
      }
    },
    
    // Confirm and cancel order
    confirmCancelOrder(orderId) {
      if (confirm("Are you sure you want to cancel this order?")) {
        this.cancelOrder(orderId);
      }
    },
    
    // Cancel order
    async cancelOrder(orderId) {
      try {
        console.log('Canceling order:', orderId);
        const response = await axios.post(`${this.apiBaseUrl}/api/fbm-orders/cancel`, {
          order_id: orderId
        }, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
          }
        });
        
        console.log('Cancel response:', response);
        
        if (response.data && response.data.success) {
          alert("Order canceled successfully");
          this.closeOrderDetailsModal();
          this.fetchOrders(); // Refresh data
        } else {
          alert(`Error: ${response.data.message || 'Failed to cancel order'}`);
        }
      } catch (error) {
        console.error("Error canceling order:", error);
        alert("Failed to cancel order. Please try again.");
      }
    },
    
    // MODIFIED: Print shipping labels for selected orders using persistentSelectedOrderIds
    printShippingLabels() {
      const selectedOrderIds = this.persistentSelectedOrderIds;
      
      if (selectedOrderIds.length === 0) {
        alert("Please select at least one order to print labels");
        return;
      }
      
      // Print labels for each selected order
      alert(`Printing labels for ${selectedOrderIds.length} orders...`);
      
      selectedOrderIds.forEach(id => this.printShippingLabel(id));
    },
    
    // MODIFIED: Generate packing slips for selected orders using persistentSelectedOrderIds
    generatePackingSlips() {
      const selectedOrderIds = this.persistentSelectedOrderIds;
      
      if (selectedOrderIds.length === 0) {
        alert("Please select at least one order to generate packing slips");
        return;
      }
      
      // Generate packing slips for each selected order
      alert(`Generating packing slips for ${selectedOrderIds.length} orders...`);
      
      selectedOrderIds.forEach(id => this.generatePackingSlip(id));
    }
  },
  watch: {
    // Watch for global search changes
    searchQuery() {
      this.currentPage = 1;
      this.fetchOrders();
    }
  },
  mounted() {
    console.log('FbmOrderModule mounted');
    
    // Set CSRF token
    const token = document.querySelector('meta[name="csrf-token"]');
    if (token) {
      axios.defaults.headers.common['X-CSRF-TOKEN'] = token.getAttribute('content');
    } else {
      console.warn('CSRF token not found');
    }
    
    // Add Font Awesome if not already included
    if (!document.querySelector('link[href*="font-awesome"]')) {
      const fontAwesome = document.createElement('link');
      fontAwesome.rel = 'stylesheet';
      fontAwesome.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css';
      document.head.appendChild(fontAwesome);
    }
    
    // Fetch stores for dropdown
    this.fetchStores();
    
    // Fetch initial data
    this.fetchOrders();
    
    // Initialize dispense items selection
    this.initializeDispenseItems();
  }
}