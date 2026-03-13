<template>
    <Dialog v-model:visible="visibleModel" modal header="Amazon Returns" :style="{ width: '92vw', maxWidth: '1400px' }"
        :breakpoints="{ '960px': '96vw', '640px': '98vw' }">
        <div class="amazon-returns-wrapper">

            <!-- FILTER BAR -->
            <div class="filters-bar">
                <SelectButton v-model="filters.type" :options="typeOptions" optionLabel="label" optionValue="value"
                    class="type-toggle" />

                <InputText v-model="filters.amazonOrderId" placeholder="OID, TrackID, RMAID" />

                <Dropdown v-model="filters.store_name" :options="storeOptions" optionLabel="label" optionValue="value"
                    placeholder="Store" />

                <Dropdown v-model="filters.sort_order" :options="sortOptions" optionLabel="label" optionValue="value"
                    placeholder="Sort" />

                <Calendar v-model="filters.date_from" dateFormat="yy-mm-dd" placeholder="Date From" showIcon />

                <Calendar v-model="filters.date_to" dateFormat="yy-mm-dd" placeholder="Date To" showIcon />

                <Button icon="pi pi-search" label="Search" @click="fetchReturns" />
            </div>

            <!-- RETURNS LIST -->
            <div class="returns-list">
                <div v-for="item in returns" :key="item.id" class="return-card">
                    <div class="return-card-top">
                        <div>
                            <div class="order-id">Order ID: {{ item.amazonOrderId }}</div>
                            <div v-if="item.type === 'FBM'" class="rma-id">RMA-ID: {{ item.amazon_rma_id }}</div>
                            <div class="meta-line">
                                <span>{{ item.store_name }}</span>
                                <span>•</span>
                                <span>{{ item.type }}</span>
                                <span>•</span>
                                <span>{{ item.return_date }}</span>
                            </div>
                        </div>

                        <Tag :value="item.status || 'N/A'" :severity="getStatusSeverity(item.status)"
                            class="status-tag" />
                    </div>

                    <div class="return-card-body">
                        <div class="info-block product-block">
                            <div class="block-label">Product</div>
                            <div class="product-name">{{ item.product_name || '-' }}</div>
                            <div class="sub-line"><strong>ASIN:</strong> {{ item.asin || '-' }}</div>
                            <div class="sub-line"><strong>SKU:</strong> {{ item.sku || '-' }}</div>
                            <div class="sub-line" v-if="item.fnsku"><strong>FNSKU:</strong> {{ item.fnsku }}</div>
                        </div>

                        <div class="info-block">
                            <div class="block-label">Return</div>
                            <div class="sub-line"><strong>Reason:</strong> {{ item.return_reason || '-' }}</div>
                            <div class="sub-line" v-if="item.quantity"><strong>Qty:</strong> {{ item.quantity }}</div>
                            <div class="sub-line" v-if="item.customer_comments">
                                <strong>Comment:</strong> {{ item.customer_comments }}
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="!returns.length" class="empty-state">
                    No returns found.
                </div>
            </div>

        </div>
    </Dialog>
</template>

<script>
import axios from "axios";

import Dialog from "primevue/dialog";
import SelectButton from "primevue/selectbutton";
import InputText from "primevue/inputtext";
import Dropdown from "primevue/dropdown";
import Calendar from "primevue/calendar";
import Button from "primevue/button";
import Tag from "primevue/tag";

export default {
    components: {
        Dialog,
        SelectButton,
        InputText,
        Dropdown,
        Calendar,
        Button,
        Tag
    },

    props: {
        visible: Boolean
    },

    emits: ["update:visible"],

    computed: {
        visibleModel: {
            get() {
                return this.visible;
            },
            set(val) {
                this.$emit("update:visible", val);
            }
        }
    },

    data() {
        return {
            returns: [],

            filters: {
                type: "FBM",
                amazonOrderId: "",
                store_name: null,
                sort_order: "DESC",
                date_from: null,
                date_to: null
            },

            typeOptions: [
                { label: "FBM", value: "FBM" },
                { label: "FBA", value: "FBA" }
            ],

            storeOptions: [
                { label: "All Stores", value: null },
                { label: "Allrenewed", value: "Allrenewed" },
                { label: "Renovartech", value: "Renovartech" }
            ],

            sortOptions: [
                { label: "Newest First", value: "DESC" },
                { label: "Oldest First", value: "ASC" }
            ]
        };
    },

    methods: {
        async fetchReturns() {
            try {
                const response = await axios.get("api/returns/amazon-returns/list", {
                    params: this.filters
                });

                this.returns = response.data.data;
            } catch (error) {
                console.error("Error fetching returns:", error);
            }
        },
        getStatusSeverity(status) {
            const value = String(status || "").toLowerCase();

            if (value.includes("approved")) return "success";
            if (value.includes("pending")) return "warning";
            if (value.includes("rejected")) return "danger";
            return "info";
        }
    },
    mounted() {
        this.fetchReturns();
    }
};
</script>
<style scoped>
.amazon-returns-wrapper {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 0.25rem 0;
}

.filters-bar {
    display: grid;
    grid-template-columns: 220px 170px 170px 190px 190px auto;
    gap: 0.75rem;
    align-items: center;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e5e7eb;
}

.returns-list {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
    max-height: 68vh;
    overflow-y: auto;
    padding-right: 0.25rem;
}

.return-card {
    background: #fff;
    border: 1px solid #dfe3e8;
    border-radius: 10px;
    padding: 1rem 1rem 0.9rem;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}

.return-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #eef1f4;
}

.order-id {
    font-size: 0.95rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 0.2rem;
}

.rma-id {
    font-size: 0.95rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 0.2rem;
}

.meta-line {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
    font-size: 0.82rem;
    color: #6b7280;
}

.return-card-body {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 1.25rem;
    padding-top: 0.9rem;
}

.info-block {
    display: flex;
    flex-direction: column;
    gap: 0.28rem;
}

.block-label {
    font-size: 0.74rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #6b7280;
    margin-bottom: 0.25rem;
}

.product-name {
    font-size: 0.92rem;
    font-weight: 600;
    color: #1f2937;
    line-height: 1.4;
    margin-bottom: 0.15rem;
}

.sub-line {
    font-size: 0.84rem;
    color: #374151;
    line-height: 1.45;
}

.status-tag {
    font-size: 0.72rem;
}

.empty-state {
    text-align: center;
    padding: 2rem 1rem;
    color: #6b7280;
    border: 1px dashed #d1d5db;
    border-radius: 10px;
    background: #fafafa;
}

@media (max-width: 1200px) {
    .filters-bar {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .filters-bar {
        grid-template-columns: 1fr;
    }

    .return-card-body {
        grid-template-columns: 1fr;
    }

    .return-card-top {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>