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
                <form
                    @submit.prevent="submitLabel"
                    enctype="multipart/form-data"
                >
                    <fieldset>
                        <label>Amazon Order ID</label>
                        <input
                            v-model="form.AmazonOrderId"
                            type="text"
                            class="form-control"
                            required
                        />
                    </fieldset>

                    <!-- New Input -->
                    <div class="mb-3">
                        <label class="form-label">Order Item IDs</label>
                        <div
                            v-for="(item, index) in form.OrderItemIds"
                            :key="index"
                            class="input-group mb-2"
                        >
                            <input
                                v-model="form.OrderItemIds[index]"
                                type="text"
                                class="form-control"
                                placeholder="Enter OrderItemId"
                                required
                                style="width: 100%"
                            />
                            <button
                                type="button"
                                class="btn btn-danger"
                                @click="removeOrderItemId(index)"
                                v-if="form.OrderItemIds.length > 1"
                                style="width: 35px"
                            >
                                X
                            </button>
                        </div>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary mt-2"
                            @click="addOrderItemId"
                        >
                            + Add OrderItemId
                        </button>
                    </div>

                    <fieldset>
                        <label>LCode</label>
                        <input
                            v-model.number="form.LCode"
                            type="number"
                            class="form-control"
                            step="0.01"
                            min="00.00"
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

                    <!-- New Input -->

                    <fieldset>
                        <label
                            class="form-label d-flex justify-content-between align-items-center"
                        >
                            <span
                                >Carrier Description
                                <i
                                    class="bi bi-info-circle-fill ms-1 text-primary"
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    title="Choose from existing options or input manually, additionally you can click add to options to add your input to the options!"
                                    style="cursor: pointer"
                                ></i
                            ></span>
                            <div
                                class="btn-group btn-group-sm ms-2"
                                role="group"
                            >
                                <button
                                    type="button"
                                    class="btn"
                                    :class="
                                        carrierMode === 'input'
                                            ? 'btn-primary'
                                            : 'btn-outline-secondary'
                                    "
                                    @click="carrierMode = 'input'"
                                >
                                    Input
                                </button>
                                <button
                                    type="button"
                                    class="btn"
                                    :class="
                                        carrierMode === 'select'
                                            ? 'btn-primary'
                                            : 'btn-outline-secondary'
                                    "
                                    @click="carrierMode = 'select'"
                                >
                                    Select
                                </button>
                            </div>
                        </label>

                        <!-- Input with embedded add button -->
                        <div
                            v-if="carrierMode === 'input'"
                            class="position-relative"
                        >
                            <input
                                v-model="form.CarrierDescription"
                                type="text"
                                class="form-control pe-5"
                                placeholder="Enter carrier description"
                                required
                            />
                            <button
                                type="button"
                                class="btn btn-outline-success position-absolute top-50 end-0 translate-middle-y me-2"
                                style="z-index: 10"
                                @click="addCarrierDescription"
                            >
                                Add to Option
                            </button>
                        </div>

                        <!-- Select dropdown -->
                        <select
                            v-else
                            v-model="form.CarrierDescription"
                            class="form-select"
                            required
                        >
                            <option disabled value="">
                                -- Select carrier description --
                            </option>
                            <option
                                v-for="desc in carrierDescriptions"
                                :key="desc"
                                :value="desc"
                            >
                                {{ desc }}
                            </option>
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
                        <small class="text-danger mt-1 d-block"
                            >⚠ Please upload a valid PDF file only. Other file
                            types are not supported.</small
                        >
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
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "ManualShipmentLabelModal",
    props: {
        visible: Boolean,
    },
    data() {
        return {
            bsModal: null,
            form: {
                AmazonOrderId: "Enter AmazonOrderId",
                OrderItemIds: [""],
                LCode: 0.0,
                ShipDate: "",
                TrackingNumber: "Enter Tracking Number",
                Carrier: "USPS",
                DeliveryExperience: "DeliveryConfirmationWithoutSignature",
                shippinglabelpdf: null,
                CarrierDescription: "",
            },
            carrierMode: "select",
            carrierDescriptions: [],
        };
    },
    mounted() {
        // Activate Bootstrap tooltips
        const tooltipTriggerList = [].slice.call(
            document.querySelectorAll('[data-bs-toggle="tooltip"]')
        );
        tooltipTriggerList.forEach((el) => {
            new bootstrap.Tooltip(el);
        });

        // Modal logic
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

        // Fetch dynamic carrier descriptions
        this.fetchCarrierDescriptions();
    },
    methods: {
        toggleCarrierMode() {
            this.carrierMode =
                this.carrierMode === "input" ? "select" : "input";
        },
        async addCarrierDescription() {
            const value = this.form.CarrierDescription.trim();
            if (!value) {
                alert("Carrier description is empty.");
                return;
            }

            try {
                const res = await axios.post(
                    `${API_BASE_URL}/fbm-orders-add-new-carrier`,
                    {
                        name: value,
                    }
                );

                if (res.data.success) {
                    if (!this.carrierDescriptions.includes(value)) {
                        this.carrierDescriptions.push(value);
                    }
                    alert("Carrier description added.");
                } else {
                    alert(
                        res.data.error || "Failed to save carrier description."
                    );
                }
            } catch (err) {
                console.error(err);
                alert("Server error saving carrier description.");
            }
        },
        addOrderItemId() {
            this.form.OrderItemIds.push("");
        },
        removeOrderItemId(index) {
            this.form.OrderItemIds.splice(index, 1);
        },
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
                if (key === "OrderItemIds") {
                    this.form.OrderItemIds.forEach((itemId, i) => {
                        formData.append(`OrderItemIds[${i}]`, itemId);
                    });
                } else {
                    formData.append(key, this.form[key]);
                }
            }

            try {
                const res = await axios.post(
                    `${API_BASE_URL}/fbm-orders-manualshipmentlabel`,
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
                LCode: 0.0,
                ShipDate: "",
                TrackingNumber: "",
                Carrier: "USPS",
                DeliveryExperience: "DeliveryConfirmationWithoutSignature",
                shippinglabelpdf: null,
            };
        },
        async fetchCarrierDescriptions() {
            try {
                const res = await axios.get(
                    `${API_BASE_URL}/fbm-orders-carrier-options`
                );
                if (res.data.success && Array.isArray(res.data.options)) {
                    this.carrierDescriptions = res.data.options;
                }
            } catch (err) {
                console.error("Failed to load carrier descriptions:", err);
            }
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
    background-color: #fff;
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
