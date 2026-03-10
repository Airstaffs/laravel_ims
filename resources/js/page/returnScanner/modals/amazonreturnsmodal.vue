<template>
    <Dialog
        v-model:visible="visible"
        modal
        header="Amazon Returns"
        :style="{ width: '95vw', height: '90vh' }"
    >
        <div class="amazon-returns-wrapper">

            <!-- FILTER BAR -->
            <div class="filters-bar">

                <SelectButton
                    v-model="filters.type"
                    :options="typeOptions"
                    optionLabel="label"
                    optionValue="value"
                />

                <InputText
                    v-model="filters.amazonOrderId"
                    placeholder="Amazon Order ID"
                />

                <Dropdown
                    v-model="filters.store_name"
                    :options="storeOptions"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Store"
                />

                <Dropdown
                    v-model="filters.sort_order"
                    :options="sortOptions"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Sort"
                />

                <Calendar
                    v-model="filters.date_from"
                    dateFormat="yy-mm-dd"
                    placeholder="Date From"
                    showIcon
                />

                <Calendar
                    v-model="filters.date_to"
                    dateFormat="yy-mm-dd"
                    placeholder="Date To"
                    showIcon
                />

                <Button
                    icon="pi pi-search"
                    label="Search"
                    @click="fetchReturns"
                />

            </div>

            <!-- RETURNS LIST -->
            <div class="returns-list">

                <div
                    v-for="item in returns"
                    :key="item.id"
                    class="return-card"
                >

                    <div class="return-grid">

                        <!-- ORDER -->
                        <div>
                            <div class="section-title">Order</div>

                            <div>
                                <strong>Order ID:</strong>
                                {{ item.amazonOrderId }}
                            </div>

                            <div>
                                <strong>Store:</strong>
                                {{ item.store_name }}
                            </div>

                            <div>
                                <strong>Type:</strong>
                                {{ item.type }}
                            </div>

                            <div>
                                <strong>Date:</strong>
                                {{ item.return_date }}
                            </div>
                        </div>

                        <!-- PRODUCT -->
                        <div>
                            <div class="section-title">Product</div>

                            <div class="product-name">
                                {{ item.product_name }}
                            </div>

                            <div>
                                <strong>ASIN:</strong>
                                {{ item.asin || '-' }}
                            </div>

                            <div>
                                <strong>SKU:</strong>
                                {{ item.sku || '-' }}
                            </div>

                            <div v-if="item.fnsku">
                                <strong>FNSKU:</strong>
                                {{ item.fnsku }}
                            </div>
                        </div>

                        <!-- RETURN -->
                        <div>
                            <div class="section-title">Return</div>

                            <div>
                                <strong>Reason:</strong>
                                {{ item.return_reason || '-' }}
                            </div>

                            <div v-if="item.quantity">
                                <strong>Qty:</strong>
                                {{ item.quantity }}
                            </div>

                            <div v-if="item.customer_comments">
                                <strong>Comment:</strong>
                                {{ item.customer_comments }}
                            </div>
                        </div>

                        <!-- STATUS -->
                        <div>
                            <div class="section-title">Status</div>

                            <Tag
                                :value="item.status || 'N/A'"
                                severity="info"
                            />
                        </div>

                    </div>

                </div>

            </div>

        </div>
    </Dialog>
</template>

<script>
import axios from "axios";

export default {

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

                const response = await axios.get("/amazon-returns/list", {
                    params: this.filters
                });

                this.returns = response.data.data;

            } catch (error) {
                console.error("Error fetching returns:", error);
            }

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
}

.filters-bar {
    display: grid;
    grid-template-columns: repeat(6, 1fr) auto;
    gap: .75rem;
}

.returns-list {
    overflow-y: auto;
    max-height: 70vh;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.return-card {
    border: 1px solid #dcdfe3;
    border-radius: 8px;
    padding: 1rem;
    background: white;
}

.return-grid {
    display: grid;
    grid-template-columns: 1.2fr 1.5fr 1.2fr 0.8fr;
    gap: 1rem;
}

.section-title {
    font-size: .8rem;
    font-weight: 700;
    color: #6b7280;
    margin-bottom: .4rem;
    text-transform: uppercase;
}

.product-name {
    font-weight: 600;
}

</style>