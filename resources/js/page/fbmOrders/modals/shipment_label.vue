<template>
    <div v-if="show" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Shipment Label</h3>
                <button @click="$emit('close')" class="close-btn">&times;</button>
            </div>

            <div class="modal-body">
                <div class="shipment-body">


                    <div class="right-panel">
                        <div v-for="order in shipmentData" :key="order.platform_order_id" class="order-block">
                            <strong>AmazonOrderId:</strong> {{ order.platform_order_id }}<br />
                            <strong>Customer Name:</strong> {{ order.BuyerName }}<br />
                            <strong>Address:</strong>
                            {{ order.address_line1 }}, {{ order.city }}, {{ order.StateOrRegion }},
                            {{ order.postal_code }}, {{ order.CountryCode }}

                            <template v-if="forms[order.platform_order_id]">
                                <div class="package-dimensions">
                                    <h4>Package Dimensions</h4>

                                    <div class="form-group">
                                        <label>Delivery Experience</label>
                                        <select v-model="forms[order.platform_order_id].deliveryExperience">
                                            <option value="DeliveryConfirmationWithoutSignature">Delivery Confirmation
                                                Without Signature
                                            </option>
                                            <option value="DeliveryConfirmationWithSignature">Delivery Confirmation With
                                                Signature</option>
                                            <option value="DeliveryConfirmationWithAdultSignature">Delivery Confirmation
                                                With Adult
                                                Signature</option>
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
                            </template>
                        </div>

                        <div class="button-row">
                            <button @click="getRates">Get Rates</button>
                            <button @click="buyShipment">Buy Shipment</button>
                            <button @click="manualShipment">Manual Shipment</button>
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
            selectedRate: null
        };
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
        getRates() {
            const payload = {
                orders: this.shipmentData,
                forms: this.forms
            };

            axios.post(`${API_BASE_URL}/amzn/fbm-orders/purchase-label/rates`, payload)
                .then(res => {
                    this.rateResults = res.data || [];
                })
                .catch(err => {
                    alert('Failed to get rates');
                    console.error(err);
                });
        },
        buyShipment() {
            const payload = {
                selectedRate: this.selectedRate,
                orders: this.shipmentData,
                forms: this.forms
            };
            this.$emit('submit', payload);
        },
        manualShipment() {
            alert('Manual shipment not implemented yet.');
        }
    }
};
</script>

<style scoped>
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
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.close-btn {
    background: transparent;
    font-size: 1.5rem;
    border: none;
    cursor: pointer;
}

.shipment-body {
    display: flex;
    gap: 2rem;
}

.left-panel {
    flex: 1;
    max-width: 50%;
    overflow-y: auto;
}

.right-panel {
    flex: 1;
}

.rates-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.rates-table th,
.rates-table td {
    border: 1px solid #ccc;
    padding: 8px;
    vertical-align: top;
}

.package-dimensions {
    background: #fff7ef;
    padding: 1rem;
    border: 1px solid #f0d9c2;
    border-radius: 8px;
    margin-top: 1rem;
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
}

.button-row {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}
</style>
