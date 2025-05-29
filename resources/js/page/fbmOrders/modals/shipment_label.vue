<template>
    <div v-if="show" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Shipment Label</h3>
                <button @click="$emit('close')" class="close-btn">&times;</button>
            </div>

            <div class="modal-body">
                <div class="shipment-body">
                    <div v-for="order in shipmentData" :key="order.platform_order_id" class="order-wrapper">
                        <div class="order-section">
                            <div class="left-panel">
                                <div class="order-info">
                                    <p><strong>AmazonOrderId:</strong> {{ order.platform_order_id }}</p>
                                    <p><strong>Customer Name:</strong> {{ order.BuyerName }}</p>
                                    <p><strong>Address:</strong><br>
                                        {{ order.address_line1 }},<br>
                                        {{ order.city }}, {{ order.StateOrRegion }}<br>
                                        {{ order.postal_code }}, {{ order.CountryCode }}
                                    </p>

                                    <div v-if="order.items && order.items.length">
                                        <p><strong>Order Items:</strong></p>
                                        <div v-for="(item, index) in order.items" :key="index"
                                            style="margin-bottom: 1rem; padding-left: 1rem; border-left: 2px solid #ddd;">
                                            <div>OrderItemId: <strong>{{ item.platform_order_item_id }}</strong></div>
                                            <div>ASIN: <strong>{{ item.platform_asin }}</strong></div>
                                            <div>SKU: <strong>{{ item.platform_sku }}</strong></div>
                                            <div>Qty: <strong>{{ item.QuantityOrdered }}</strong></div>
                                            <div>Status: <strong>{{ item.order_status }}</strong></div>
                                            <div>Condition: <strong>{{ item.ConditionSubtypeId }} - {{ item.ConditionId
                                            }}</strong></div>
                                            <div>Unit Price: <strong>${{ item.unit_price }}</strong></div>
                                            <div>Unit Tax: <strong>${{ item.unit_tax }}</strong></div>
                                        </div>
                                    </div>

                                    <div v-if="rateResults && rateResults.length">
                                        <!--
                                        <div v-for="rateBlock in rateResults" :key="rateBlock.platform_order_id">
                                            <table v-if="rateBlock.platform_order_id === order.platform_order_id"
                                                class="carrier-table">
                                                <thead>
                                                    <tr>
                                                        <th>Select</th>
                                                        <th>Shipping Service</th>
                                                        <th>Rate</th>
                                                        <th>Ship Date</th>
                                                        <th>Estimated Delivery</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="(rate, idx) in rateBlock.rates?.payload?.ShippingServiceList || []"
                                                        :key="idx">
                                                        <td v-if="selectedCarriers">
                                                            <input type="radio"
                                                                :name="'select-rate-' + order.platform_order_id"
                                                                :value="rate"
                                                                v-model="selectedCarriers[order.platform_order_id]" />
                                                        </td>
                                                        <td>
                                                            <strong>{{ rate.ShippingServiceName }}</strong><br />
                                                            <span><strong>Included:</strong>
                                                                <span v-for="b in rate.Benefits?.IncludedBenefits || []"
                                                                    :key="b">{{ b }}</span>
                                                            </span><br />
                                                            <span><strong>Excluded:</strong>
                                                                <span v-for="b in rate.Benefits?.ExcludedBenefits || []"
                                                                    :key="b.Benefit">{{ b.Benefit }}</span>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span>${{ rate.Rate.Amount }}</span>
                                                        </td>
                                                        <td>{{ formatDate(rate.ShipDate) }}</td>
                                                        <td>
                                                            {{ formatDate(rate.EarliestEstimatedDeliveryDate) }} -
                                                            {{ formatDate(rate.LatestEstimatedDeliveryDate) }}
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                                                                                            -->
                                        <button @click="openCarrierModal(order)" class="carrier-select-btn">Select
                                            Carrier Option</button>
                                    </div>

                                    <p v-else>No rates available. Please click "Get Rates" after filling out the form.
                                    </p>

                                    <div v-if="selectedCarriers.hasOwnProperty(order.platform_order_id) && selectedCarriers[order.platform_order_id]"
                                        class="selected-carrier-summary mt-2 p-2 border rounded bg-light">
                                        <p><strong>Selected Carrier:</strong> {{
                                            selectedCarriers[order.platform_order_id].ShippingServiceName }}</p>
                                        <p><strong>Rate:</strong> ${{
                                            selectedCarriers[order.platform_order_id].Rate.Amount }}</p>
                                        <p><strong>Ship Date:</strong> {{
                                            formatDatetext(selectedCarriers[order.platform_order_id].ShipDate) }}</p>
                                        <p>
                                            <strong>Estimated Delivery:</strong>
                                            {{
                                                formatDatetext(selectedCarriers[order.platform_order_id].EarliestEstimatedDeliveryDate)
                                            }} –
                                            {{
                                                formatDatetext(selectedCarriers[order.platform_order_id].LatestEstimatedDeliveryDate)
                                            }}
                                        </p>
                                    </div>

                                </div>

                            </div>

                            <div class="right-panel">
                                <div v-if="forms && forms[order.platform_order_id]" class="package-dimensions">
                                    <h4>Package Dimensions</h4>

                                    <div class="form-group">
                                        <label>Delivery Experience</label>
                                        <select v-model="forms[order.platform_order_id].deliveryExperience">
                                            <option value="DeliveryConfirmationWithoutSignature">Without Signature
                                            </option>
                                            <option value="DeliveryConfirmationWithSignature">With Signature</option>
                                            <option value="DeliveryConfirmationWithAdultSignature">With Adult Signature
                                            </option>
                                            <option value="NoTracking">No Tracking</option>
                                        </select>
                                    </div>

                                    <div class="form-row">
                                        <label>Length</label>
                                        <input type="number" v-model="forms[order.platform_order_id].length"
                                            placeholder="Required" />
                                    </div>
                                    <div class="form-row">
                                        <label>Width</label>
                                        <input type="number" v-model="forms[order.platform_order_id].width"
                                            placeholder="Required" />
                                    </div>
                                    <div class="form-row">
                                        <label>Height</label>
                                        <input type="number" v-model="forms[order.platform_order_id].height"
                                            placeholder="Required" />
                                    </div>

                                    <div class="form-row">
                                        <label>Unit</label>
                                        <select v-model="forms[order.platform_order_id].dimensionUnit">
                                            <option value="inches">Inches</option>
                                            <option value="centimeters">Centimeters</option>
                                        </select>
                                    </div>

                                    <div class="form-row">
                                        <label>Weight</label>
                                        <input type="number" v-model="forms[order.platform_order_id].weight" />
                                    </div>
                                    <div class="form-row">
                                        <label>Weight Unit</label>
                                        <select v-model="forms[order.platform_order_id].weightUnit">
                                            <option value="pound">Pound</option>
                                            <option value="grams">Grams</option>
                                            <option value="ounces">Ounces</option>
                                        </select>
                                    </div>

                                    <div class="form-row">
                                        <label>Currency Code</label>
                                        <input v-model="forms[order.platform_order_id].currency"
                                            placeholder="Optional" />
                                    </div>

                                    <div class="form-row">
                                        <label>Ship By</label>
                                        <input type="datetime-local" v-model="forms[order.platform_order_id].shipBy" />
                                    </div>

                                    <div class="form-row">
                                        <label>Deliver By</label>
                                        <input type="datetime-local"
                                            v-model="forms[order.platform_order_id].deliverBy" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="button-row">
                        <button @click="getRates">Get Rates</button>
                        <button @click="submitValidShipments">Buy Shipment</button>
                        <button @click="manualShipment">Manual Shipment</button>
                    </div>

                </div>


                <!-- Carrier Modal -->
                <div v-if="showCarrierModal" class="modal-overlay inner-overlay">
                    <div class="modal-container">
                        <div class="modal-header">
                            <h4>Select Carrier for {{ selectedCarrierOrderId }}</h4>
                            <button class="close-btn" @click="showCarrierModal = false">&times;</button>
                        </div>
                        <div class="modal-body">
                            <table class="carrier-table"
                                v-if="selectedCarrierOrder?.rates?.payload?.ShippingServiceList?.length">
                                <thead>
                                    <tr>
                                        <th>Select</th>
                                        <th>Shipping Service</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(rate, idx) in selectedCarrierOrder.rates.payload.ShippingServiceList"
                                        :key="idx">
                                        <td v-if="selectedCarrierOrder">
                                            <input type="radio"
                                                :name="'select-carrier-' + selectedCarrierOrder.platform_order_id"
                                                :value="rate"
                                                v-model="selectedCarriers[selectedCarrierOrder.platform_order_id]"
                                                @change="ensureSelectedCarrierEntry(selectedCarrierOrder.platform_order_id)" />
                                        </td>
                                        <td>
                                            <strong>{{ rate.ShippingServiceName }}</strong><br />
                                            <span><strong>Included:</strong>
                                                <span v-for="b in rate.Benefits?.IncludedBenefits || []" :key="b">{{ b
                                                }}</span>
                                            </span><br />
                                            <span>Ship Date: <strong>{{ formatDatetext(rate.ShipDate)
                                                    }}</strong></span><br>
                                            <span>Rate: <strong>${{ rate.Rate.Amount }}</strong></span><br>
                                            <span>Estimated Delivery:
                                                <strong>{{ formatDatetext(rate.EarliestEstimatedDeliveryDate) }} - {{
                                                    formatDatetext(rate.LatestEstimatedDeliveryDate) }}</strong></span>
                                            <!--
                                            <span><strong>Excluded:</strong>
                                                <span v-for="b in rate.Benefits?.ExcludedBenefits || []"
                                                    :key="b.Benefit">{{ b.Benefit }}</span>
                                            </span>
                                            -->
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <input type="radio"
                                                :name="'select-carrier-' + selectedCarrierOrder.platform_order_id"
                                                :value="null"
                                                v-model="selectedCarriers[selectedCarrierOrder.platform_order_id]" />
                                        </td>
                                        <td colspan="4"><em>No carrier selected (skip this order)</em></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    props: {
        show: Boolean,
        shipmentData: Array
    },
    emits: ['close', 'submit'],
    data() {
        return {
            forms: {},
            rateResults: [],
            selectedRateMap: {},
            selectedCarriers: {},       // ✅ This is required
            selectedCarrierOrderId: null,
            showCarrierModal: false,
            selectedCarrierOrder: null
        };
    },
    computed: {
        hasRates() {
            return this.rateResults.some(r => r.rates?.payload?.ShippingServiceList?.length);
        },
        selectedCarrierOrder() {
            return this.rateResults.find(
                r => r.platform_order_id === this.selectedCarrierOrderId
            );
        }
    },
    watch: {
        shipmentData: {
            handler(newOrders) {
                if (!Array.isArray(newOrders)) return;
                newOrders.forEach(order => {
                    const id = order.platform_order_id;
                    if (!this.forms[id]) {
                        this.forms[id] = {
                            deliveryExperience: 'DeliveryConfirmationWithoutSignature',
                            length: '',
                            width: '',
                            height: '',
                            dimensionUnit: 'inches',
                            weight: '',
                            weightUnit: 'pound',
                            currency: '',
                            shipBy: '',
                            deliverBy: ''
                        };
                    }
                });
            },
            immediate: true,
            deep: true
        }
    },
    methods: {
        formatDatetext(isoDate) {
            if (!isoDate) return '';
            const date = new Date(isoDate);
            return date.toLocaleDateString('en-US', {
                weekday: 'long',    // "Monday"
                year: 'numeric',    // "2024"
                month: 'short',     // "Jan"
                day: 'numeric'      // "7"
            });
        },
        getRates() {
            const payload = {
                orders: this.shipmentData,
                forms: this.forms
            };

            axios.post(`${API_BASE_URL}/amzn/fbm-orders/purchase-label/rates`, payload)
                .then(res => {
                    this.rateResults = res.data.results || [];
                })
                .catch(err => {
                    alert('Failed to get rates');
                    console.error(err);
                });
        },
        selectRate(orderId, rate) {
            this.selectedRateMap[orderId] = rate;
        },
        buyShipment() {
            const payload = {
                selectedRates: this.selectedRateMap,
                orders: this.shipmentData,
                forms: this.forms
            };
            this.$emit('submit', payload);
        },
        manualShipment() {
            alert('Manual shipment not implemented yet.');
        },
        formatDate(dateStr) {
            if (!dateStr) return '';
            return new Date(dateStr).toLocaleDateString();
        },
        openCarrierModal(order) {
            console.log("rateResults:", this.rateResults);
            console.log("Looking for:", order.platform_order_id);
            this.selectedCarrierOrderId = order.platform_order_id;

            // Find the matching rateBlock for this order
            this.selectedCarrierOrder = this.rateResults.find(
                r => r.platform_order_id === order.platform_order_id
            );

            // Guard clause: only proceed if data is valid
            if (!this.selectedCarrierOrder || !this.selectedCarrierOrder.rates) {
                console.warn("No carrier data available for", order.platform_order_id);
                return;
            }

            // Safely initialize the selected carrier entry
            if (!this.selectedCarriers) {
                this.selectedCarriers = {};
            }

            if (!(order.platform_order_id in this.selectedCarriers)) {
                this.selectedCarriers[order.platform_order_id] = null;
            }

            this.showCarrierModal = true;
        },
        ensureSelectedCarrierEntry(orderId) {
            if (!this.selectedCarriers[orderId]) {
                this.$set(this.selectedCarriers, orderId, null);
            }
        }


    }
};
</script>


<style scoped>
/*
.order-divider {
  margin: 24px 0;
  border-top: 1px solid #ccc;
}
  */

.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: flex-start;
    overflow-y: auto;
    padding: 2rem;
    z-index: 1000;
}

.modal-container {
    background: #fff;
    padding: 1.5rem;
    border-radius: 10px;
    max-width: 1100px;
    width: 100%;
    max-height: 95vh;
    overflow-y: auto;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #ddd;
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
}

.close-btn {
    background: transparent;
    font-size: 1.5rem;
    border: none;
    cursor: pointer;
    color: #333;
}

.shipment-body {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.left-panel {
    flex: 1;
    background: #f8f9fa;
    padding: 1rem;
    border: 1px solid #ddd;
    border-radius: 8px;
}

.right-panel {
    flex: 1;
}

.order-section {
    display: flex;
    gap: 2rem;
}

.order-info {
    margin-bottom: 1rem;
    line-height: 1.5;
}

.package-dimensions {
    background: #fff7ef;
    padding: 1rem;
    border: 1px solid #f0d9c2;
    border-radius: 8px;
}

.carrier-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
    font-size: 0.9rem;
    background: #fff;
}

.carrier-table th,
.carrier-table td {
    border: 1px solid #ccc;
    padding: 8px;
    vertical-align: top;
    text-align: left;
}

.carrier-table th {
    background-color: #f0f0f0;
    min-width: 0px;
    max-width: 0px;
}

.form-row,
.form-group {
    margin-bottom: 1rem;
}

label {
    font-weight: bold;
    display: block;
    margin-bottom: 0.25rem;
}

input,
select {
    width: 100%;
    padding: 6px;
    font-size: 1rem;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
}

.button-row {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
    justify-content: flex-end;
}

.button-row button {
    background-color: #007bff;
    border: none;
    color: white;
    padding: 0.5rem 1rem;
    font-size: 1rem;
    border-radius: 4px;
    cursor: pointer;
}

.button-row button:hover {
    background-color: #0056b3;
}

.order-wrapper {
    display: flex;
    gap: 24px;
    /* margin-bottom: 32px; */
    flex-direction: column;
}
</style>
