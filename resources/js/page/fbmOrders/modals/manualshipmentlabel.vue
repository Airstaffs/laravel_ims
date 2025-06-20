<template>
    <div v-if="visible" class="modal manualShipmentLabel">
        <div class="modal-overlay" @click="closeModal"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2>Manual Shipment Label</h2>
                <button class="btn btn-modal-close" @click="closeModal">
                    &times;
                </button>
            </div>

            <div class="modal-body">
                <form>
                    <fieldset>
                        <label>Amazon Order ID</label>
                        <input
                            v-model="form.AmazonOrderId"
                            type="text"
                            class="form-control"
                            required
                        />
                    </fieldset>

                    <fieldset>
                        <label>LCode (Step 0.01, min 0)</label>
                        <input
                            v-model.number="form.LCode"
                            type="number"
                            class="form-control"
                            step="0.01"
                            min="0"
                            required
                        />
                    </fieldset>

                    <fieldset>
                        <label>Ship Date</label>
                        <input
                            v-model="form.ShipDate"
                            type="datetime-local"
                            class="form-control"
                            required
                        />
                    </fieldset>

                    <fieldset>
                        <label>Tracking Number</label>
                        <input
                            v-model="form.TrackingNumber"
                            type="text"
                            class="form-control"
                            required
                        />
                    </fieldset>

                    <fieldset>
                        <label>Carrier</label>
                        <select
                            v-model="form.Carrier"
                            class="form-select"
                            required
                        >
                            <option value="Other">Other</option>
                            <option value="USPS">USPS</option>
                            <option value="UPS">UPS</option>
                            <option value="FedEx">FedEx</option>
                        </select>
                    </fieldset>

                    <fieldset>
                        <label>Shipping Delivery Experience</label>
                        <select
                            v-model="form.DeliveryExperience"
                            class="form-select"
                            required
                        >
                            <option
                                value="DeliveryConfirmationWithoutSignature"
                            >
                                DeliveryConfirmationWithoutSignature
                            </option>
                            <option value="DeliveryConfirmationWithSignature">
                                DeliveryConfirmationWithSignature
                            </option>
                            <option
                                value="DeliveryConfirmationWithAdultSignature"
                            >
                                DeliveryConfirmationWithAdultSignature
                            </option>
                            <option value="NoTracking">NoTracking</option>
                        </select>
                    </fieldset>

                    <fieldset>
                        <label>Shipping Label PDF</label>
                        <input
                            @change="handleFileUpload"
                            type="file"
                            accept="application/pdf"
                            class="form-control"
                            required
                        />
                    </fieldset>

                    <div class="button-container">
                        <button
                            type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal"
                            @click="resetForm"
                        >
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            Submit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "ManualShipmentLabelModal",
    props: {
        visible: Boolean,
    },
    data() {
        return {
            bsModal: null,
            form: {
                AmazonOrderId: "",
                LCode: 0.01,
                ShipDate: "",
                TrackingNumber: "",
                Carrier: "USPS",
                DeliveryExperience: "DeliveryConfirmationWithoutSignature",
                shippinglabelpdf: null,
            },
        };
    },
    mounted() {
        const modalEl = document.getElementById("manualShipmentLabelModal");
        if (modalEl) {
            this.bsModal = new bootstrap.Modal(modalEl, {
                backdrop: "static",
                keyboard: false,
            });

            modalEl.addEventListener("hidden.bs.modal", () => {
                this.resetForm();
            });
        }
    },
    methods: {
        closeModal() {
            this.$emit("close");
        },
        handleFileUpload(event) {
            this.form.shippinglabelpdf = event.target.files[0];
        },
        async submitLabel() {
            if (!this.form.shippinglabelpdf) {
                alert("Please upload a PDF file.");
                return;
            }

            const formData = new FormData();
            for (const key in this.form) {
                formData.append(key, this.form[key]);
            }

            try {
                const res = await axios.post(
                    "/your-api/manual-label-submit",
                    formData,
                    {
                        headers: {
                            "Content-Type": "multipart/form-data",
                        },
                    }
                );

                if (res.data.success) {
                    alert("Label submitted successfully!");
                    this.resetForm();
                    window.closeManualShipmentLabel();
                } else {
                    alert("Submission failed: " + res.data.error);
                }
            } catch (err) {
                console.error(err);
                alert("Error occurred during submission.");
            }
        },
        resetForm() {
            this.form = {
                AmazonOrderId: "",
                LCode: 0.01,
                ShipDate: "",
                TrackingNumber: "",
                Carrier: "USPS",
                DeliveryExperience: "DeliveryConfirmationWithoutSignature",
                shippinglabelpdf: null,
            };
        },
    },
};
</script>

<style scoped>
.modal.manualShipmentLabel {
    display: flex;
    justify-content: center;
    align-items: center;
}

.manualShipmentLabel .modal-content {
    max-width: 600px;
}

.manualShipmentLabel .modal-body {
    padding: 20px;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: stretch;
    gap: 10px;
}

.manualShipmentLabel .button-container {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    margin-top: 20px;
}

.manualShipmentLabel .button-container button {
    width: 120px;
    justify-content: center;
    color: #fff;
}

@media (max-width: 767px) {
    .manualShipmentLabel .button-container button {
        width: calc(100% / 2 - 5px);
    }
}
</style>
