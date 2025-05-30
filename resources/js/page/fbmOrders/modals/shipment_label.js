import "./shipment_label.css";
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    props: {
        show: Boolean,
        shipmentData: Array,
    },
    emits: ["close", "submit"],
    data() {
        return {
            forms: {},
            rateResults: [],
            selectedRateMap: {},
            selectedCarriers: {}, // ✅ This is required
            selectedCarrierOrderId: null,
            showCarrierModal: false,
            selectedCarrierOrder: null,
        };
    },
    computed: {
        hasRates() {
            return this.rateResults.some(
                (r) => r.rates?.payload?.ShippingServiceList?.length
            );
        },
        selectedCarrierOrder() {
            return this.rateResults.find(
                (r) => r.platform_order_id === this.selectedCarrierOrderId
            );
        },
        hasValidShipments() {
            return this.shipmentData.some(
                (order) =>
                    this.selectedCarriers[order.platform_order_id] &&
                    this.forms[order.platform_order_id]
            );
        },
    },
    watch: {
        shipmentData: {
            handler(newOrders) {
                if (!Array.isArray(newOrders)) return;
                newOrders.forEach((order) => {
                    const id = order.platform_order_id;
                    if (!this.forms[id]) {
                        this.forms[id] = {
                            deliveryExperience:
                                "DeliveryConfirmationWithoutSignature",
                            length: "",
                            width: "",
                            height: "",
                            dimensionUnit: "inches",
                            weight: "",
                            weightUnit: "pound",
                            currency: "",
                            shipBy: "",
                            deliverBy: "",
                        };
                    }
                });
            },
            immediate: true,
            deep: true,
        },
    },
    methods: {
        formatDatetext(isoDate) {
            if (!isoDate) return "";
            const date = new Date(isoDate);
            return date.toLocaleDateString("en-US", {
                weekday: "long", // "Monday"
                year: "numeric", // "2024"
                month: "short", // "Jan"
                day: "numeric", // "7"
            });
        },
        getRates() {
            const payload = {
                orders: this.shipmentData,
                forms: this.forms,
            };

            axios
                .post(
                    `${API_BASE_URL}/amzn/fbm-orders/purchase-label/rates`,
                    payload
                )
                .then((res) => {
                    this.rateResults = res.data.results || [];
                })
                .catch((err) => {
                    alert("Failed to get rates");
                    console.error(err);
                });
        },
        selectRate(orderId, rate) {
            this.selectedRateMap[orderId] = rate;
        },
        buyShipment() {
            const payload = {
                orders: this.shipmentData.map((order) => {
                    return {
                        ...order,
                        selectedCarrier:
                            this.selectedCarriers[order.platform_order_id] ||
                            null,
                    };
                }),
                forms: this.forms,
            };

            axios
                .post("/amzn/fbm-orders/purchase-label/createshipment", payload)
                .then((res) => {
                    console.log("Shipment purchase response:", res.data);
                    this.$emit("close"); // or handle success visually
                })
                .catch((err) => {
                    console.error("Buy shipment error:", err);
                    alert("Failed to buy shipment");
                });
        },
        manualShipment() {
            alert("Manual shipment not implemented yet.");
        },
        formatDate(dateStr) {
            if (!dateStr) return "";
            return new Date(dateStr).toLocaleDateString();
        },
        openCarrierModal(order) {
            console.log("rateResults:", this.rateResults);
            console.log("Looking for:", order.platform_order_id);
            this.selectedCarrierOrderId = order.platform_order_id;

            // Find the matching rateBlock for this order
            this.selectedCarrierOrder = this.rateResults.find(
                (r) => r.platform_order_id === order.platform_order_id
            );

            // Guard clause: only proceed if data is valid
            if (
                !this.selectedCarrierOrder ||
                !this.selectedCarrierOrder.rates
            ) {
                console.warn(
                    "No carrier data available for",
                    order.platform_order_id
                );
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
        },
    },
};
