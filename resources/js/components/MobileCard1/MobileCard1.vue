<template>
    <div class="mobile-cards">
        <div v-if="loading" class="loading-spinner-mobile">
            <i class="fas fa-spinner fa-spin"></i> Loading...
        </div>

        <div v-else-if="sortedInventory.length === 0" class="no-data-mobile">
            No data found
        </div>

        <div
            v-else
            v-for="(item, index) in sortedInventory"
            :key="item.id"
            class="mobile-card"
        >
            <div class="mobile-card-header">
                <div class="mobile-checkbox">
                    <input type="checkbox" v-model="item.checked" />
                </div>

                <TableGallery
                    :data="item"
                    :openImageModal="openImageModal"
                    :handleImageError="handleImageError"
                    :countAdditionalImages="countAdditionalImages"
                />

                <div class="mobile-product-info">
                    <h5 class="mobile-product-name clickable">
                        <p style="font-size: 1rem">RT#: {{ item.rtcounter }}</p>
                        <p>{{ getDisplayTitle(item) }}</p>
                    </h5>
                </div>
            </div>

            <Divider />

            <div class="mobile-card-details">
                <div class="row gx-4 gy-2">
                    <!-- Left Column -->
                    <div class="col-6">
                        <div
                            class="mobile-detail-row"
                            v-for="col in firstCol(item)"
                            :key="col.label"
                        >
                            <span class="mobile-detail-label"
                                >{{ col.label }}:</span
                            >
                            <span class="mobile-detail-value">{{
                                col.value
                            }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <Divider />

            <div class="mobile-card-actions">
                <Button
                    size="small"
                    severity="info"
                    @click="openEditModal(item)"
                    icon="pi pi-exclamation-circle"
                    label="View Details"
                />
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
import { defineProps } from "vue";
import TableGallery from "../Gallery/tableGallery.vue";
import { Button, Divider } from "primevue";

const props = defineProps({
    sortedInventory: { type: Array, required: true },
    expandedRows: { type: Object, required: true },
    loading: { type: Boolean, required: true },
    showDetails: { type: Boolean, required: true },
    visibleFields: {
        type: Array,
        default: () => [
            "price",
            "serialnumber",
            "trackingnumber",
            "datedelivered",
            "materialtype",
        ],
    },
    openImageModal: Function,
    handleImageError: Function,
    countAdditionalImages: Function,
    openEditModal: Function,
});

const getDisplayTitle = (item) => {
    if (!item) return "—";
    // Priority: system_title > internal > AStitle > ProductTitle
    if (item.system_title && item.system_title.trim() !== "") {
        return item.system_title;
    }
    if (item.internal && item.internal.trim() !== "") {
        return item.internal;
    }
    if (item.AStitle && item.AStitle.trim() !== "") {
        return item.AStitle;
    }
    if (item.ProductTitle && item.ProductTitle.trim() !== "") {
        return item.ProductTitle;
    }
    return "—";
};

const getFnskuDisplayTitle = (fnskuItem) => {
    if (!fnskuItem) return "—";

    // Backend already prioritizes: system_title > internal via COALESCE
    if (fnskuItem.astitle && fnskuItem.astitle.trim() !== "") {
        return fnskuItem.astitle;
    }

    // Fallbacks if astitle is missing
    if (fnskuItem.system_title && fnskuItem.system_title.trim() !== "") {
        return fnskuItem.system_title;
    }

    if (fnskuItem.internal && fnskuItem.internal.trim() !== "") {
        return fnskuItem.internal;
    }

    return "—";
};

const firstCol = (item) => {
    const allFields = [
        { label: "Price", value: item.price, key: "price" },
        {
            label: "Serial Number",
            value: item.serialnumber,
            key: "serialnumber",
        },
        {
            label: "Tracking Number",
            value: item.trackingnumber,
            key: "trackingnumber",
        },
        {
            label: "Delivered Date",
            value: item.datedelivered,
            key: "datedelivered",
        },
        {
            label: "Material",
            value: item.materialtype,
            key: "materialtype",
        },
    ];

    return allFields.filter((field) => props.visibleFields.includes(field.key));
};
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
