import Chart from "chart.js/auto";
import Swal from "sweetalert2";

const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "InventoryStatistics",
    data() {
        return {
            loading: false,
            loadingDetails: false,
            
            // Summary Data
            totalItems: 0,
            uniqueAsins: 0,
            unlabeledItems: 0,
            totalQuantity: 0,
            
            // Module Distribution
            moduleData: [],
            moduleColors: [
                '#3b82f6', '#10b981', '#f59e0b', '#ef4444', 
                '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16',
                '#f97316', '#6366f1', '#14b8a6', '#a855f7'
            ],
            
            // Chart Types
            moduleChartType: 'bar',
            soldChartType: 'bar',
            returnChartType: 'bar',
            asinBreakdownChartType: 'bar',
            chartTypeOptions: [
                { label: 'Bar Chart', value: 'bar' },
                { label: 'Horizontal Bar', value: 'horizontalBar' },
                { label: 'Pie Chart', value: 'pie' },
                { label: 'Doughnut Chart', value: 'doughnut' },
                { label: 'Line Chart', value: 'line' },
                { label: 'Polar Area', value: 'polarArea' },
            ],
            
            // Charts
            moduleChartInstance: null,
            soldChartInstance: null,
            returnChartInstance: null,
            asinBreakdownChartInstance: null,
            
            // Sold & Return Data
            soldData: [],
            returnData: [],
            
            // ASIN Details
            asinData: [],
            filteredAsinData: [],
            selectedModule: null,
            asinSearchQuery: '',
            
            // Modal
            showDetailsModal: false,
            selectedAsinDetails: null,
            asinDetailItems: [],
            
            // Filter Options
            moduleFilterOptions: [
                { label: 'All Modules', value: null },
                { label: 'Orders', value: 'Orders' },
                { label: 'Received', value: 'Received' },
                { label: 'Labeling', value: 'Labeling' },
                { label: 'Testing', value: 'Testing' },
                { label: 'Cleaning', value: 'Cleaning' },
                { label: 'Packing', value: 'Packing' },
                { label: 'Validation', value: 'Validation' },
                { label: 'Production Area', value: 'Production Area' },
                { label: 'Stockroom', value: 'Stockroom' },
                { label: 'Shipment', value: 'Shipment' },
                { label: 'Returnlist', value: 'Returnlist' },
                { label: 'Soldlist', value: 'Soldlist' },
            ],
        };
    },
    
    computed: {
        // Filtered ASINs for the selected module
        filteredModuleAsins() {
            if (!this.selectedModule) return [];
            
            const moduleAsins = this.asinData.filter(item => 
                item.modules && item.modules[this.selectedModule]
            );
            
            // Sort by count in selected module (descending)
            moduleAsins.sort((a, b) => 
                (b.modules[this.selectedModule] || 0) - (a.modules[this.selectedModule] || 0)
            );
            
            return moduleAsins;
        }
    },
    
    methods: {
        formatNumber(num) {
            if (!num) return '0';
            return num.toLocaleString();
        },
        
        getModuleSeverity(module) {
            const severityMap = {
                'Orders': 'info',
                'Received': 'secondary',
                'Labeling': 'warning',
                'Testing': 'info',
                'Cleaning': 'info',
                'Packing': 'primary',
                'Validation': 'success',
                'Production Area': 'warning',
                'Stockroom': 'success',
                'Shipment': 'info',
                'Returnlist': 'danger',
                'Soldlist': 'success',
            };
            return severityMap[module] || 'secondary';
        },
        
        async fetchStatistics() {
            this.loading = true;
            try {
                // Make sure we're calling the API route with /api prefix
                const apiUrl = `${API_BASE_URL}/api/inventory-statistics/summary`;
                console.log('🔍 Fetching from:', apiUrl);
                
                const response = await axios.get(apiUrl);
                
                console.log('📡 Raw response:', response);
                console.log('📊 Response data:', response.data);
                console.log('📊 Response data (stringified):', JSON.stringify(response.data).substring(0, 500));
                console.log('📋 Response status:', response.status);
                console.log('📋 Response headers:', response.headers);
                console.log('📋 Content-Type:', response.headers['content-type']);
                
                const data = response.data;
                
                // Log the exact structure we received
                console.log('🔍 Data type:', typeof data);
                console.log('🔍 Data is array?', Array.isArray(data));
                console.log('🔍 Data is null?', data === null);
                console.log('🔍 Data is undefined?', data === undefined);
                console.log('🔍 Data keys:', data ? Object.keys(data) : 'no keys');
                console.log('🔍 Has summary?', data?.summary);
                console.log('🔍 Full data structure:', data);
                
                // Check if data structure is correct
                if (!data || typeof data !== 'object') {
                    console.error('❌ Invalid response format - data is not an object');
                    console.error('❌ Received type:', typeof data);
                    console.error('❌ Received value:', data);
                    throw new Error('Invalid response format - expected object, got ' + typeof data);
                }
                
                // Update summary cards with safe fallbacks
                if (data.summary) {
                    console.log('✅ Summary found:', data.summary);
                    this.totalItems = data.summary.total_items || 0;
                    this.uniqueAsins = data.summary.unique_asins || 0;
                    this.unlabeledItems = data.summary.unlabeled_items || 0;
                    this.totalQuantity = data.summary.total_quantity || 0;
                } else {
                    console.warn('⚠️ No summary data in response - using defaults');
                    this.totalItems = 0;
                    this.uniqueAsins = 0;
                    this.unlabeledItems = 0;
                    this.totalQuantity = 0;
                }
                
                // Update module distribution
                this.moduleData = Array.isArray(data.module_distribution) ? data.module_distribution : [];
                console.log('📦 Module data:', this.moduleData.length, 'modules');
                
                // Update sold and return data
                this.soldData = Array.isArray(data.sold_items) ? data.sold_items : [];
                this.returnData = Array.isArray(data.return_items) ? data.return_items : [];
                console.log('💰 Sold/Return data:', this.soldData.length, '/', this.returnData.length);
                
                // Update ASIN data
                this.asinData = Array.isArray(data.asin_details) ? data.asin_details : [];
                this.filteredAsinData = [...this.asinData];
                console.log('🏷️ ASIN data:', this.asinData.length, 'items');
                
                console.log('✅ All data loaded successfully');
                
                // Render charts
                this.$nextTick(() => {
                    console.log('🎨 Rendering charts...');
                    this.renderModuleChart();
                    this.renderSoldChart();
                    this.renderReturnChart();
                });
                
            } catch (error) {
                console.error('❌ Error fetching statistics:', error);
                console.error('❌ Error message:', error.message);
                console.error('❌ Error response:', error.response);
                console.error('❌ Error response data:', error.response?.data);
                console.error('❌ Error response status:', error.response?.status);
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.response?.data?.message || error.message || 'Failed to load statistics data',
                    footer: 'Check console (F12) for details'
                });
            } finally {
                this.loading = false;
            }
        },
        
        renderModuleChart() {
            if (this.moduleChartInstance) {
                this.moduleChartInstance.destroy();
            }
            
            const ctx = this.$refs.moduleChart?.getContext('2d');
            if (!ctx || !this.moduleData.length) return;
            
            const labels = this.moduleData.map(m => m.name);
            const data = this.moduleData.map(m => m.count);
            
            // Determine if it's a horizontal chart
            const isHorizontal = this.moduleChartType === 'horizontalBar';
            const chartType = isHorizontal ? 'bar' : this.moduleChartType;
            
            // Base configuration
            const config = {
                type: chartType,
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Items',
                        data: data,
                        backgroundColor: this.moduleColors,
                        borderColor: ['pie', 'doughnut', 'polarArea'].includes(chartType) ? '#ffffff' : this.moduleColors,
                        borderWidth: 1,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onClick: (event, elements) => {
                        if (elements.length > 0) {
                            const index = elements[0].index;
                            const moduleName = this.moduleData[index].name;
                            this.filterByModule(moduleName);
                            
                            // Scroll to ASIN breakdown chart
                            this.$nextTick(() => {
                                const breakdownElement = document.querySelector('.asin-stats-table');
                                if (breakdownElement) {
                                    breakdownElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                }
                            });
                        }
                    },
                    plugins: {
                        legend: {
                            display: ['pie', 'doughnut', 'polarArea'].includes(this.moduleChartType),
                            position: 'right'
                        },
                        title: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const label = context.label || '';
                                    const value = this.formatNumber(context.parsed.y || context.parsed);
                                    return `${label}: ${value} items`;
                                }
                            }
                        }
                    }
                }
            };
            
            // Add axis configuration for bar/line charts
            if (['bar', 'line'].includes(chartType)) {
                config.options.indexAxis = isHorizontal ? 'y' : 'x';
                config.options.scales = {
                    [isHorizontal ? 'x' : 'y']: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => this.formatNumber(value)
                        }
                    }
                };
            }
            
            this.moduleChartInstance = new Chart(ctx, config);
        },
        
        renderAsinBreakdownChart() {
            if (this.asinBreakdownChartInstance) {
                this.asinBreakdownChartInstance.destroy();
            }
            
            const ctx = this.$refs.asinBreakdownChart?.getContext('2d');
            if (!ctx || !this.filteredModuleAsins.length) return;
            
            // Take top 20 ASINs
            const topAsins = this.filteredModuleAsins.slice(0, 20);
            
            const labels = topAsins.map(item => item.asin || 'UNLABELED');
            const itemCounts = topAsins.map(item => item.modules[this.selectedModule] || 0);
            const quantities = topAsins.map(item => {
                // Get total quantity for this ASIN in the selected module
                // We need to calculate based on the actual quantity field
                return item.modules[this.selectedModule] || 0;
            });
            
            // Generate dynamic colors
            const colors = topAsins.map((_, index) => {
                const hue = (index * 360 / topAsins.length);
                return `hsl(${hue}, 70%, 60%)`;
            });
            
            const isHorizontal = this.asinBreakdownChartType === 'horizontalBar';
            const chartType = isHorizontal ? 'bar' : this.asinBreakdownChartType;
            
            const config = {
                type: chartType,
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Item Count',
                        data: itemCounts,
                        backgroundColor: colors,
                        borderColor: ['pie', 'doughnut', 'polarArea'].includes(chartType) ? '#ffffff' : colors,
                        borderWidth: 1,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onClick: (event, elements) => {
                        if (elements.length > 0) {
                            const index = elements[0].index;
                            const asin = labels[index];
                            
                            // Search for this ASIN
                            this.asinSearchQuery = asin;
                            this.filterAsinData();
                            
                            // Scroll to table
                            const tableElement = document.querySelector('.asin-stats-table');
                            if (tableElement) {
                                tableElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: ['pie', 'doughnut', 'polarArea'].includes(chartType),
                            position: 'right',
                            labels: {
                                generateLabels: (chart) => {
                                    const data = chart.data;
                                    if (data.labels.length && data.datasets.length) {
                                        return data.labels.map((label, i) => {
                                            const itemCount = data.datasets[0].data[i];
                                            const asinItem = topAsins[i];
                                            const qty = asinItem.total_quantity || 0;
                                            return {
                                                text: `${label} (${itemCount} items, Qty: ${qty})`.substring(0, 60),
                                                fillStyle: data.datasets[0].backgroundColor[i],
                                                hidden: false,
                                                index: i
                                            };
                                        });
                                    }
                                    return [];
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const asinItem = topAsins[context.dataIndex];
                                    const itemCount = this.formatNumber(context.parsed.y || context.parsed);
                                    const totalQty = this.formatNumber(asinItem.total_quantity || 0);
                                    return [
                                        `ASIN: ${context.label}`,
                                        `Items in ${this.selectedModule}: ${itemCount}`,
                                        `Total Quantity: ${totalQty}`,
                                        `Title: ${asinItem.title || 'No title'}`
                                    ];
                                }
                            }
                        }
                    }
                }
            };
            
            // Add axis configuration for bar/line charts
            if (['bar', 'line'].includes(chartType)) {
                config.options.indexAxis = isHorizontal ? 'y' : 'x';
                config.options.scales = {
                    [isHorizontal ? 'x' : 'y']: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => this.formatNumber(value)
                        }
                    }
                };
            }
            
            this.asinBreakdownChartInstance = new Chart(ctx, config);
        },
        
        renderSoldChart() {
            if (this.soldChartInstance) {
                this.soldChartInstance.destroy();
            }
            
            const ctx = this.$refs.soldChart?.getContext('2d');
            if (!ctx) {
                console.warn('Sold chart canvas not found');
                return;
            }
            
            if (!this.soldData.length) {
                console.log('No sold data available');
                return;
            }
            
            const labels = this.soldData.map(item => item.asin || 'UNLABELED');
            const data = this.soldData.map(item => item.count);
            
            // Generate colors for pie/doughnut charts
            const colors = this.soldData.map((_, index) => {
                const hue = (index * 360 / this.soldData.length);
                return `hsl(${hue}, 70%, 60%)`;
            });
            
            const isHorizontal = this.soldChartType === 'horizontalBar';
            const chartType = isHorizontal ? 'bar' : this.soldChartType;
            
            const config = {
                type: chartType,
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Sold Items',
                        data: data,
                        backgroundColor: ['pie', 'doughnut', 'polarArea'].includes(chartType) ? colors : '#10b981',
                        borderColor: ['pie', 'doughnut', 'polarArea'].includes(chartType) ? '#ffffff' : '#10b981',
                        borderWidth: 1,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onClick: (event, elements) => {
                        if (elements.length > 0) {
                            const index = elements[0].index;
                            const asinItem = this.soldData[index];
                            
                            this.asinSearchQuery = asinItem.asin || 'UNLABELED';
                            this.filterAsinData();
                            
                            const tableElement = document.querySelector('.asin-stats-table');
                            if (tableElement) {
                                tableElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: ['pie', 'doughnut', 'polarArea'].includes(chartType),
                            position: 'right'
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const item = this.soldData[context.dataIndex];
                                    const value = this.formatNumber(context.parsed.x || context.parsed);
                                    return [
                                        `Count: ${value}`,
                                        `Title: ${item.title || 'No title'}`
                                    ];
                                }
                            }
                        }
                    }
                }
            };
            
            if (['bar', 'line'].includes(chartType)) {
                config.options.indexAxis = isHorizontal ? 'y' : 'x';
                config.options.scales = {
                    [isHorizontal ? 'x' : 'y']: {
                        beginAtZero: true
                    }
                };
            }
            
            this.soldChartInstance = new Chart(ctx, config);
        },
        
        renderReturnChart() {
            if (this.returnChartInstance) {
                this.returnChartInstance.destroy();
            }
            
            const ctx = this.$refs.returnChart?.getContext('2d');
            if (!ctx) {
                console.warn('Return chart canvas not found');
                return;
            }
            
            if (!this.returnData.length) {
                console.log('No return data available');
                return;
            }
            
            const labels = this.returnData.map(item => item.asin || 'UNLABELED');
            const data = this.returnData.map(item => item.count);
            
            // Generate colors for pie/doughnut charts
            const colors = this.returnData.map((_, index) => {
                const hue = (index * 360 / this.returnData.length) + 180; // Offset hue
                return `hsl(${hue}, 70%, 60%)`;
            });
            
            const isHorizontal = this.returnChartType === 'horizontalBar';
            const chartType = isHorizontal ? 'bar' : this.returnChartType;
            
            const config = {
                type: chartType,
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Returned Items',
                        data: data,
                        backgroundColor: ['pie', 'doughnut', 'polarArea'].includes(chartType) ? colors : '#ef4444',
                        borderColor: ['pie', 'doughnut', 'polarArea'].includes(chartType) ? '#ffffff' : '#ef4444',
                        borderWidth: 1,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onClick: (event, elements) => {
                        if (elements.length > 0) {
                            const index = elements[0].index;
                            const asinItem = this.returnData[index];
                            
                            this.asinSearchQuery = asinItem.asin || 'UNLABELED';
                            this.filterAsinData();
                            
                            const tableElement = document.querySelector('.asin-stats-table');
                            if (tableElement) {
                                tableElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: ['pie', 'doughnut', 'polarArea'].includes(chartType),
                            position: 'right'
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const item = this.returnData[context.dataIndex];
                                    const value = this.formatNumber(context.parsed.x || context.parsed);
                                    return [
                                        `Count: ${value}`,
                                        `Title: ${item.title || 'No title'}`
                                    ];
                                }
                            }
                        }
                    }
                }
            };
            
            if (['bar', 'line'].includes(chartType)) {
                config.options.indexAxis = isHorizontal ? 'y' : 'x';
                config.options.scales = {
                    [isHorizontal ? 'x' : 'y']: {
                        beginAtZero: true
                    }
                };
            }
            
            this.returnChartInstance = new Chart(ctx, config);
        },
        
        filterByModule(moduleName) {
            this.selectedModule = moduleName;
            this.filterAsinData();
            
            // Render the ASIN breakdown chart
            this.$nextTick(() => {
                this.renderAsinBreakdownChart();
            });
        },
        
        clearModuleFilter() {
            this.selectedModule = null;
            this.filterAsinData();
            
            // Destroy the ASIN breakdown chart
            if (this.asinBreakdownChartInstance) {
                this.asinBreakdownChartInstance.destroy();
                this.asinBreakdownChartInstance = null;
            }
        },
        
        filterAsinData() {
            let filtered = [...this.asinData];
            
            // Filter by module
            if (this.selectedModule) {
                filtered = filtered.filter(item => 
                    item.modules && item.modules[this.selectedModule]
                );
            }
            
            // Filter by search query
            if (this.asinSearchQuery) {
                const query = this.asinSearchQuery.toLowerCase();
                filtered = filtered.filter(item => 
                    (item.asin && item.asin.toLowerCase().includes(query)) ||
                    (item.title && item.title.toLowerCase().includes(query))
                );
            }
            
            this.filteredAsinData = filtered;
        },
        
        async viewAsinDetails(asinItem) {
            this.selectedAsinDetails = asinItem;
            this.showDetailsModal = true;
            this.loadingDetails = true;
            
            try {
                const params = {
                    asin: asinItem.asin || 'UNLABELED'
                };
                
                const response = await axios.get(
                    `${API_BASE_URL}/api/inventory-statistics/asin-details`,
                    { params }
                );
                
                this.asinDetailItems = response.data.items || [];
                
            } catch (error) {
                console.error('Error fetching ASIN details:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load item details',
                });
            } finally {
                this.loadingDetails = false;
            }
        },
    },
    
    mounted() {
        this.fetchStatistics();
    },
    
    beforeUnmount() {
        // Cleanup charts
        if (this.moduleChartInstance) {
            this.moduleChartInstance.destroy();
        }
        if (this.soldChartInstance) {
            this.soldChartInstance.destroy();
        }
        if (this.returnChartInstance) {
            this.returnChartInstance.destroy();
        }
        if (this.asinBreakdownChartInstance) {
            this.asinBreakdownChartInstance.destroy();
        }
    },
};