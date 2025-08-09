<template>
    <!-- New Scanned Items Modal -->
    <div v-if="show" class="new-scanned-modal">
        <div class="new-scanned-modal-content">
            <div class="new-scanned-modal-header">
                <h2>New Scanned Items</h2>
                <button class="new-scanned-modal-close" @click="closeModal">
                    &times;
                </button>
            </div>
            
            <div class="new-scanned-modal-filters">
                <input 
                    type="text" 
                    v-model="searchQuery" 
                    placeholder="Search by any field..." 
                    @input="filterItems"
                    class="search-input"
                >
                
                <div class="date-filters">
                    <label for="startDate">Start Date:</label>
                    <input 
                        type="date" 
                        id="startDate" 
                        v-model="startDate" 
                        @change="fetchItems"
                    >
                    
                    <label for="endDate">End Date:</label>
                    <input 
                        type="date" 
                        id="endDate" 
                        v-model="endDate" 
                        @change="fetchItems"
                    >
                    
                    <button @click="applyDateFilter" class="btn-filter">Apply Filter</button>
                    <button @click="clearDateFilter" class="btn-clear">Clear Filter</button>
                </div>
                
                <div class="row-count">
                    <strong>Total Items: {{ filteredItems.length }}</strong>
                </div>
            </div>

            <div class="new-scanned-modal-body">
                <div v-if="loading" class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i>
                    Loading...
                </div>
                
                <div v-else class="table-container">
                    <table class="new-scanned-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;">
                                    <input 
                                        type="checkbox" 
                                        @change="toggleAllItems"
                                        v-model="selectAll"
                                        title="Select/Deselect All"
                                    >
                                    <br>
                                    <small>Select</small>
                                </th>
                                <th style="width: 100px;">
                                    <div class="header-content">
                                        <strong>RT Number</strong>
                                        <small>Counter ID</small>
                                    </div>
                                </th>
                                <th style="width: 120px;">
                                    <div class="header-content">
                                        <strong>Location</strong>
                                        <small>Warehouse</small>
                                    </div>
                                </th>
                                <th style="width: 140px;">
                                    <div class="header-content">
                                        <strong>MSKU</strong>
                                        <small>Merchant SKU</small>
                                    </div>
                                </th>
                                <th style="width: 160px;">
                                    <div class="header-content">
                                        <strong>Scan Date</strong>
                                        <small>Insert Date</small>
                                    </div>
                                </th>
                                <th style="width: 140px;">
                                    <div class="header-content">
                                        <strong>FNSKU</strong>
                                        <small>Fulfillment SKU</small>
                                    </div>
                                </th>
                                <th style="width: 120px;">
                                    <div class="header-content">
                                        <strong>Condition</strong>
                                        <small>Item Grade</small>
                                    </div>
                                </th>
                                <th style="width: 120px;">
                                    <div class="header-content">
                                        <strong>ASIN</strong>
                                        <small>Product ID</small>
                                    </div>
                                </th>
                                <th style="min-width: 200px;">
                                    <div class="header-content">
                                        <strong>Product Title</strong>
                                        <small>Amazon Title</small>
                                    </div>
                                </th>
                                <th style="width: 140px;">
                                    <div class="header-content">
                                        <strong>Scanned By</strong>
                                        <small>Employee</small>
                                    </div>
                                </th>
                                <th style="width: 140px;">
                                    <div class="header-content">
                                        <strong>Tracking #</strong>
                                        <small>Shipment</small>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="filteredItems.length === 0">
                                <td colspan="11" class="text-center empty-message">
                                    <div class="empty-state">
                                        <i class="fas fa-search"></i>
                                        <p>No items found for the selected date range or search criteria.</p>
                                        <small>Try adjusting your filters or date range.</small>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else v-for="item in filteredItems" :key="item.ProductID">
                                <td class="text-center">
                                    <input 
                                        type="checkbox" 
                                        v-model="item.selected"
                                        @change="updateItemStatus(item)"
                                        :checked="item.fbm_list_status === 'listed'"
                                    >
                                </td>
                                <td class="rt-number">
                                    <strong>{{ formatRTNumber(item.rtcounter, item.StoreName) }}</strong>
                                </td>
                                <td class="location">
                                    <span class="location-badge">{{ item.warehouselocation || 'N/A' }}</span>
                                </td>
                                <td class="msku">
                                    <code>{{ item.MSKUviewer || 'N/A' }}</code>
                                </td>
                                <td class="scan-date">
                                    <div class="date-display">
                                        <div class="date-main">
                                            {{ item.stockroom_insert_date ? item.stockroom_insert_date.split(' ')[0] : 'N/A' }}
                                        </div>
                                        <div class="time-sub">
                                            {{ item.stockroom_insert_date ? item.stockroom_insert_date.split(' ')[1] : '' }}
                                        </div>
                                    </div>
                                </td>
                                <td class="fnsku">
                                    <code>{{ item.FNSKUviewer || 'N/A' }}</code>
                                </td>
                                <td class="condition">
                                    <span class="condition-badge" :class="getConditionClass(item.gradingviewer)">
                                        {{ item.gradingviewer || 'N/A' }}
                                    </span>
                                </td>
                                <td class="asin">
                                    <a 
                                        :href="`https://ims.tecniquality.com/Admin/modules/stockroom/stockroom.php?search=${item.ASINviewer}`" 
                                        target="_blank" 
                                        class="asin-link"
                                        v-if="item.ASINviewer"
                                    >
                                        {{ item.ASINviewer }}
                                    </a>
                                    <span v-else>N/A</span>
                                </td>
                                <td class="product-title">
                                    <a 
                                        :href="`https://www.amazon.com/dp/${item.ASINviewer}`" 
                                        target="_blank" 
                                        class="amazon-link"
                                        :title="item.AStitle"
                                        v-if="item.ASINviewer && item.AStitle"
                                    >
                                        {{ truncateText(item.AStitle, 60) }}
                                    </a>
                                    <span v-else>{{ item.AStitle || 'N/A' }}</span>
                                </td>
                                <!-- EMPLOYEE COLUMN WITH DEBUG -->
                                <td class="employee">
                                    <span 
                                        class="employee-name" 
                                        :class="{
                                            'employee-missing': !item.employeeName || item.employeeName === 'N/A' || item.employeeName === '',
                                            'employee-found': item.employeeName && item.employeeName !== 'N/A' && item.employeeName !== ''
                                        }"
                                        :title="`Debug: RT ${item.rtcounter} - Employee: '${item.employeeName}'`"
                                    >
                                        {{ item.employeeName || 'No Employee' }}
                                    </span>
                                    <!-- Debug info in small text -->
                                    <small class="debug-text">RT: {{ item.rtcounter }}</small>
                                </td>
                                <td class="tracking">
                                    <span v-if="item.shipment_tracking_number" class="tracking-number">
                                        {{ item.shipment_tracking_number }}
                                    </span>
                                    <span v-else class="no-tracking">-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: 'NewScannedItemModal',
    props: {
        show: {
            type: Boolean,
            default: false
        }
    },
    emits: ['close', 'update-count'],
    data() {
        return {
            loading: false,
            items: [],
            filteredItems: [],
            searchQuery: "",
            startDate: "",
            endDate: "",
            selectAll: false
        };
    },
    methods: {
        // Format RT number based on store name
        formatRTNumber(rtCounter, storeName) {
            const paddedCounter = String(rtCounter).padStart(5, "0");

            if (storeName === "RenovarTech") {
                return `RT ${paddedCounter}`;
            } else if (storeName === "Allrenewed") {
                return `AR ${paddedCounter}`;
            } else {
                return `#${paddedCounter}`;
            }
        },

        // Get condition class for styling
        getConditionClass(condition) {
            if (!condition) return '';
            
            const normalizedCondition = condition.toLowerCase().replace(/[^a-z]/g, '');
            
            if (normalizedCondition.includes('new')) return 'condition-new';
            if (normalizedCondition.includes('used')) return 'condition-used';
            if (normalizedCondition.includes('refurbished')) return 'condition-refurbished';
            
            return 'condition-default';
        },

        // Truncate text helper
        truncateText(text, maxLength) {
            if (!text) return '';
            return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
        },

        // Fetch items from API - WITH ENHANCED DEBUGGING
        async fetchItems() {
            this.loading = true;
            try {
                // Set default dates if not provided
                if (!this.startDate && !this.endDate) {
                    const today = new Date();
                    const fourDaysAgo = new Date(today);
                    fourDaysAgo.setDate(today.getDate() - 4);
                    
                    this.endDate = today.toISOString().split('T')[0];
                    this.startDate = fourDaysAgo.toISOString().split('T')[0];
                }

                console.log('🔍 Fetching items with dates:', {
                    startDate: this.startDate,
                    endDate: this.endDate
                });

                const response = await axios.get(
                    `${API_BASE_URL}/api/stockroom/new-scanned-items`,
                    {
                        params: {
                            startDate: this.startDate,
                            endDate: this.endDate
                        },
                        withCredentials: true,
                    }
                );

                console.log('📦 Raw API Response:', response.data);

                // Debug: Check what's in the raw data
                if (response.data.data && response.data.data.length > 0) {
                    console.log('🧪 First item raw data:', response.data.data[0]);
                }

                // Ensure each item has the required properties with fallback values
                this.items = (response.data.data || []).map((item, index) => {
                    const processedItem = {
                        ProductID: item.ProductID || '',
                        rtcounter: item.rtcounter || '',
                        warehouselocation: item.warehouselocation || '',
                        MSKUviewer: item.MSKUviewer || item.MSKU || '',
                        stockroom_insert_date: item.stockroom_insert_date || '',
                        FNSKUviewer: item.FNSKUviewer || item.FNSKU || '',
                        gradingviewer: item.gradingviewer || item.grading || '',
                        ASINviewer: item.ASINviewer || item.ASIN || '',
                        AStitle: item.AStitle || item.internal || '',
                        StoreName: item.StoreName || item.storename || '',
                        employeeName: item.employeeName || '',
                        shipment_tracking_number: item.shipment_tracking_number || '',
                        fbm_list_status: item.fbm_list_status || null,
                        selected: item.fbm_list_status === 'listed'
                    };

                    // Debug each item's employee name
                    if (index < 3) { // Only log first 3 items to avoid spam
                        console.log(`👤 Item ${index + 1} Employee Data:`, {
                            rtcounter: processedItem.rtcounter,
                            rawEmployeeName: item.employeeName,
                            processedEmployeeName: processedItem.employeeName,
                            ProductID: processedItem.ProductID
                        });
                    }

                    return processedItem;
                });
                
                this.filteredItems = [...this.items];
                
                // Summary debug info
                console.log('✅ Processing complete:', {
                    totalItems: this.items.length,
                    itemsWithEmployeeNames: this.items.filter(item => item.employeeName && item.employeeName !== '').length,
                    itemsWithoutEmployeeNames: this.items.filter(item => !item.employeeName || item.employeeName === '').length
                });
                
            } catch (error) {
                console.error("❌ Error fetching new scanned items:", error);
                console.error("📋 Error details:", error.response?.data);
                this.items = [];
                this.filteredItems = [];
            } finally {
                this.loading = false;
            }
        },

        // Filter items based on search query
        filterItems() {
            const query = this.searchQuery.toLowerCase();
            if (!query) {
                this.filteredItems = [...this.items];
            } else {
                this.filteredItems = this.items.filter(item => {
                    return Object.values(item).some(value => 
                        String(value).toLowerCase().includes(query)
                    );
                });
            }
        },

        // Apply date filter
        applyDateFilter() {
            this.fetchItems();
        },

        // Clear date filter
        clearDateFilter() {
            this.startDate = "";
            this.endDate = "";
            this.fetchItems();
        },

        // Toggle all items selection
        toggleAllItems() {
            this.filteredItems.forEach(item => {
                item.selected = this.selectAll;
                item.fbm_list_status = this.selectAll ? 'listed' : null;
                this.updateItemStatus(item, false); // false = don't revert on error
            });
        },

        // Update individual item status
        async updateItemStatus(item, revertOnError = true) {
            try {
                const status = item.selected ? 'listed' : null;
                
                const response = await axios.post(
                    `${API_BASE_URL}/api/stockroom/update-fbm-status`,
                    {
                        id: item.ProductID,
                        status: status
                    },
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]'
                            )?.content,
                        },
                    }
                );

                if (response.data.success) {
                    item.fbm_list_status = status;
                } else {
                    console.error("Failed to update FBM status:", response.data.message);
                    if (revertOnError) {
                        item.selected = !item.selected;
                    }
                }
            } catch (error) {
                console.error("Error updating FBM status:", error);
                if (revertOnError) {
                    item.selected = !item.selected;
                }
            }
        },

        // Close modal
        closeModal() {
            this.searchQuery = "";
            this.selectAll = false;
            this.$emit('close');
        },

        // Initialize modal when opened
        initializeModal() {
            if (this.show) {
                this.fetchItems();
            }
        }
    },
    watch: {
        show(newValue) {
            if (newValue) {
                this.initializeModal();
            }
        }
    },
    mounted() {
        // Configure axios defaults
        axios.defaults.baseURL = window.location.origin;
        axios.defaults.withCredentials = true;

        // Set CSRF token
        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) {
            axios.defaults.headers.common["X-CSRF-TOKEN"] = token.getAttribute("content");
        }
    }
};
</script>

<style scoped>
/* New Scanned Modal Styles - WIDER VERSION */
.new-scanned-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    overflow: auto;
}

.new-scanned-modal-content {
    background-color: #fefefe;
    margin: 1% auto;
    padding: 0;
    border: 1px solid #888;
    width: 98%; /* Made wider - was 95% */
    max-width: 1800px; /* Increased max width - was 1400px */
    height: 95vh; /* Made taller - was 90vh */
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    position: relative;
}

.new-scanned-modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    background-color: #f8f9fa;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.new-scanned-modal-header h2 {
    margin: 0;
    color: #333;
}

.new-scanned-modal-close {
    background: none;
    border: none;
    font-size: 28px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
    padding: 0;
    line-height: 1;
}

.new-scanned-modal-close:hover {
    color: #000;
}

.new-scanned-modal-filters {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    background-color: #f8f9fa;
}

.search-input {
    width: 100%;
    padding: 8px 12px;
    margin-bottom: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.date-filters {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.date-filters label {
    font-weight: 600;
    color: #333;
}

.date-filters input[type="date"] {
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.btn-filter, .btn-clear {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
}

.btn-filter {
    background-color: #007bff;
    color: white;
}

.btn-filter:hover {
    background-color: #0056b3;
}

.btn-clear {
    background-color: #6c757d;
    color: white;
}

.btn-clear:hover {
    background-color: #545b62;
}

.row-count {
    text-align: right;
    color: #333;
    font-size: 14px;
}

.new-scanned-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
}

.loading-spinner {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 200px;
    font-size: 18px;
    color: #6c757d;
}

.loading-spinner i {
    margin-right: 10px;
    font-size: 24px;
}

.table-container {
    overflow-x: auto;
}

.new-scanned-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    background-color: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    min-width: 1600px; /* Ensure minimum width for all columns */
}

.new-scanned-table thead {
    background-color: #1a252f !important;
    color: white !important;
    position: sticky;
    top: 0;
    z-index: 10;
}

.new-scanned-table thead th {
    background-color: #1a252f !important;
    color: white !important;
    padding: 12px 8px !important;
    text-align: left !important;
    font-weight: 600 !important;
    font-size: 12px !important;
    border-right: 1px solid rgba(255, 255, 255, 0.2) !important;
    white-space: nowrap !important;
    vertical-align: top !important;
}

.new-scanned-table thead th:last-child {
    border-right: none;
}

.header-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.header-content strong {
    font-size: 13px;
    font-weight: 700;
    line-height: 1.2;
}

.header-content small {
    font-size: 10px;
    opacity: 0.8;
    font-weight: 400;
    line-height: 1;
}

.new-scanned-table tbody td {
    padding: 10px 8px;
    border-bottom: 1px solid #dee2e6;
    border-right: 1px solid #dee2e6;
    color: #495057;
    font-size: 12px;
    vertical-align: middle;
}

.new-scanned-table tbody td:last-child {
    border-right: none;
}

.new-scanned-table tbody tr:nth-child(even) {
    background-color: #f8f9fa;
}

.new-scanned-table tbody tr:hover {
    background-color: #e3f2fd;
}

.rt-number strong {
    color: #007bff;
    font-weight: 600;
}

.location-badge {
    background-color: #e9ecef;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 500;
}

.msku code, .fnsku code {
    background-color: #f8f9fa;
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 11px;
    color: #6c757d;
}

.date-display {
    text-align: center;
}

.date-main {
    font-weight: 600;
    font-size: 12px;
    color: #495057;
}

.time-sub {
    font-size: 10px;
    color: #6c757d;
    margin-top: 2px;
}

.condition-badge {
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 500;
    text-transform: uppercase;
}

.condition-new { 
    background-color: #d4edda; 
    color: #155724; 
}

.condition-used { 
    background-color: #fff3cd; 
    color: #856404; 
}

.condition-refurbished { 
    background-color: #d1ecf1; 
    color: #0c5460; 
}

.condition-default { 
    background-color: #e9ecef; 
    color: #495057; 
}

.product-title {
    max-width: 250px;
    word-wrap: break-word;
}

/* ENHANCED EMPLOYEE NAME STYLES */
.employee {
    width: 140px;
    min-width: 140px;
}

.employee-name {
    font-weight: 500;
    color: #495057;
    display: block;
    line-height: 1.2;
}

.employee-name.employee-missing {
    color: #dc3545;
    background-color: #f8d7da;
    padding: 2px 4px;
    border-radius: 3px;
    font-weight: 600;
    font-style: italic;
}

.employee-name.employee-found {
    color: #28a745;
    font-weight: 600;
}

.debug-text {
    font-size: 9px;
    color: #6c757d;
    display: block;
    margin-top: 2px;
    font-style: italic;
}

.tracking-number {
    font-family: monospace;
    font-size: 11px;
    background-color: #f8f9fa;
    padding: 2px 4px;
    border-radius: 3px;
}

.no-tracking {
    color: #6c757d;
    font-style: italic;
}

.empty-state {
    padding: 40px 20px;
    text-align: center;
}

.empty-state i {
    font-size: 48px;
    color: #dee2e6;
    margin-bottom: 15px;
}

.empty-state p {
    font-size: 16px;
    color: #6c757d;
    margin-bottom: 5px;
}

.empty-state small {
    color: #adb5bd;
}

.asin-link {
    color: #28a745 !important;
    text-decoration: none;
    font-weight: 500;
}

.asin-link:hover {
    color: #1e7e34 !important;
    text-decoration: underline;
}

.amazon-link {
    color: #007bff !important;
    text-decoration: none;
}

.amazon-link:hover {
    color: #0056b3 !important;
    text-decoration: underline;
}

.text-center {
    text-align: center;
}

.empty-message {
    padding: 20px !important;
    background-color: #f8f9fa;
}

/* NOTIFICATION BADGE FIXES - RED AND NOT CUT OFF */


/* Mobile Responsive */
@media (max-width: 768px) {
    .new-scanned-modal-content {
        width: 100%;
        height: 100vh;
        margin: 0;
        border-radius: 0;
    }
    
    .date-filters {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .date-filters > * {
        margin-bottom: 10px;
    }
    
    .new-scanned-table {
        font-size: 12px;
        min-width: 1200px; /* Maintain minimum width on mobile */
    }
    
    .new-scanned-table thead th,
    .new-scanned-table tbody td {
        padding: 8px 6px;
    }
}
</style>