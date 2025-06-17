<template>
    <div v-if="visible" class="modal printInvoice">
        <div class="modal-overlay" @click="closeModal"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-shipping-fast"></i>
                    <span>Print Invoice</span>
                </h2>
                <button class="btn btn-modal-close" @click="closeModal">
                    &times;
                </button>
            </div>

            <div class="modal-body">
                <p class="d-flex flex-column justify-content-start align-items-stretch">
                    <span>Are you sure you want to print the invoice for</span>
                    <strong>
                        Order ID: {{ order?.platform_order_id }} (Store:
                        {{ order?.storename || "N/A" }})?
                    </strong>
                </p>

                <div class="toggle-container">
                    <label>
                        <input type="checkbox" v-model="displayPrice" />
                        Display Price: {{ displayPrice ? "ON" : "OFF" }}
                    </label>
                    <label>
                        <input type="checkbox" v-model="testPrint" />
                        Test Print: {{ testPrint ? "ON" : "OFF" }}
                    </label>
                    <label>
                        <input type="checkbox" v-model="signatureRequired" />
                        Signature Required:
                        {{ signatureRequired ? "ON" : "OFF" }}
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-edit">Edit Price</button>
                <button class="btn btn-view" @click="handleInvoiceAction('ViewInvoice')">
                    View Pdf
                </button>
                <button :disabled="loading" class="btn btn-print" @click="handleInvoiceAction('PrintInvoice')">
                    Yes, Print
                </button>
                <button class="btn btn-cancel" @click="closeModal">
                    Cancel
                </button>
            </div>

            <!-- New -->
            <div>
                <span>Shipment Label</span>
                <input v-model="note" placeholder="Enter label note" class="form-control mt-2" />
                <button class="btn btn-outline-primary" @click="handleShippingLabelAction('ViewShipmentLabel')">
                    View Shipping Label
                </button>

                <!-- Print Shipping Label -->
                <button class="btn btn-success" @click="handleShippingLabelAction('PrintShipmentLabel')">
                    Print Shipping Label
                </button>
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
                platform_order_ids: [this.order.platform_order_id],
                platform_order_item_ids: this.order.items?.map((i) => i.platform_order_item_id) || [],
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
                    payload,
                    {
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    }
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
                action: action,
                note: this.note,
            };

            try {
                const res = await axios.post(
                    `${API_BASE_URL}/fbm-orders-shippinglabel`,
                    payload,
                    {
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    }
                );

                if (res.data.success) {
                    const result = res.data.results?.[0];

                    if (action === 'ViewShipmentLabel') {
                        const blob = new Blob([result.zpl_preview], { type: 'text/plain' });
                        const url = URL.createObjectURL(blob);
                        window.open(url, '_blank');
                    } else {
                        alert('Shipping label sent to printer!');
                    }
                } else {
                    alert('Failed: ' + (res.data.message || 'Unknown error'));
                }
            } catch (err) {
                console.error(err);
                alert('Error occurred while processing shipping label.');
            }
        }
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

.toggle-container label {
    display: block;
    margin-bottom: 0;
    font-size: 16px;
    cursor: pointer;
}

.toggle-container input {
    margin-right: 8px;
}

.btn-edit {
    background: #007bff;
    color: white;
}

.btn-view {
    background: #17a2b8;
    color: white;
}

.btn-print {
    background: #28a745;
    color: white;
}

.btn-cancel {
    background: #dc3545;
    color: white;
}

.btn:hover {
    opacity: 0.9;
}
</style>
