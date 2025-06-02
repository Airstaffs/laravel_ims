<template>
    <div v-if="show" class="modal shipmentLabel">
        <div class="modal-overlay" @click="$emit('close')"></div>

        <div class="modal-content">
            <div class="modal-header">
                <h2>Shipment Label</h2>
                <button class="btn btn-modal-close" @click="$emit('close')">&times;</button>
            </div>

            <div class="modal-body">
                <div class="order-section" v-for="order in shipmentData" :key="order.platform_order_id">
                    <div class="left-container">
                        <div class="order-info">
                            <ul class="list-unstyled m-0">
                                <li>
                                    <p><strong>Amazon Order Id: </strong>{{ order.platform_order_id }}</p>
                                    <p><strong>Customer Name: </strong>{{ order.BuyerName }}</p>
                                    <p><strong>Address: </strong>
                                    <ul class="list-unstyled m-0 d-flex flex-column align-items-end">
                                        <li>{{ order.address_line1 }},</li>
                                        <li>{{ order.city }}, {{ order.StateOrRegion }}</li>
                                        <li>{{ order.postal_code }}, {{ order.CountryCode }}</li>
                                    </ul>
                                    </p>
                                </li>

                                <hr>

                                <li class="d-flex flex-column gap-2" v-if="order.items && order.items.length">
                                    <p><strong>Order Items: </strong></p>
                                    <div class="orderItems-container">
                                        <ul class="list-unstyled m-0" v-for="(item, index) in order.items" :key="index">
                                            <li>Order Item Id: <strong>{{ item.platform_order_item_id }}</strong></li>
                                            <li>ASIN: <strong>{{ item.platform_asin }}</strong></li>
                                            <li>SKU: <strong>{{ item.platform_sku }}</strong></li>
                                            <li>Qty: <strong>{{ item.QuantityOrdered }}</strong></li>
                                            <li>Status: <strong class="badge"
                                                    :class="'status-' + item.order_status">{{ item.order_status }}</strong>
                                            </li>
                                            <li>Condition: <strong>{{ item.ConditionSubtypeId }} -
                                                    {{ item.ConditionId }}</strong></li>
                                            <li>Unit Price: <strong>${{ item.unit_price }}</strong></li>
                                            <li>Unit Tax: <strong>${{ item.unit_tax }}</strong></li>
                                        </ul>
                                    </div>
                                </li>

                                <hr>

                                <li
                                    v-if="(!selectedCarriers.hasOwnProperty(order.platform_order_id) || !selectedCarriers[order.platform_order_id])">
                                    <button v-if="rateResults && rateResults.length" @click="openCarrierModal(order)"
                                        class="btn btn-carrier">
                                        Select Carrier Option
                                    </button>

                                    <div v-else class="alert alert-danger m-0">
                                        <p>
                                            <strong class="d-flex flex-column">
                                                <span>No rates available.</span>
                                                <span>Please click "Get Rates" after filling out the form.</span>
                                            </strong>
                                        </p>
                                    </div>
                                </li>

                                <li
                                    v-if="selectedCarriers.hasOwnProperty(order.platform_order_id) && selectedCarriers[order.platform_order_id]">
                                    <ul class="list-unstyled m-0 selected-carrier">
                                        <li>
                                            <strong>Selected Carrier: </strong>
                                            {{ selectedCarriers[order.platform_order_id].ShippingServiceName }}
                                        </li>
                                        <li>
                                            <strong>Rate: </strong>
                                            ${{ selectedCarriers[order.platform_order_id].Rate.Amount }}
                                        </li>
                                        <li>
                                            <strong>Ship Date: </strong>
                                            {{ formatDatetext(selectedCarriers[order.platform_order_id].ShipDate) }}
                                        </li>
                                        <li>
                                            <strong>Estimated Delivery: </strong>
                                            {{
                                                formatDatetext(selectedCarriers[order.platform_order_id].EarliestEstimatedDeliveryDate)
                                            }} –
                                            {{
                                                formatDatetext(selectedCarriers[order.platform_order_id].LatestEstimatedDeliveryDate)
                                            }}
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="right-container">
                        <form v-if="forms && forms[order.platform_order_id]" class="package-dimensions">
                            <fieldset>
                                <label>Delivery Experience</label>
                                <select class="form-control"
                                    v-model="forms[order.platform_order_id].deliveryExperience">
                                    <option value="DeliveryConfirmationWithoutSignature">Without Signature
                                    </option>
                                    <option value="DeliveryConfirmationWithSignature">With Signature</option>
                                    <option value="DeliveryConfirmationWithAdultSignature">With Adult Signature
                                    </option>
                                    <option value="NoTracking">No Tracking</option>
                                </select>
                            </fieldset>

                            <fieldset>
                                <label>Length</label>
                                <input class="form-control" type="number"
                                    v-model="forms[order.platform_order_id].length" required />
                            </fieldset>

                            <fieldset>
                                <label>Width</label>
                                <input class="form-control" type="number" v-model="forms[order.platform_order_id].width"
                                    required />
                            </fieldset>

                            <fieldset>
                                <label>Height</label>
                                <input class="form-control" type="number"
                                    v-model="forms[order.platform_order_id].height" required />
                            </fieldset>

                            <fieldset>
                                <label>Unit</label>
                                <select class="form-control" v-model="forms[order.platform_order_id].dimensionUnit">
                                    <option value="inches">Inches</option>
                                    <option value="centimeters">Centimeters</option>
                                </select>
                            </fieldset>

                            <fieldset>
                                <label>Weight</label>
                                <input class="form-control" type="number"
                                    v-model="forms[order.platform_order_id].weight" />
                            </fieldset>

                            <fieldset>
                                <label>Weight Unit</label>
                                <select class="form-control" v-model="forms[order.platform_order_id].weightUnit">
                                    <option value="pound">Pound</option>
                                    <option value="grams">Grams</option>
                                    <option value="ounces">Ounces</option>
                                </select>
                            </fieldset>

                            <fieldset>
                                <label>Currency Code</label>
                                <input class="form-control" v-model="forms[order.platform_order_id].currency"
                                    placeholder="Optional" />
                            </fieldset>

                            <fieldset>
                                <label>Ship By</label>
                                <input class="form-control" type="datetime-local"
                                    v-model="forms[order.platform_order_id].shipBy" />
                            </fieldset>

                            <fieldset>
                                <label>Deliver By</label>
                                <input class="form-control" type="datetime-local"
                                    v-model="forms[order.platform_order_id].deliverBy" />
                            </fieldset>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button @click="getRates">Get Rates</button>
                <button @click="buyShipment" :disabled="!hasValidShipments">Buy Shipment</button>
                <button @click="manualShipment">Manual Shipment</button>
            </div>
        </div>
    </div>

    <div v-if="showCarrierModal" class="modal carrier">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Select Carrier for {{ selectedCarrierOrderId }}</h2>
                <button class="btn btn-modal-close" @click="showCarrierModal = false">&times;</button>
            </div>

            <div class="modal-body">
                <div class="d-none d-md-block">
                    <table class="table" v-if="selectedCarrierOrder?.rates?.payload?.ShippingServiceList?.length">
                        <thead class="table-dark">
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
                                        :name="'select-carrier-' + selectedCarrierOrder.platform_order_id" :value="rate"
                                        v-model="selectedCarriers[selectedCarrierOrder.platform_order_id]"
                                        @change="ensureSelectedCarrierEntry(selectedCarrierOrder.platform_order_id)" />
                                </td>
                                <td>
                                    <p><strong>{{ rate.ShippingServiceName }}</strong></p>
                                    <p><strong>Included: </strong></p>
                                    <ul class="list-unstyled m-0">
                                        <li v-for="b in rate.Benefits?.IncludedBenefits || []" :key="b"> {{ b }}</li>
                                        <li>
                                            <ul class="list-unstyled m-0 shipping-details">
                                                <li>
                                                    Ship Date: <strong>{{ formatDatetext(rate.ShipDate) }}</strong>
                                                </li>
                                                <li>
                                                    Rate: <strong>${{ rate.Rate.Amount }}</strong>
                                                </li>
                                                <li>
                                                    Estimated Delivery:
                                                    <strong>{{ formatDatetext(rate.EarliestEstimatedDeliveryDate) }} -
                                                        {{
                                                            formatDatetext(rate.LatestEstimatedDeliveryDate) }}</strong>
                                                </li>
                                            </ul>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <input type="radio"
                                        :name="'select-carrier-' + selectedCarrierOrder.platform_order_id" :value="null"
                                        v-model="selectedCarriers[selectedCarrierOrder.platform_order_id]" />
                                </td>
                                <td colspan="4">
                                    <div class="alert alert-danger m-0">
                                        <strong>No carrier selected (skip this order)</strong>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="d-block d-md-none" v-if="selectedCarrierOrder?.rates?.payload?.ShippingServiceList?.length">
                    <div v-for="(rate, idx) in selectedCarrierOrder.rates.payload.ShippingServiceList" :key="idx"
                        class="card mb-3 shadow-sm">
                        <div class="card-body d-flex flex-column gap-2">
                            <!-- Radio Select -->
                            <div class="form-check">
                                <input class="form-check-input" type="radio"
                                    :name="'select-carrier-' + selectedCarrierOrder.platform_order_id" :value="rate"
                                    v-model="selectedCarriers[selectedCarrierOrder.platform_order_id]"
                                    @change="ensureSelectedCarrierEntry(selectedCarrierOrder.platform_order_id)"
                                    :id="'rate-' + idx" />
                                <label class="form-check-label" :for="'rate-' + idx">
                                    <strong>{{ rate.ShippingServiceName }}</strong>
                                </label>
                            </div>

                            <!-- Included Benefits -->
                            <div>
                                <strong>Included:</strong>
                                <ul class="list-unstyled mb-1">
                                    <li v-for="b in rate.Benefits?.IncludedBenefits || []" :key="b">• {{ b }}</li>
                                </ul>
                            </div>

                            <!-- Shipping Details -->
                            <ul class="list-unstyled shipping-details mb-0">
                                <li>Ship Date: <strong>{{ formatDatetext(rate.ShipDate) }}</strong></li>
                                <li>Rate: <strong>${{ rate.Rate.Amount }}</strong></li>
                                <li>
                                    Estimated Delivery:
                                    <strong>{{ formatDatetext(rate.EarliestEstimatedDeliveryDate) }} - {{
                                        formatDatetext(rate.LatestEstimatedDeliveryDate) }}</strong>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- No Carrier Option -->
                    <div class="card border-danger">
                        <div class="card-body">
                            <div class="form-check">
                                <input class="form-check-input" type="radio"
                                    :name="'select-carrier-' + selectedCarrierOrder.platform_order_id" :value="null"
                                    v-model="selectedCarriers[selectedCarrierOrder.platform_order_id]"
                                    :id="'no-carrier-' + selectedCarrierOrder.platform_order_id" />
                                <label class="form-check-label text-danger"
                                    :for="'no-carrier-' + selectedCarrierOrder.platform_order_id">
                                    <strong>No carrier selected (skip this order)</strong>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<script>
    import ShipmentLabel from "./shipment_label.js";
    export default ShipmentLabel;
</script>
