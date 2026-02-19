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
                '#f97316', '#6366f1', '#14b8a6', '#a855f7',
            ],

            // Chart Types
            moduleChartType: 'bar',
            soldChartType: 'bar',
            returnChartType: 'bar',
            asinBreakdownChartType: 'bar',
            chartTypeOptions: [
                { label: 'Bar Chart',       value: 'bar' },
                { label: 'Horizontal Bar',  value: 'horizontalBar' },
                { label: 'Pie Chart',       value: 'pie' },
                { label: 'Doughnut Chart',  value: 'doughnut' },
                { label: 'Line Chart',      value: 'line' },
                { label: 'Polar Area',      value: 'polarArea' },
            ],

            // Chart instances
            moduleChartInstance:       null,
            soldChartInstance:         null,
            returnChartInstance:       null,
            asinBreakdownChartInstance: null,

            // Data
            soldData:   [],
            returnData: [],
            asinData:   [],
            filteredAsinData: [],

            // Filters
            selectedModule:   null,
            asinSearchQuery:  '',

            // Modal
            showDetailsModal:    false,
            selectedAsinDetails: null,
            asinDetailItems:     [],

            // Filter Options
            moduleFilterOptions: [
                { label: 'All Modules',    value: null },
                { label: 'Orders',         value: 'Orders' },
                { label: 'Received',       value: 'Received' },
                { label: 'Labeling',       value: 'Labeling' },
                { label: 'Testing',        value: 'Testing' },
                { label: 'Cleaning',       value: 'Cleaning' },
                { label: 'Packing',        value: 'Packing' },
                { label: 'Validation',     value: 'Validation' },
                { label: 'Production Area',value: 'Production Area' },
                { label: 'Stockroom',      value: 'Stockroom' },
                { label: 'Shipment',       value: 'Shipment' },
                { label: 'Returnlist',     value: 'Returnlist' },
                { label: 'Soldlist',       value: 'Soldlist' },
            ],
        };
    },

    computed: {
        filteredModuleAsins() {
            if (!this.selectedModule) return [];
            return [...this.asinData]
                .filter(item => item.modules?.[this.selectedModule])
                .sort((a, b) =>
                    (b.modules[this.selectedModule] || 0) - (a.modules[this.selectedModule] || 0)
                );
        },
    },

    methods: {
        // ─── Helpers ────────────────────────────────────────────────────────

        formatNumber(num) {
            if (!num && num !== 0) return '0';
            return Number(num).toLocaleString();
        },

        getModuleSeverity(module) {
            const map = {
                'Orders': 'info', 'Received': 'secondary', 'Labeling': 'warning',
                'Testing': 'info', 'Cleaning': 'info', 'Packing': 'primary',
                'Validation': 'success', 'Production Area': 'warning',
                'Stockroom': 'success', 'Shipment': 'info',
                'Returnlist': 'danger', 'Soldlist': 'success',
            };
            return map[module] || 'secondary';
        },

        // ─── Fetch ───────────────────────────────────────────────────────────

        async fetchStatistics() {
            this.loading = true;
            try {
                const { data } = await axios.get(`${API_BASE_URL}/api/inventory-statistics/summary`);

                // Summary cards
                const s = data.summary || {};
                this.totalItems     = s.total_items     || 0;
                this.uniqueAsins    = s.unique_asins    || 0;
                this.unlabeledItems = s.unlabeled_items || 0;
                this.totalQuantity  = s.total_quantity  || 0;

                // Charts data
                this.moduleData  = Array.isArray(data.module_distribution) ? data.module_distribution : [];
                this.soldData    = Array.isArray(data.sold_items)           ? data.sold_items           : [];
                this.returnData  = Array.isArray(data.return_items)         ? data.return_items         : [];

                // Table data
                this.asinData         = Array.isArray(data.asin_details) ? data.asin_details : [];
                this.filteredAsinData = [...this.asinData];

                this.$nextTick(() => {
                    this.renderModuleChart();
                    this.renderSoldChart();
                    this.renderReturnChart();
                });

            } catch (err) {
                console.error('fetchStatistics error:', err);
                Swal.fire({
                    icon:   'error',
                    title:  'Error',
                    text:   err.response?.data?.message || err.message || 'Failed to load statistics',
                    footer: 'Check browser console for details',
                });
            } finally {
                this.loading = false;
            }
        },

        // ─── Chart Preferences ───────────────────────────────────────────────

        async loadChartPreferences() {
            try {
                const saved = localStorage.getItem('inventory_chart_prefs');
                if (saved) {
                    const prefs = JSON.parse(saved);
                    this.moduleChartType        = prefs.moduleChartType        || 'bar';
                    this.soldChartType          = prefs.soldChartType          || 'bar';
                    this.returnChartType        = prefs.returnChartType        || 'bar';
                    this.asinBreakdownChartType = prefs.asinBreakdownChartType || 'bar';
                }
            } catch (e) {
                // No saved prefs yet, use defaults
            }
        },

        saveChartPreferences() {
            try {
                localStorage.setItem('inventory_chart_prefs', JSON.stringify({
                    moduleChartType:        this.moduleChartType,
                    soldChartType:          this.soldChartType,
                    returnChartType:        this.returnChartType,
                    asinBreakdownChartType: this.asinBreakdownChartType,
                }));
            } catch (e) {
                console.warn('Could not save chart preferences', e);
            }
        },

        /**
         * Generic chart config factory
         */
        _buildChartConfig({ type, labels, datasets, onClickIndex, tooltipCallbacks, extraPlugins = {} }) {
            const isHorizontal = type === 'horizontalBar';
            const chartType    = isHorizontal ? 'bar' : type;
            const isPolar      = ['pie', 'doughnut', 'polarArea'].includes(chartType);

            const config = {
                type: chartType,
                data: { labels, datasets },
                options: {
                    animation: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    onClick: (event, elements) => {
                        if (elements.length > 0) onClickIndex?.(elements[0].index);
                    },
                    plugins: {
                        legend: { display: isPolar, position: 'right', ...extraPlugins.legend },
                        tooltip: { callbacks: tooltipCallbacks },
                    },
                },
            };

            if (['bar', 'line'].includes(chartType)) {
                config.options.indexAxis = isHorizontal ? 'y' : 'x';
                config.options.scales = {
                    [isHorizontal ? 'x' : 'y']: {
                        beginAtZero: true,
                        ticks: { callback: v => this.formatNumber(v) },
                    },
                };
            }

            return config;
        },

        _destroyChart(refName) {
            const inst = this[`${refName}Instance`];
            if (inst) {
                inst.destroy();
                this[`${refName}Instance`] = null;
            }
        },

        renderModuleChart() {
            this._destroyChart('moduleChart');
            const ctx = this.$refs.moduleChart?.getContext('2d');
            if (!ctx || !this.moduleData.length) return;
            this.saveChartPreferences();

            const labels = this.moduleData.map(m => m.name);
            const data   = this.moduleData.map(m => m.count);

            const config = this._buildChartConfig({
                type: this.moduleChartType,
                labels,
                datasets: [{
                    label: 'Items',
                    data,
                    backgroundColor: this.moduleColors,
                    borderWidth: 1,
                }],
                onClickIndex: (i) => {
                    this.filterByModule(this.moduleData[i].name);
                    this.$nextTick(() => {
                        document.querySelector('.asin-stats-table')
                            ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                },
                tooltipCallbacks: {
                    label: ctx => `${ctx.label || ''}: ${this.formatNumber(ctx.parsed.y ?? ctx.parsed)} items`,
                },
            });

            this.moduleChartInstance = new Chart(ctx, config);
        },

        renderAsinBreakdownChart() {
            this._destroyChart('asinBreakdownChart');
            const ctx = this.$refs.asinBreakdownChart?.getContext('2d');
            if (!ctx || !this.filteredModuleAsins.length) return;
            this.saveChartPreferences();

            const top    = this.filteredModuleAsins.slice(0, 20);
            const labels = top.map(item => item.asin || 'UNLABELED');
            const counts = top.map(item => item.modules[this.selectedModule] || 0);
            const colors = top.map((_, i) => `hsl(${i * (360 / top.length)}, 70%, 60%)`);

            const config = this._buildChartConfig({
                type: this.asinBreakdownChartType,
                labels,
                datasets: [{
                    label: 'Item Count',
                    data: counts,
                    backgroundColor: colors,
                    borderWidth: 1,
                }],
                onClickIndex: (i) => {
                    this.asinSearchQuery = labels[i];
                    this.filterAsinData();
                    document.querySelector('.asin-stats-table')
                        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                },
                tooltipCallbacks: {
                    label: ctx => {
                        const item = top[ctx.dataIndex];
                        return [
                            `ASIN: ${ctx.label}`,
                            `Items in ${this.selectedModule}: ${this.formatNumber(ctx.parsed.y ?? ctx.parsed)}`,
                            `Total Quantity: ${this.formatNumber(item.total_quantity || 0)}`,
                            `Title: ${item.title || 'No title'}`,
                        ];
                    },
                },
                extraPlugins: {
                    legend: {
                        generateLabels: (chart) => chart.data.labels.map((label, i) => ({
                            text: `${label} (${counts[i]})`.substring(0, 60),
                            fillStyle: colors[i],
                            hidden: false,
                            index: i,
                        })),
                    },
                },
            });

            this.asinBreakdownChartInstance = new Chart(ctx, config);
        },

        renderSoldChart() {
            this._destroyChart('soldChart');
            const ctx = this.$refs.soldChart?.getContext('2d');
            if (!ctx || !this.soldData.length) return;

            const labels = this.soldData.map(item => item.asin || 'UNLABELED');
            const data   = this.soldData.map(item => item.count);
            const colors = this.soldData.map((_, i) => `hsl(${i * (360 / this.soldData.length)}, 70%, 60%)`);
            const isPolar = ['pie', 'doughnut', 'polarArea'].includes(
                this.soldChartType === 'horizontalBar' ? 'bar' : this.soldChartType
            );

            const config = this._buildChartConfig({
                type: this.soldChartType,
                labels,
                datasets: [{
                    label: 'Sold Items',
                    data,
                    backgroundColor: isPolar ? colors : '#10b981',
                    borderColor:     isPolar ? '#ffffff' : '#10b981',
                    borderWidth: 1,
                }],
                onClickIndex: (i) => {
                    this.asinSearchQuery = this.soldData[i].asin || 'UNLABELED';
                    this.filterAsinData();
                    document.querySelector('.asin-stats-table')
                        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                },
                tooltipCallbacks: {
                    label: ctx => {
                        const item = this.soldData[ctx.dataIndex];
                        return [
                            `Count: ${this.formatNumber(ctx.parsed.x ?? ctx.parsed)}`,
                            `Title: ${item.title || 'No title'}`,
                        ];
                    },
                },
            });

            this.soldChartInstance = new Chart(ctx, config);
        },

        renderReturnChart() {
            this._destroyChart('returnChart');
            const ctx = this.$refs.returnChart?.getContext('2d');
            if (!ctx || !this.returnData.length) return;

            const labels = this.returnData.map(item => item.asin || 'UNLABELED');
            const data   = this.returnData.map(item => item.count);
            const colors = this.returnData.map((_, i) =>
                `hsl(${(i * (360 / this.returnData.length)) + 180}, 70%, 60%)`
            );
            const isPolar = ['pie', 'doughnut', 'polarArea'].includes(
                this.returnChartType === 'horizontalBar' ? 'bar' : this.returnChartType
            );

            const config = this._buildChartConfig({
                type: this.returnChartType,
                labels,
                datasets: [{
                    label: 'Returned Items',
                    data,
                    backgroundColor: isPolar ? colors : '#ef4444',
                    borderColor:     isPolar ? '#ffffff' : '#ef4444',
                    borderWidth: 1,
                }],
                onClickIndex: (i) => {
                    this.asinSearchQuery = this.returnData[i].asin || 'UNLABELED';
                    this.filterAsinData();
                    document.querySelector('.asin-stats-table')
                        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                },
                tooltipCallbacks: {
                    label: ctx => {
                        const item = this.returnData[ctx.dataIndex];
                        return [
                            `Count: ${this.formatNumber(ctx.parsed.x ?? ctx.parsed)}`,
                            `Title: ${item.title || 'No title'}`,
                        ];
                    },
                },
            });

            this.returnChartInstance = new Chart(ctx, config);
        },

        // ─── Filter / Module ─────────────────────────────────────────────────

        filterByModule(moduleName) {
            this._destroyChart('asinBreakdownChart');
            this.selectedModule = moduleName;
            this.filterAsinData();
            this.$nextTick(() => {
                this.renderAsinBreakdownChart();
            });
        },

        clearModuleFilter() {
            this.selectedModule = null;
            this.filterAsinData();
            this._destroyChart('asinBreakdownChart');
        },

        filterAsinData() {
            let filtered = [...this.asinData];

            if (this.selectedModule) {
                filtered = filtered.filter(item => item.modules?.[this.selectedModule]);
            }

            if (this.asinSearchQuery) {
                const q = this.asinSearchQuery.toLowerCase();
                filtered = filtered.filter(item =>
                    item.asin?.toLowerCase().includes(q) ||
                    item.title?.toLowerCase().includes(q)
                );
            }

            this.filteredAsinData = filtered;
        },

        // ─── Modal ───────────────────────────────────────────────────────────

        async viewAsinDetails(asinItem) {
            this.selectedAsinDetails = asinItem;
            this.showDetailsModal    = true;
            this.loadingDetails      = true;
            this.asinDetailItems     = [];

            try {
                const { data } = await axios.get(
                    `${API_BASE_URL}/api/inventory-statistics/asin-details`,
                    { params: { asin: asinItem.asin || 'UNLABELED' } }
                );
                this.asinDetailItems = data.items || [];
            } catch (err) {
                console.error('viewAsinDetails error:', err);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load item details' });
            } finally {
                this.loadingDetails = false;
            }
        },
    },

    mounted() {
        this.loadChartPreferences();
        this.fetchStatistics();
    },

    beforeUnmount() {
        ['moduleChart', 'soldChart', 'returnChart', 'asinBreakdownChart']
            .forEach(ref => this._destroyChart(ref));
    },
};