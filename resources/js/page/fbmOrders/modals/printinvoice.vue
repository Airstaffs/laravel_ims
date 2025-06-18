<template>
    <div v-if="visible" class="modal printInvoice">
        <div class="modal-overlay" @click="closeModal"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-shipping-fast"></i>
                    <span>Print</span>
                </h2>
                <button class="btn btn-modal-close" @click="closeModal">
                    &times;
                </button>
            </div>

            <div class="modal-body">
                <div class="tab-container">
                    <button
                        :class="{ active: activeTab === 1 }"
                        @click="activeTab = 1"
                    >
                        Invoice
                    </button>
                    <button
                        :class="{ active: activeTab === 2 }"
                        @click="activeTab = 2"
                    >
                        Shipping Label
                    </button>
                </div>

                <div class="tab-content">
                    <div class="invoice" v-if="activeTab === 1">
                        <p
                            class="d-flex flex-column justify-content-start align-items-stretch"
                        >
                            <span
                                >Are you sure you want to print the invoice
                                for</span
                            >
                            <strong>
                                Order ID: {{ order?.platform_order_id }} (Store:
                                {{ order?.storename || "N/A" }})?
                            </strong>
                        </p>

                        <div class="toggle-container">
                            <label class="toggle-label">
                                <label class="switch">
                                    <input
                                        type="checkbox"
                                        v-model="displayPrice"
                                    />
                                    <span class="slider">
                                        <span>OFF</span>
                                        <span>ON</span>
                                    </span>
                                </label>
                                <span class="switch-text">Display Price</span>
                            </label>

                            <label class="toggle-label">
                                <label class="switch">
                                    <input
                                        type="checkbox"
                                        v-model="testPrint"
                                    />
                                    <span class="slider">
                                        <span>OFF</span>
                                        <span>ON</span>
                                    </span>
                                </label>
                                <span class="switch-text">Test Print</span>
                            </label>

                            <label class="toggle-label">
                                <label class="switch">
                                    <input
                                        type="checkbox"
                                        v-model="signatureRequired"
                                    />
                                    <span class="slider">
                                        <span>OFF</span>
                                        <span>ON</span>
                                    </span>
                                </label>
                                <span class="switch-text"
                                    >Signature Required</span
                                >
                            </label>
                        </div>

                        <div class="button-container">
                            <button class="btn btn-edit">Edit Price</button>
                            <button
                                class="btn btn-view"
                                @click="handleInvoiceAction('ViewInvoice')"
                            >
                                View Pdf
                            </button>
                            <button
                                :disabled="loading"
                                class="btn btn-print"
                                @click="handleInvoiceAction('PrintInvoice')"
                            >
                                Yes, Print
                            </button>
                        </div>
                    </div>

                    <div class="shipping-label" v-if="activeTab === 2">
                        <input
                            v-model="note"
                            placeholder="Enter label note"
                            class="form-control"
                        />

                        <div class="toggle-container">
                            <label class="toggle-label">
                                <label class="switch">
                                    <input
                                        type="checkbox"
                                        v-model="testPrint"
                                    />
                                    <span class="slider">
                                        <span>OFF</span>
                                        <span>ON</span>
                                    </span>
                                </label>
                                <span class="switch-text">Test Print</span>
                            </label>
                        </div>

                        <div class="button-container">
                            <button
                                class="btn btn-primary"
                                @click="
                                    handleShippingLabelAction(
                                        'ViewShipmentLabel'
                                    )
                                "
                            >
                                View Shipping Label
                            </button>

                            <!-- Print Shipping Label -->
                            <button
                                class="btn btn-success"
                                @click="
                                    handleShippingLabelAction(
                                        'PrintShipmentLabel'
                                    )
                                "
                            >
                                Print Shipping Label
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    props: {
        visible: Boolean,
        order: Object,
    },
    data() {
        return {
            activeTab: 1,
            displayPrice: false,
            testPrint: false,
            signatureRequired: false,
            loading: false,
            note: "",
        };
    },
    methods: {
        closeModal() {
            this.$emit("close");
        },

        async handleInvoiceAction(action) {
            const payload = {
                platform_order_ids: [this.order.platform_order_id], // ✅ fixed
                platform_order_item_ids:
                    this.order.items?.map((i) => i.platform_order_item_id) ||
                    [],
                action: action,
                settings: {
                    displayPrice: this.displayPrice,
                    testPrint: this.testPrint,
                    signatureRequired: this.signatureRequired,
                },
            };

            try {
                const res = await axios.post(
                    `${API_BASE_URL}/fbm-orders-invoice`,
                    payload
                );

                if (res.data.success) {
                    if (action === "ViewInvoice" && res.data.results?.length) {
                        const pdfUrl = res.data.results[0].pdf_url;
                        if (pdfUrl) {
                            window.open(pdfUrl, "_blank");
                        } else {
                            alert("PDF not available.");
                        }
                    }
                } else {
                    alert("Failed: " + (res.data.message || "Unknown error."));
                }
            } catch (err) {
                console.error(err);
                alert("Error occurred while processing invoice.");
            }
        },
        async handleShippingLabelAction(action) {
            const payload = {
                platform_order_ids: [this.order.platform_order_id],
                action: action, // ViewShipmentLabel or PrintShipmentLabel
                note: this.note,
            };

            try {
                const res = await axios.post(
                    `${API_BASE_URL}/fbm-orders-shippinglabel`,
                    payload
                );

                if (res.data.success) {
                    const result = res.data.results?.[0];

                    if (action === "ViewShipmentLabel") {
                        const blob = new Blob([result.zpl_preview], {
                            type: "text/plain",
                        });
                        const url = URL.createObjectURL(blob);
                        window.open(url, "_blank");
                    } else {
                        alert("Shipping label sent to printer!");
                    }
                } else {
                    alert("Failed: " + (res.data.message || "Unknown error"));
                }
            } catch (err) {
                console.error(err);
                alert("Error occurred while processing shipping label.");
            }
        },
    },
};
</script>

<style scoped>
.modal.printInvoice {
    display: flex !important;
    align-items: center;
    justify-content: center;
}

.printInvoice .modal-content {
    max-width: 500px;
}

.printInvoice .modal-body {
    padding: 20px;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: stretch;
    gap: 10px;
}

.printInvoice .modal-footer {
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.printInvoice .modal-footer button {
    height: 40px;
    width: calc(100% / 2 - 5px);
    margin: 0;
    justify-content: center;
}

.order-info {
    margin: 10px 0 20px;
    font-size: 16px;
    line-height: 1.4;
}

.btn-edit {
    background: #007bff;
}

.btn-view {
    background: #17a2b8;
}

.btn-print {
    background: #28a745;
}

.btn-cancel {
    background: #dc3545;
}

.btn:hover {
    opacity: 0.9;
}

.switch {
    position: relative;
    display: inline-block;
    width: 72px;
    height: 30px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: background-color 0.4s;
    border-radius: 30px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 8px;
    font-size: 12px;
    font-weight: bold;
    color: white;
    box-sizing: border-box;
}

.slider:before {
    content: "";
    position: absolute;
    height: 24px;
    width: 30px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    border-radius: 50%;
    transition: transform 0.4s;
}

input:checked + .slider {
    background-color: #4caf50;
}

input:checked + .slider:before {
    transform: translateX(36px);
}

.toggle-container {
    display: flex;
    flex-direction: column;
    gap: 1em;
    font-family: sans-serif;
    margin: 20px;
}

.toggle-label {
    display: flex;
    align-items: center;
    gap: 12px;
}

.switch-text {
    font-weight: 600;
}

.tab-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.tab-container button {
    width: calc(100% / 2);
    height: 40px;
    cursor: pointer;
    border: none;
    background-color: #eee;
    font-weight: bold;
    transition: background-color 0.2s;
}

.tab-container button.active {
    background-color: #4285f4;
    color: white;
}

.button-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.button-container button {
    height: 40px;
    justify-content: center;
    margin: 0;
    color: white;
}

.invoice .button-container button {
    width: calc(100% / 3 - 7px);
}

.shipping-label .button-container button {
    width: calc(100% / 2);
}
</style>
