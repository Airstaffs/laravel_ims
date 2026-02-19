<template>
    <div class="vue-container shipment-module">
        <!-- Header Section -->
        <div class="d-flex align-items-center justify-content-between flex-wrap mb-4">
            <TitlePage title="Shipments Module"
                subtitle="Track all products currently in shipment. Monitor delivery status, carrier information, and customer orders." />

            <div class="d-flex justify-content-center gap-2 me-4 flex-wrap desktop-view">
                <Button severity="secondary" size="small" outlined @click="refreshData" label="Refresh"
                    icon="pi pi-refresh" />
                <Button severity="info" size="small" outlined @click="openStatsModal" label="View Statistics"
                    icon="pi pi-chart-bar" />
            </div>

            <div class="mobile-view w-100 ms-2">
                <Button label="Actions" fluid size="small" severity="secondary" outlined icon="pi pi-list"
                    @click="toggleMenuButton($event)" aria-haspopup="true" aria-controls="overlay_menu" />
            </div>

            <Menu ref="menu" id="overlay_menu" :model="menuActions" :popup="true" />
        </div>

        <!-- Statistics Cards -->
        <AnimateDiv :delay="100" class="stats-container px-4">
            <div class="stat-card bg-primary-light">
                <div class="stat-icon bg-primary">
                    <i class="pi pi-box text-white"></i>
                </div>
                <div>
                    <p class="mb-0">Total Shipments</p>
                    <h5 class="mb-0">{{ stats.total_shipments || 0 }}</h5>
                </div>
            </div>

            <div class="stat-card bg-danger-light">
                <div class="stat-icon bg-danger">
                    <i class="pi pi-calendar-times text-white"></i>
                </div>
                <div>
                    <p class="mb-0">Shipped Today</p>
                    <h5 class="mb-0">{{ stats.shipped_today || 0 }}</h5>
                </div>
            </div>

            <div class="stat-card bg-info-light">
                <div class="stat-icon bg-info">
                    <i class="pi pi-calendar text-white"></i>
                </div>
                <div>
                    <p class="mb-0">This Week</p>
                    <h5 class="mb-0">{{ stats.shipped_this_week || 0 }}</h5>
                </div>
            </div>

            <div class="stat-card bg-success-light">
                <div class="stat-icon bg-success">
                    <i class="pi pi-calendar-plus text-white"></i>
                </div>
                <div>
                    <p class="mb-0">This Month</p>
                    <h5 class="mb-0">{{ stats.shipped_this_month || 0 }}</h5>
                </div>
            </div>
        </AnimateDiv>

        <!-- Filters Section -->
        <AnimateDiv :delay="200" class="px-4">
            <div class="search-container">
                <fieldset class="d-flex align-items-center gap-3">
                    <label for="storeFilter">Store</label>
                    <Select :options="storeOptions" v-model="selectedStore" optionLabel="label" optionValue="value"
                        size="small" class="select-form" @change="changeStore" placeholder="Select a store" />
                </fieldset>

                <fieldset class="d-flex align-items-center gap-3">
                    <label for="carrierFilter">Carrier</label>
                    <Select :options="carrierOptions" v-model="selectedCarrier" optionLabel="label" optionValue="value"
                        size="small" class="select-form" @change="changeCarrier" placeholder="Select carrier" />
                </fieldset>

                <fieldset class="d-flex align-items-center gap-3">
                    <label for="orderByFilter">Order</label>
                    <Select :options="orderByOptions" v-model="orderByFilter" optionLabel="label" optionValue="value"
                        size="small" class="select-form" @change="changeOrderBy" placeholder="Order by" />
                </fieldset>
            </div>

            <!-- Desktop Table -->
            <XDataTable :value="shipments" :loading="loading" :columns="columns" :pagination="false"
                tableClass="desktop-view" dataKey="product_id" scrollable scrollHeight="600px" :key="'shipment-table'">
                <template #productInfo="{ data }">
                    <div class="d-flex flex-column gap-2">
                        <div class="detail-item-container">
                            <span>Title: </span>
                            <span>{{ data.product_title }}</span>
                        </div>
                        <div class="detail-item-container">
                            <span>ASIN: </span>
                            <span>{{ data.asin || 'N/A' }}</span>
                        </div>
                        <div class="detail-item-container">
                            <span>FNSKU: </span>
                            <span>{{ data.fnsku || 'N/A' }}</span>
                        </div>
                        <div class="detail-item-container">
                            <span>MSKU: </span>
                            <span>{{ data.msku || 'N/A' }}</span>
                        </div>
                        <div class="detail-item-container">
                            <span>Condition: </span>
                            <Badge :value="data.condition || 'N/A'" severity="info" />
                        </div>
                    </div>
                </template>

                <template #locationInfo="{ data }">
                    <div class="d-flex flex-column gap-2">
                        <div class="detail-item-container">
                            <span>Location: </span>
                            <span class="fw-bold text-info">{{ data.warehouse_location || 'N/A' }}</span>
                        </div>
                        <div class="detail-item-container" v-show="data.serial_number">
                            <span>Serial #1: </span>
                            <span>{{ data.serial_number || 'N/A' }}</span>
                        </div>
                        <div class="detail-item-container" v-show="data.serial_numberb">
                            <span>Serial #2: </span>
                            <span>{{ data.serial_numberb || 'N/A' }}</span>
                        </div>
                        <div class="detail-item-container" v-show="data.serial_numberc">
                            <span>Serial #3: </span>
                            <span>{{ data.serial_numberc || 'N/A' }}</span>
                        </div>
                        <div class="detail-item-container" v-show="data.serial_numberd">
                            <span>Serial #4: </span>
                            <span>{{ data.serial_numberd || 'N/A' }}</span>
                        </div>
                        <div class="detail-item-container">
                            <span>RT Counter: </span>
                            <span>{{ data.rt_counter || 'N/A' }}</span>
                        </div>
                        <div class="detail-item-container">
                            <span>Price: </span>
                            <span>${{ parseFloat(data.price || 0).toFixed(2) }}</span>
                        </div>
                        <div class="detail-item-container">
                            <span>Qty: </span>
                            <span>{{ data.quantity || 1 }}</span>
                        </div>
                    </div>
                </template>

                <template #orderInfo="{ data }">
                    <div class="d-flex flex-column gap-2">
                        <div class="detail-item-container">
                            <span>Order ID: </span>
                            <span class="text-primary">{{ data.order_id || 'N/A' }}</span>
                        </div>
                        <div class="detail-item-container">
                            <span>Customer: </span>
                            <span>{{ data.customer_name || 'N/A' }}</span>
                        </div>
                        <div class="detail-item-container">
                            <span>Store: </span>
                            <span>{{ data.store_name || 'N/A' }}</span>
                        </div>
                        <div class="detail-item-container">
                            <span>Order Date: </span>
                            <span>{{ formatDate(data.order_date) }}</span>
                        </div>
                        <div class="detail-item-container">
                            <span>Status: </span>
                            <Badge :value="data.order_status || 'N/A'"
                                :severity="getStatusSeverity(data.order_status)" />
                        </div>
                    </div>
                </template>

                <template #trackingInfo="{ data }">
                    <div class="d-flex flex-column gap-2">
                        <div class="detail-item-container">
                            <span>Tracking #: </span>
                            <span class="fw-bold">{{ data.tracking_number || 'N/A' }}</span>
                        </div>
                        <div class="detail-item-container">
                            <span>Carrier: </span>
                            <Tag :value="data.carrier || 'N/A'"
                                :style="{ backgroundColor: getCarrierColor(data.carrier) }" />
                        </div>
                        <div class="detail-item-container">
                            <span>Status: </span>
                            <span>{{ data.tracking_status || 'N/A' }}</span>
                        </div>
                        <div class="detail-item-container">
                            <span>Ship Date: </span>
                            <span>{{ formatDate(data.ship_date) }}</span>
                        </div>
                        <div class="detail-item-container">
                            <span>Delivery Date: </span>
                            <span>{{ formatDate(data.delivery_date) }}</span>
                        </div>
                        <div class="detail-item-container">
                            <span>Shipment Date: </span>
                            <span>{{ formatDate(data.shipment_date) }}</span>
                        </div>
                    </div>
                </template>

                <template #actions="{ data }">
                    <div class="d-flex flex-column gap-2">
                        <Button label="View Details" icon="pi pi-eye" size="small" severity="info" outlined
                            @click="viewDetails(data)" />
                        <Button label="Track Package" icon="pi pi-map-marker" size="small" severity="success" outlined
                            v-if="data.tracking_number" @click="trackPackage(data)" />
                        <Button label="Manual Deliver" icon="pi pi-check" size="small" severity="warning" outlined
                            v-if="data.order_status === 'Shipped'" :loading="manualDeliverLoading"
                            :disabled="manualDeliverLoading" @click="manualDeliver(data)" />
                    </div>
                </template>
            </XDataTable>
        </AnimateDiv>

        <!-- Mobile Cards View -->
        <div class="mobile-view px-3">
            <div v-if="loading" class="loading-spinner-mobile">
                <i class="pi pi-spin pi-spinner"></i> Loading shipments...
            </div>
            <div v-else-if="shipments.length === 0" class="no-data-mobile">
                No shipments found
            </div>
            <div v-else class="mobile-cards">
                <div v-for="shipment in shipments" :key="shipment.product_id" class="mobile-card">
                    <div class="mobile-card-header">
                        <div class="mobile-id fw-bold">
                            Product ID: {{ shipment.product_id }}
                        </div>
                        <Badge :value="shipment.order_status || 'N/A'"
                            :severity="getStatusSeverity(shipment.order_status)" />
                    </div>

                    <div class="mobile-product-title fw-bold mb-2">
                        {{ shipment.product_title }}
                    </div>

                    <Divider />

                    <div class="mobile-section">
                        <div class="mobile-detail">
                            <span class="mobile-detail-label">Location:</span>
                            <span class="mobile-detail-value text-info fw-bold">
                                {{ shipment.warehouse_location || 'N/A' }}
                            </span>
                        </div>
                        <div class="mobile-detail">
                            <span class="mobile-detail-label">ASIN:</span>
                            <span class="mobile-detail-value">{{ shipment.asin || 'N/A' }}</span>
                        </div>
                        <div class="mobile-detail">
                            <span class="mobile-detail-label">Tracking #:</span>
                            <span class="mobile-detail-value fw-bold">{{ shipment.tracking_number || 'N/A' }}</span>
                        </div>
                        <div class="mobile-detail">
                            <span class="mobile-detail-label">Carrier:</span>
                            <Tag :value="shipment.carrier || 'N/A'"
                                :style="{ backgroundColor: getCarrierColor(shipment.carrier) }" />
                        </div>
                    </div>

                    <Divider />

                    <div class="d-flex gap-2">
                        <Button label="Details" icon="pi pi-eye" size="small" outlined class="flex-1"
                            @click="viewDetails(shipment)" />
                        <Button label="Track" icon="pi pi-map-marker" size="small" severity="success" outlined
                            class="flex-1" v-if="shipment.tracking_number" @click="trackPackage(shipment)" />
                        <Button label="Manual Deliver" icon="pi pi-check" size="small" severity="warning" outlined
                            class="flex-1" v-if="shipment.order_status === 'Shipped'" :loading="manualDeliverLoading"
                            :disabled="manualDeliverLoading" @click="manualDeliver(shipment)" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <Paginator
            :first="first"
            :rows="perPage"
            :total-records="totalRecords"
            :rows-per-page-options="[10, 20, 50]"
            template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords}"
            class="small-paginator"
            @page="onPageChange"
        />

        <!-- Details Modal -->
        <Dialog v-model:visible="showDetailsModal" modal header="Shipment Details"
            :style="{ width: '95%', maxWidth: '800px' }" :pt="{ root: { class: 'mobile-fullscreen-dialog' } }">
            <div v-if="selectedShipment" class="details-modal-content">
                <div class="details-section">
                    <h4><i class="pi pi-box"></i> Product Information</h4>
                    <div class="details-grid">
                        <div class="detail-row">
                            <span class="label">Title:</span>
                            <span class="value">{{ selectedShipment.product_title }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">ASIN:</span>
                            <span class="value">{{ selectedShipment.asin || 'N/A' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">FNSKU:</span>
                            <span class="value">{{ selectedShipment.fnsku || 'N/A' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">MSKU:</span>
                            <span class="value">{{ selectedShipment.msku || 'N/A' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Condition:</span>
                            <Badge :value="selectedShipment.condition || 'N/A'" severity="info" />
                        </div>
                        <div class="detail-row">
                            <span class="label">Location:</span>
                            <span class="value fw-bold text-info">{{ selectedShipment.warehouse_location || 'N/A'
                                }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Serial Number:</span>
                            <span class="value">{{ selectedShipment.serial_number || 'N/A' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">RT Counter:</span>
                            <span class="value">{{ selectedShipment.rt_counter || 'N/A' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Price:</span>
                            <span class="value">${{ parseFloat(selectedShipment.price || 0).toFixed(2) }}</span>
                        </div>
                    </div>
                </div>

                <Divider />

                <div class="details-section" v-if="selectedShipment.order_id">
                    <h4><i class="pi pi-shopping-cart"></i> Order Information</h4>
                    <div class="details-grid">
                        <div class="detail-row">
                            <span class="label">Order ID:</span>
                            <span class="value text-primary">{{ selectedShipment.order_id }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Customer:</span>
                            <span class="value">{{ selectedShipment.customer_name || 'N/A' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Store:</span>
                            <span class="value">{{ selectedShipment.store_name || 'N/A' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Order Date:</span>
                            <span class="value">{{ formatDate(selectedShipment.order_date) }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Status:</span>
                            <Badge :value="selectedShipment.order_status || 'N/A'"
                                :severity="getStatusSeverity(selectedShipment.order_status)" />
                        </div>
                    </div>
                </div>

                <Divider v-if="selectedShipment.tracking_number" />

                <div class="details-section" v-if="selectedShipment.tracking_number">
                    <h4><i class="pi pi-map-marker"></i> Tracking Information</h4>
                    <div class="details-grid">
                        <div class="detail-row">
                            <span class="label">Tracking Number:</span>
                            <span class="value fw-bold">{{ selectedShipment.tracking_number }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Carrier:</span>
                            <Tag :value="selectedShipment.carrier || 'N/A'"
                                :style="{ backgroundColor: getCarrierColor(selectedShipment.carrier) }" />
                        </div>
                        <div class="detail-row">
                            <span class="label">Tracking Status:</span>
                            <span class="value">{{ selectedShipment.tracking_status || 'N/A' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Ship Date:</span>
                            <span class="value">{{ formatDate(selectedShipment.ship_date) }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Expected Delivery:</span>
                            <span class="value">{{ formatDate(selectedShipment.delivery_date) }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Shipment Date:</span>
                            <span class="value">{{ formatDate(selectedShipment.shipment_date) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Close" icon="pi pi-times" @click="closeDetailsModal" severity="secondary" />
                <Button label="Track Package" icon="pi pi-map-marker" @click="trackPackage(selectedShipment)"
                    severity="success" v-if="selectedShipment && selectedShipment.tracking_number" />
            </template>
        </Dialog>

        <!-- Statistics Modal -->
        <Dialog v-model:visible="showStatsModal" modal header="Shipment Statistics"
            :style="{ width: '95%', maxWidth: '900px' }">
            <div class="stats-modal-content">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <h5>By Carrier</h5>
                        <div class="chart-container">
                            <div v-for="carrier in stats.by_carrier" :key="carrier.carrier" class="stat-bar">
                                <div class="stat-bar-label">{{ carrier.carrier || 'Unknown' }}</div>
                                <div class="stat-bar-bg">
                                    <div class="stat-bar-fill"
                                        :style="{ width: getPercentage(carrier.count, stats.total_shipments) + '%' }">
                                    </div>
                                </div>
                                <div class="stat-bar-value">{{ carrier.count }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h5>By Store</h5>
                        <div class="chart-container">
                            <div v-for="store in stats.by_store" :key="store.storename" class="stat-bar">
                                <div class="stat-bar-label">{{ store.storename || 'Unknown' }}</div>
                                <div class="stat-bar-bg">
                                    <div class="stat-bar-fill"
                                        :style="{ width: getPercentage(store.count, stats.total_shipments) + '%' }">
                                    </div>
                                </div>
                                <div class="stat-bar-value">{{ store.count }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Dialog>

        <ScrollTop />
    </div>
</template>

<script>
import { Badge, Button, Dialog, Divider, Menu, ScrollTop, Select, Tag, Paginator } from "primevue";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";
import shipmentModule from "./shipment.js";
import { ROWS_PER_PAGE } from "../../constant.js";

const TABLE_COLUMNS = [
    {
        header: "Product Info",
        slot: "productInfo",
        bodyStyle: "font-size: 14px",
        style: { minWidth: "20rem" }
    },
    {
        header: "Location Info",
        slot: "locationInfo",
        bodyStyle: "font-size: 14px",
        style: { minWidth: "15rem" }
    },
    {
        header: "Order Info",
        slot: "orderInfo",
        bodyStyle: "font-size: 14px",
        style: { minWidth: "18rem" }
    },
    {
        header: "Tracking Info",
        slot: "trackingInfo",
        bodyStyle: "font-size: 14px",
        style: { minWidth: "18rem" }
    },

];

export default {
    mixins: [shipmentModule],
    components: {
        XDataTable,
        Button,
        Select,
        Badge,
        Tag,
        Dialog,
        Divider,
        ScrollTop,
        Menu,
        TitlePage,
        AnimateDiv,
        Paginator
    },
    data() {
        return {
            columns: TABLE_COLUMNS,
            menuActions: [],
            rowsPerPageOptions: ROWS_PER_PAGE,
            storeOptions: [{ label: "All Stores", value: '' }],
            carrierOptions: [{ label: "All Carriers", value: '' }],
            orderByOptions: [
                { value: "desc", label: "Newest First" },
                { value: "asc", label: "Oldest First" }
            ]
        }
    },
    methods: {
        toggleMenuButton(event) {
            this.menuActions = this.getMenuActions();
            if (this.$refs.menu) {
                this.$refs.menu.toggle(event);
            }
        },
        getMenuActions() {
            return [
                {
                    label: "Refresh",
                    icon: "pi pi-refresh",
                    command: () => this.refreshData()
                },
                {
                    label: "View Statistics",
                    icon: "pi pi-chart-bar",
                    command: () => this.openStatsModal()
                }
            ]
        },
        getStatusSeverity(status) {
            switch (status) {
                case 'Shipped': return 'success';
                case 'Pending': return 'warning';
                case 'Canceled': return 'danger';
                default: return 'info';
            }
        },
        getCarrierColor(carrier) {
            if (!carrier) return '#e2e8f0'; // Light gray for unknown
            const c = carrier.toUpperCase();
            if (c.includes('UPS')) return '#8B4513'; // Brown (UPS brand color - lighter)
            if (c.includes('FEDEX')) return '#7c3aed'; // Purple (FedEx brand - lighter)
            if (c.includes('USPS')) return '#3b82f6'; // Blue (USPS brand - lighter)
            if (c.includes('DHL')) return '#eab308'; // Yellow (DHL brand)
            if (c.includes('AMAZON')) return '#ff9900'; // Orange (Amazon brand)
            return '#64748b'; // Default gray
        }
    }
};
</script>

<style scoped>
@import './shipment.css';
</style>