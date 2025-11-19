<template>
    <div class="mobile-cards">

        <div v-if="loading" class="loading-spinner-mobile">
            <i class="fas fa-spinner fa-spin"></i> Loading...
        </div>


        <div v-else-if="sortedInventory.length === 0" class="no-data-mobile">
            No data found
        </div>


        <div v-else v-for="(item, index) in sortedInventory" :key="item.id" class="mobile-card">
            <div class="mobile-card-header">
                <div class="mobile-checkbox">
                    <input type="checkbox" v-model="item.checked" />
                </div>

                <TableGallery :data="item" :openImageModal="openImageModal" :handleImageError="handleImageError"
                    :countAdditionalImages="countAdditionalImages" />

                <div class="mobile-product-info">
                    <h5 class="mobile-product-name clickable">
                        <p style="font-size: 1rem;">RT#: {{ item.rtcounter }}</p>
                        <p>{{ item.ProductTitle }}</p>
                    </h5>
                </div>
            </div>

            <Divider />

            <div class="mobile-card-details">
                <div class="row gx-4 gy-2">
                    <!-- Left Column -->
                    <div class="col-6">
                        <div class="mobile-detail-row" v-for="col in firstCol(item)" :key="col.label">
                            <span class="mobile-detail-label">{{ col.label }}:</span>
                            <span class="mobile-detail-value">{{ col.value }}</span>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="col-6">
                        <div class="mobile-detail-row" v-for="col in secondCol(item)" :key="col.label">
                            <span class="mobile-detail-label">{{ col.label }}:</span>
                            <span class="mobile-detail-value">{{ col.value }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional rows -->
            <div class="mobile-detail-row">
                <span class="mobile-detail-label">FNSKU:</span>
                <span class="mobile-detail-value">{{ item.FNSKUviewer }}</span>
            </div>

            <div class="mobile-detail-row">
                <span class="mobile-detail-label">Serial Number:</span>
                <span class="mobile-detail-value">{{ item.serialNumber }}</span>
            </div>

            <Divider />

            <div class="mobile-card-actions">
                <Button size="small" severity="info" @click="openEditModal(item)" icon="pi pi-exclamation-circle"
                    label="View Details" />
            </div>

            <Divider v-if="expandedRows[index]" />

            <div v-if="expandedRows[index]" class="mobile-expanded-content">
                <p><strong>Expanded Rows Here</strong></p>
                <p><strong>Product Name:</strong> {{ item.AStitle }}</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { defineProps } from 'vue'
import TableGallery from '../Gallery/tableGallery.vue'
import { Button, Divider } from 'primevue'


const props = defineProps({
    sortedInventory: { type: Array, required: true },
    expandedRows: { type: Object, required: true },
    loading: { type: Boolean, required: true },
    showDetails: { type: Boolean, required: true },
    openImageModal: Function,
    handleImageError: Function,
    countAdditionalImages: Function,
    openEditModal: Function
})


const firstCol = (item) => {
    return [
        { label: 'Added date', value: item.datedelivered },
        { label: 'Updated date', value: item.lastDateUpdate },
        { label: 'ASIN', value: item.ASINviewer },
        { label: 'FBM', value: item.FBMAvailable, visible: props.showDetails },
        { label: 'FBA', value: item.FbaAvailable, visible: props.showDetails },
        { label: 'MSKU', value: item.MSKUviewer },
        { label: 'Status', value: item.Status }
    ].filter((c) => c.visible === undefined || c.visible)
}

const secondCol = (item) => {
    return [
        { label: 'Outbound', value: item.Outbound, visible: props.showDetails },
        { label: 'Inbound', value: item.Inbound, visible: props.showDetails },
        { label: 'Unfulfillable', value: item.Unfulfillable, visible: props.showDetails },
        { label: 'Reserved', value: item.Reserved, visible: props.showDetails },
        { label: 'Fulfillment', value: item.Fulfilledby }
    ].filter((c) => c.visible === undefined || c.visible)
}
</script>

<style scoped>
.mobile-card {
    border: 1px solid #ddd;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    background: #fff;
}

.mobile-detail-row {
    display: flex;
    align-items: center;
    padding: 0.25rem 0;
}

.mobile-detail-label {
    font-weight: 600;
}

.mobile-detail-value {
    color: #777676;
}
</style>
