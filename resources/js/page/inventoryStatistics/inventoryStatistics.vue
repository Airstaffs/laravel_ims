<template>
    <div class="vue-container inventory-statistics-module">
        <TitlePage
            title="Inventory Statistics"
            subtitle="Comprehensive overview of inventory distribution across all modules with ASIN-based analytics"
        />

        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-sm-6">
                <Card class="stats-card total-items">
                    <template #content>
                        <div class="stat-content">
                            <i class="pi pi-box stat-icon"></i>
                            <div>
                                <h3>{{ formatNumber(totalItems) }}</h3>
                                <p>Total Items</p>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
            <div class="col-md-3 col-sm-6">
                <Card class="stats-card unique-asins">
                    <template #content>
                        <div class="stat-content">
                            <i class="pi pi-tag stat-icon"></i>
                            <div>
                                <h3>{{ formatNumber(uniqueAsins) }}</h3>
                                <p>Unique ASINs</p>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
            <div class="col-md-3 col-sm-6">
                <Card class="stats-card unlabeled-items">
                    <template #content>
                        <div class="stat-content">
                            <i class="pi pi-exclamation-triangle stat-icon"></i>
                            <div>
                                <h3>{{ formatNumber(unlabeledItems) }}</h3>
                                <p>Unlabeled Items</p>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
            <div class="col-md-3 col-sm-6">
                <Card class="stats-card total-quantity">
                    <template #content>
                        <div class="stat-content">
                            <i class="pi pi-database stat-icon"></i>
                            <div>
                                <h3>{{ formatNumber(totalQuantity) }}</h3>
                                <p>Total Quantity</p>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
        </div>

        <!-- Module Distribution Chart -->
        <Card class="mb-4">
            <template #title>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5>Inventory Distribution by Module</h5>
                    <div class="d-flex gap-2 align-items-center">
                        <Select
                            v-model="moduleChartType"
                            :options="chartTypeOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Chart Type"
                            size="small"
                            @change="renderModuleChart"
                        />
                        <Button
                            icon="pi pi-refresh"
                            size="small"
                            severity="info"
                            outlined
                            @click="fetchStatistics"
                            :loading="loading"
                        />
                    </div>
                </div>
            </template>
            <template #content>
                <div class="chart-container">
                    <canvas ref="moduleChart"></canvas>
                </div>
                <div class="legend-container mt-3">
                    <div
                        v-for="(module, index) in moduleData"
                        :key="module.name"
                        class="legend-item"
                        @click="filterByModule(module.name)"
                    >
                        <span
                            class="legend-color"
                            :style="{ backgroundColor: moduleColors[index] }"
                        ></span>
                        <span class="legend-label">{{ module.name }}</span>
                        <span class="legend-value">{{ formatNumber(module.count) }}</span>
                    </div>
                </div>
            </template>
        </Card>

        <!-- ASIN Breakdown Chart (shows when module is filtered) -->
        <Card v-if="selectedModule && filteredModuleAsins.length > 0" class="mb-4">
            <template #title>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-0">{{ selectedModule }} - ASIN Breakdown</h5>
                        <small class="text-muted">
                            Showing {{ filteredModuleAsins.length }} ASINs in {{ selectedModule }}
                        </small>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <Button
                            icon="pi pi-times"
                            size="small"
                            severity="secondary"
                            outlined
                            @click="clearModuleFilter"
                            label="Clear Filter"
                        />
                        <Select
                            v-model="asinBreakdownChartType"
                            :options="chartTypeOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Chart Type"
                            size="small"
                            @change="renderAsinBreakdownChart"
                        />
                    </div>
                </div>
            </template>
            <template #content>
                <div class="chart-container">
                    <canvas ref="asinBreakdownChart"></canvas>
                </div>
            </template>
        </Card>

        <!-- Sold and Return Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <Card class="h-100">
                    <template #title>
                        <div class="d-flex justify-content-between align-items-center">
                            <h5>Top 10 Sold Items (by ASIN)</h5>
                            <Select
                                v-model="soldChartType"
                                :options="chartTypeOptions"
                                optionLabel="label"
                                optionValue="value"
                                size="small"
                                @change="renderSoldChart"
                            />
                        </div>
                    </template>
                    <template #content>
                        <div class="chart-container">
                            <canvas ref="soldChart"></canvas>
                        </div>
                    </template>
                </Card>
            </div>
            <div class="col-lg-6">
                <Card class="h-100">
                    <template #title>
                        <div class="d-flex justify-content-between align-items-center">
                            <h5>Top 10 Returned Items (by ASIN)</h5>
                            <Select
                                v-model="returnChartType"
                                :options="chartTypeOptions"
                                optionLabel="label"
                                optionValue="value"
                                size="small"
                                @change="renderReturnChart"
                            />
                        </div>
                    </template>
                    <template #content>
                        <div class="chart-container">
                            <canvas ref="returnChart"></canvas>
                        </div>
                    </template>
                </Card>
            </div>
        </div>

        <!-- ASIN Details Table -->
        <Card>
            <template #title>
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <h5>ASIN Distribution Details</h5>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <Select
                            v-model="selectedModule"
                            :options="moduleFilterOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Filter by Module"
                            size="small"
                            @change="filterAsinData"
                        />
                        <InputText
                            v-model="asinSearchQuery"
                            placeholder="Search ASIN..."
                            size="small"
                            @input="filterAsinData"
                        >
                            <template #prefix>
                                <i class="pi pi-search"></i>
                            </template>
                        </InputText>
                    </div>
                </div>
            </template>
            <template #content>
                <XDataTable
                    :value="filteredAsinData"
                    :loading="loading"
                    :columns="asinColumns"
                    :paginator="true"
                    :rows="10"
                    :showGridlines="false"
                    tableClass="asin-stats-table"
                    dataKey="asin"
                >
                    <template #asin="{ data }">
                        <div class="asin-cell">
                            <span v-if="data.asin" class="badge bg-primary">
                                {{ data.asin }}
                            </span>
                            <span v-else class="badge bg-warning text-dark">
                                UNLABELED
                            </span>
                        </div>
                    </template>

                    <template #title="{ data }">
                        <div class="title-cell" :title="data.title">
                            {{ data.title || '—' }}
                        </div>
                    </template>

                    <template #modules="{ data }">
                        <div class="modules-cell">
                            <Tag
                                v-for="(count, module) in data.modules"
                                :key="module"
                                :value="`${module} (${count})`"
                                :severity="getModuleSeverity(module)"
                                class="me-1 mb-1"
                                style="font-size: 0.75rem"
                            />
                        </div>
                    </template>

                    <template #total_quantity="{ data }">
                        <span class="fw-bold">{{ formatNumber(data.total_quantity) }}</span>
                    </template>

                    <template #actions="{ data }">
                        <Button
                            icon="pi pi-eye"
                            size="small"
                            severity="info"
                            text
                            @click="viewAsinDetails(data)"
                            label="Details"
                        />
                    </template>
                </XDataTable>
            </template>
        </Card>

        <!-- ASIN Details Modal -->
        <Dialog
            v-model:visible="showDetailsModal"
            modal
            :style="{ width: '90%', maxWidth: '1200px' }"
            :pt="{
                root: { class: 'mobile-fullscreen-dialog' },
            }"
        >
            <template #header>
                <div>
                    <h5 class="mb-1">
                        {{ selectedAsinDetails?.asin || 'UNLABELED' }} - Details
                    </h5>
                    <small class="text-muted">{{ selectedAsinDetails?.title }}</small>
                </div>
            </template>

            <div v-if="selectedAsinDetails">
                <!-- Summary Row -->
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <Card>
                            <template #content>
                                <div class="text-center">
                                    <i class="pi pi-box text-primary" style="font-size: 2rem"></i>
                                    <h4 class="mt-2">{{ formatNumber(selectedAsinDetails.total_items) }}</h4>
                                    <p class="text-muted mb-0">Total Items</p>
                                </div>
                            </template>
                        </Card>
                    </div>
                    <div class="col-md-4">
                        <Card>
                            <template #content>
                                <div class="text-center">
                                    <i class="pi pi-database text-success" style="font-size: 2rem"></i>
                                    <h4 class="mt-2">{{ formatNumber(selectedAsinDetails.total_quantity) }}</h4>
                                    <p class="text-muted mb-0">Total Quantity</p>
                                </div>
                            </template>
                        </Card>
                    </div>
                    <div class="col-md-4">
                        <Card>
                            <template #content>
                                <div class="text-center">
                                    <i class="pi pi-map-marker text-info" style="font-size: 2rem"></i>
                                    <h4 class="mt-2">{{ Object.keys(selectedAsinDetails.modules).length }}</h4>
                                    <p class="text-muted mb-0">Locations</p>
                                </div>
                            </template>
                        </Card>
                    </div>
                </div>

                <!-- Items Table -->
                <XDataTable
                    :value="asinDetailItems"
                    :loading="loadingDetails"
                    :columns="detailColumns"
                    :paginator="true"
                    :rows="10"
                    :showGridlines="false"
                    dataKey="ProductID"
                >
                    <template #rtcounter="{ data }">
                        <span class="fw-bold">RT# {{ data.rtcounter }}</span>
                    </template>

                    <template #module="{ data }">
                        <Tag
                            :value="data.ProductModuleLoc"
                            :severity="getModuleSeverity(data.ProductModuleLoc)"
                        />
                    </template>

                    <template #quantity="{ data }">
                        <span>{{ data.quantity || 1 }}</span>
                    </template>

                    <template #serial="{ data }">
                        <span>{{ data.serialnumber || '—' }}</span>
                    </template>

                    <template #location="{ data }">
                        <span>{{ data.warehouselocation || '—' }}</span>
                    </template>
                </XDataTable>
            </div>
        </Dialog>

        <ScrollTop />
    </div>
</template>

<script>
import InventoryStatistics from "./inventoryStatistics.js";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import {
    Badge,
    Button,
    Card,
    Dialog,
    InputText,
    Select,
    ScrollTop,
    Tag,
} from "primevue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";

const ASIN_COLUMNS = [
    {
        field: "asin",
        header: "ASIN",
        slot: "asin",
        sortable: true,
        style: { minWidth: "120px" },
    },
    {
        field: "title",
        header: "Title",
        slot: "title",
        sortable: true,
        style: { minWidth: "250px" },
    },
    {
        field: "total_items",
        header: "Items",
        sortable: true,
        style: { textAlign: "center", minWidth: "80px" },
    },
    {
        field: "total_quantity",
        header: "Quantity",
        slot: "total_quantity",
        sortable: true,
        style: { textAlign: "center", minWidth: "100px" },
    },
    {
        field: "modules",
        header: "Locations",
        slot: "modules",
        style: { minWidth: "300px" },
    },
    {
        field: "actions",
        header: "Actions",
        slot: "actions",
        style: { textAlign: "center", width: "100px" },
    },
];

const DETAIL_COLUMNS = [
    {
        field: "rtcounter",
        header: "RT#",
        slot: "rtcounter",
        sortable: true,
    },
    {
        field: "ProductModuleLoc",
        header: "Module",
        slot: "module",
        sortable: true,
    },
    {
        field: "quantity",
        header: "Qty",
        slot: "quantity",
        sortable: true,
        style: { textAlign: "center" },
    },
    {
        field: "serialnumber",
        header: "Serial Number",
        slot: "serial",
        sortable: true,
    },
    {
        field: "warehouselocation",
        header: "Location",
        slot: "location",
        sortable: true,
    },
];

export default {
    mixins: [InventoryStatistics],
    components: {
        XDataTable,
        Button,
        Badge,
        Card,
        Dialog,
        InputText,
        Select,
        ScrollTop,
        TitlePage,
        Tag,
    },
    data() {
        return {
            asinColumns: ASIN_COLUMNS,
            detailColumns: DETAIL_COLUMNS,
        };
    },
};
</script>

<style scoped>
.inventory-statistics-module {
    padding: 1.5rem;
}

/* Stats Cards */
.stats-card {
    height: 100%;
    transition: transform 0.2s, box-shadow 0.2s;
}

.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.stat-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    font-size: 2.5rem;
    opacity: 0.8;
}

.total-items .stat-icon {
    color: #3b82f6;
}

.unique-asins .stat-icon {
    color: #10b981;
}

.unlabeled-items .stat-icon {
    color: #f59e0b;
}

.total-quantity .stat-icon {
    color: #8b5cf6;
}

.stat-content h3 {
    margin: 0;
    font-size: 1.75rem;
    font-weight: bold;
}

.stat-content p {
    margin: 0;
    color: #6b7280;
    font-size: 0.875rem;
}

/* Chart Container */
.chart-container {
    position: relative;
    height: 350px;
}

/* Legend */
.legend-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 0.75rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.legend-item:hover {
    background-color: #f3f4f6;
}

.legend-color {
    width: 16px;
    height: 16px;
    border-radius: 3px;
    flex-shrink: 0;
}

.legend-label {
    flex: 1;
    font-size: 0.875rem;
}

.legend-value {
    font-weight: 600;
    font-size: 0.875rem;
    color: #374151;
}

/* Table Cells */
.asin-cell .badge {
    font-size: 0.875rem;
    padding: 0.375rem 0.75rem;
}

.title-cell {
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.modules-cell {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
}

/* Responsive */
@media (max-width: 768px) {
    .legend-container {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    }

    .chart-container {
        height: 300px;
    }
}
</style>