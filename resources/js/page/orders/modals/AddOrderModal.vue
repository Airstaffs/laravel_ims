<template>
    <Dialog
        :visible="visible"
        @update:visible="$emit('update:visible', $event)"
        modal
        :style="{ width: '90%' }"
        :pt="{ root: { class: 'mobile-fullscreen-dialog' } }"
        @hide="resetForm"
    >
        <template #header>
            <div class="d-flex align-items-center gap-2">
                <i class="pi pi-plus-circle text-primary"></i>
                <h5 class="mb-0">Add New Order</h5>
            </div>
        </template>

        <div class="edit-order-container">
            <div class="form-grid-wrapper">

                <!-- LEFT: Images + General Info -->
                <div class="form-col-left">
                    <!-- Image Upload Section -->
                    <Card class="mb-2">
                        <template #title>
                            <h6 class="text-primary mb-0">Product Images</h6>
                            <Divider />
                        </template>
                        <template #content>
                            <!-- Preview -->
                            <div v-if="previewImages.length" class="image-section mb-3">
                                <div class="main-image">
                                    <img
                                        :src="previewImages[activePreviewIndex]"
                                        alt="Preview"
                                        style="width:100%; max-height:220px; object-fit:contain;"
                                    />
                                </div>
                                <div class="thumbnail-carousel mt-2">
                                    <div
                                        v-for="(img, idx) in previewImages"
                                        :key="idx"
                                        :class="['thumbnail', { active: idx === activePreviewIndex }]"
                                        @click="activePreviewIndex = idx"
                                    >
                                        <img :src="img" alt="thumb" />
                                        <button
                                            type="button"
                                            class="remove-thumb-btn"
                                            @click.stop="removeImage(idx)"
                                            title="Remove"
                                        >
                                            <i class="pi pi-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Drop Zone -->
                            <div
                                class="upload-dropzone"
                                :class="{ 'drag-over': isDragging }"
                                @dragover.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="onDrop"
                                @click="$refs.fileInput.click()"
                            >
                                <i class="pi pi-cloud-upload" style="font-size:1.5rem; color:#6c757d;"></i>
                                <p class="mb-0 mt-1 text-muted" style="font-size:0.8rem;">
                                    Click or drag images here (max 15)
                                </p>
                                <small class="text-muted">JPG, PNG, WEBP accepted</small>
                            </div>
                            <input
                                ref="fileInput"
                                type="file"
                                accept="image/*"
                                multiple
                                style="display:none"
                                @change="onFileSelect"
                            />
                            <small v-if="errors.images" class="text-danger">{{ errors.images }}</small>
                        </template>
                    </Card>

                    <!-- General Info -->
                    <Card>
                        <template #title>
                            <h6 class="text-primary mb-0">General Information</h6>
                            <Divider />
                        </template>
                        <template #content>
                            <fieldset>
                                <label>Product Title <span class="text-danger">*</span></label>
                                <Textarea
                                    size="small" fluid v-model="form.ProductTitle" rows="4"
                                    placeholder="Enter product title"
                                    :class="{ 'p-invalid': errors.ProductTitle }"
                                />
                                <small class="text-danger" v-if="errors.ProductTitle">{{ errors.ProductTitle }}</small>
                            </fieldset>
                            <fieldset>
                                <label>Order Number (RT ID)</label>
                                <InputText size="small" fluid v-model="form.rtid" placeholder="e.g. 123-456-7890" />
                            </fieldset>
                            <fieldset>
                                <label>Item Number <span class="text-danger">*</span></label>
                                <InputText
                                    size="small" fluid v-model="form.itemnumber"
                                    placeholder="Unique item number"
                                    :class="{ 'p-invalid': errors.itemnumber }"
                                />
                                <small class="text-danger" v-if="errors.itemnumber">{{ errors.itemnumber }}</small>
                            </fieldset>
                        </template>
                    </Card>
                </div>

                <!-- CENTER: Dates + Product Info + Tracking + Notes -->
                <div class="form-col-center">
                    <!-- Dates -->
                    <Card>
                        <template #title>
                            <h6 class="text-primary mb-0">Dates</h6>
                            <Divider />
                        </template>
                        <template #content>
                            <fieldset>
                                <label>Order Date</label>
                                <input type="date" class="form-control" v-model="form.orderdate" />
                            </fieldset>
                            <fieldset>
                                <label>Payment Date</label>
                                <input type="date" class="form-control" v-model="form.paymentdate" />
                            </fieldset>
                            <fieldset>
                                <label>Shipped Date</label>
                                <input type="date" class="form-control" v-model="form.shipdate" />
                            </fieldset>
                            <fieldset>
                                <label>Estimated Delivery Date</label>
                                <InputText
                                    size="small" fluid
                                    v-model="form.estimated_deliverydate"
                                    placeholder="e.g. 2026-03-20 or 2026-03-18 to 2026-03-22"
                                />
                                <small class="text-muted">Single date or range (YYYY-MM-DD to YYYY-MM-DD)</small>
                            </fieldset>
                        </template>
                    </Card>

                    <!-- Notes -->
                    <Card class="mt-2">
                        <template #content>
                            <fieldset>
                                <label>Description</label>
                                <Textarea size="small" fluid v-model="form.description" rows="3" placeholder="Description" class="no-resize" />
                            </fieldset>
                            <fieldset>
                                <label>Supplier Notes</label>
                                <Textarea size="small" fluid v-model="form.notes" rows="3" placeholder="Notes" class="no-resize" />
                            </fieldset>
                        </template>
                    </Card>

                    <!-- Tracking -->
                    <Card class="mt-2">
                        <template #title>
                            <h6 class="text-primary mb-0">Serial & Tracking</h6>
                            <Divider />
                        </template>
                        <template #content>
                            <fieldset>
                                <label>Serial Number</label>
                                <InputText size="small" fluid v-model="form.serialnumber" placeholder="Serial number" />
                            </fieldset>
                            <Divider />
                            <fieldset v-for="n in 4" :key="n">
                                <label>Tracking Number {{ n }}</label>
                                <InputText
                                    size="small" fluid
                                    v-model="form[n === 1 ? 'trackingnumber' : `trackingnumber${n}`]"
                                    :placeholder="`Tracking #${n}`"
                                />
                            </fieldset>
                        </template>
                    </Card>

                    <!-- Product Info -->
                    <Card class="mt-2">
                        <template #title>
                            <h6 class="text-primary mb-0">Product Info</h6>
                            <Divider />
                        </template>
                        <template #content>
                            <fieldset>
                                <label>Supplier ID/Name</label>
                                <InputText size="small" fluid v-model="form.seller" placeholder="Seller name or ID" />
                            </fieldset>
                            <fieldset>
                                <label>Material Type</label>
                                <Select v-model="form.materialtype" :options="materialOptions" optionLabel="label" optionValue="value" placeholder="Select Material Type" fluid size="small" />
                            </fieldset>
                            <fieldset>
                                <label>Source Type</label>
                                <Select v-model="form.sourceType" :options="sourceTypeOptions" optionLabel="label" optionValue="value" placeholder="Select Source Type" fluid size="small" />
                            </fieldset>
                            <fieldset>
                                <label>Carrier / Courier</label>
                                <InputText size="small" fluid v-model="form.carrier" placeholder="e.g. UPS, FedEx" />
                            </fieldset>
                            <fieldset>
                                <label>Listed Condition</label>
                                <Select v-model="form.listedcondition" :options="listedConditionOptions" optionLabel="label" optionValue="value" placeholder="Select Condition" fluid size="small" />
                            </fieldset>
                            <!-- Auto-derived item status -->
                            <fieldset v-if="form.listedcondition">
                                <label>Condition Status <small class="text-muted">(auto)</small></label>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <span
                                        :class="form.itemstatus === 'Working'
                                            ? 'badge bg-success'
                                            : 'badge bg-danger'"
                                        style="font-size: 0.85rem; padding: 0.4rem 0.75rem;"
                                    >
                                        {{ form.itemstatus }}
                                    </span>
                                    <small class="text-muted">
                                        {{ form.itemstatus === 'Working'
                                            ? 'Condition suggests item is functional'
                                            : 'Condition suggests item is non-functional' }}
                                    </small>
                                </div>
                            </fieldset>
                            <fieldset>
                                <label>Payment Method</label>
                                <Select v-model="form.paymentmethod" :options="paymentMethodOptions" optionLabel="label" optionValue="value" placeholder="Select Payment Method" fluid size="small" />
                            </fieldset>
                        </template>
                    </Card>
                </div>

                <!-- RIGHT: Pricing -->
                <div class="form-col-right" v-show="showPricingSection">
                    <Card>
                        <template #title>
                            <h4 class="text-primary">Pricing</h4>
                            <Divider />
                            <fieldset>
                                <label>Quantity</label>
                                <InputText type="number" class="text-end" fluid v-model="form.quantity" size="small" min="1" />
                            </fieldset>
                            <fieldset>
                                <label>Total Price</label>
                                <InputText type="number" class="text-end" fluid v-model="form.price" size="small" />
                            </fieldset>
                            <fieldset>
                                <label>Discount</label>
                                <InputText type="number" class="text-end" fluid v-model="form.Discount" size="small" />
                            </fieldset>
                            <fieldset>
                                <label>Tax</label>
                                <InputText type="number" class="text-end" fluid v-model="form.tax" size="small" />
                            </fieldset>
                            <fieldset>
                                <label>Shipping</label>
                                <InputText type="number" class="text-end" fluid v-model="form.priceshipping" size="small" />
                            </fieldset>
                            <fieldset>
                                <label>Refund</label>
                                <InputText type="number" class="text-end" fluid v-model="form.refund" size="small" />
                            </fieldset>
                            <Divider />
                            <fieldset>
                                <label>Unit Price</label>
                                <InputText type="text" class="text-end" fluid :value="formattedUnitPrice" size="small" readonly />
                            </fieldset>
                            <fieldset>
                                <label>Grand Total</label>
                                <InputText type="text" class="text-end bg-light fw-bold text-success" fluid :value="grandTotal" size="small" readonly />
                            </fieldset>
                        </template>
                    </Card>
                </div>

            </div>
        </div>

        <template #footer>
            <div class="d-flex gap-2 justify-content-end pt-2">
                <Button label="Cancel" severity="secondary" size="small" icon="pi pi-times" @click="closeModal" :disabled="saving" />
                <Button label="Save Order" severity="info" size="small" icon="pi pi-save" :loading="saving" @click="saveOrder" />
            </div>
        </template>
    </Dialog>
</template>

<script>
import Swal from "sweetalert2";
import { showPricingForPH } from "../../../utils/helpers.js";
import { Button, Card, Dialog, Divider, InputText, Select, Textarea } from "primevue";

export default {
    name: "AddOrderModal",
    components: { Button, Card, Dialog, Divider, InputText, Select, Textarea },
    props: {
        visible: { type: Boolean, default: false },
    },
    emits: ["update:visible", "order-added"],

    data() {
        return {
            saving: false,
            showPricingSection: showPricingForPH(),
            errors: {},
            isDragging: false,
            activePreviewIndex: 0,
            previewImages: [],   // base64 previews
            imageFiles: [],      // actual File objects

            form: this.defaultForm(),

            materialOptions: [
                { label: "Inventory",        value: "Inventory" },
                { label: "Supplies",         value: "Supplies" },
                { label: "Components",       value: "Components" },
                { label: "Office Equipment", value: "Office Equipment" },
            ],
            sourceTypeOptions: [
                { label: "ES",  value: "ES"  },
                { label: "AS",  value: "AS"  },
                { label: "XS",  value: "XS"  },
                { label: "PS",  value: "PS"  },
                { label: "RS",  value: "RS"  },
                { label: "B&H", value: "B&H" },
            ],
            listedConditionOptions: [
                { label: "New",                      value: "New" },
                { label: "Open Box",                 value: "Open Box" },
                { label: "Used",                     value: "Used" },
                { label: "For parts or not working", value: "For parts or not working" },
            ],
            paymentMethodOptions: [
                { label: "PayPal",            value: "PayPal" },
                { label: "Credit/Debit Card", value: "Credit/Debit Card" },
                { label: "Cash",              value: "Cash" },
                { label: "Bank Transfer",     value: "Bank Transfer" },
                { label: "Check",             value: "Check" },
            ],
        };
    },

    computed: {
        qty()           { return Number(this.form.quantity) || 0; },
        price()         { return Number(this.form.price) || 0; },
        discount()      { return Number(this.form.Discount) || 0; },
        tax()           { return Number(this.form.tax) || 0; },
        shipping()      { return Number(this.form.priceshipping) || 0; },
        refund()        { return Number(this.form.refund) || 0; },
        unitPrice()     { return this.qty > 0 ? this.price / this.qty : 0; },
        grandTotalRaw() { return (this.price - this.discount) + this.tax + this.shipping - this.refund; },
        formattedUnitPrice() { return this.unitPrice.toFixed(2); },
        grandTotal()    { return this.grandTotalRaw.toFixed(2); },
    },

    watch: {
        'form.listedcondition'(val) {
            this.form.itemstatus = val === 'For parts or not working'
                ? 'Not Working'
                : 'Working';
        },
    },

    methods: {
        defaultForm() {
            return {
                ProductTitle: "", rtid: "", itemnumber: "",
                orderdate: new Date().toISOString().split("T")[0],
                paymentdate: "", shipdate: "", estimated_deliverydate: "",
                seller: "", materialtype: "", sourceType: "",
                listedcondition: "", carrier: "", paymentmethod: "",
                serialnumber: "",
                trackingnumber: "", trackingnumber2: "", trackingnumber3: "", trackingnumber4: "",
                description: "", notes: "",
                quantity: 1, price: 0, Discount: 0, tax: 0, priceshipping: 0, refund: 0,
                itemstatus: "Working",
                ProductModuleLoc: "Orders",
                validation: "unvalidated",
            };
        },

        // ── Image handling ──────────────────────────────────────────
        onFileSelect(e) {
            this.addFiles(Array.from(e.target.files));
            e.target.value = ""; // reset so same file can be re-added
        },
        onDrop(e) {
            this.isDragging = false;
            this.addFiles(Array.from(e.dataTransfer.files).filter(f => f.type.startsWith("image/")));
        },
        addFiles(files) {
            const remaining = 15 - this.imageFiles.length;
            if (remaining <= 0) {
                this.errors.images = "Maximum 15 images allowed.";
                return;
            }
            files.slice(0, remaining).forEach(file => {
                this.imageFiles.push(file);
                const reader = new FileReader();
                reader.onload = e => this.previewImages.push(e.target.result);
                reader.readAsDataURL(file);
            });
            this.errors.images = null;
        },
        removeImage(idx) {
            this.imageFiles.splice(idx, 1);
            this.previewImages.splice(idx, 1);
            if (this.activePreviewIndex >= this.previewImages.length) {
                this.activePreviewIndex = Math.max(0, this.previewImages.length - 1);
            }
        },

        // ── Validation ──────────────────────────────────────────────
        validate() {
            this.errors = {};
            if (!this.form.ProductTitle?.trim()) this.errors.ProductTitle = "Product title is required.";
            if (!this.form.itemnumber?.trim())   this.errors.itemnumber   = "Item number is required.";
            return Object.keys(this.errors).length === 0;
        },

        // ── Save ────────────────────────────────────────────────────
        async saveOrder() {
            if (!this.validate()) return;
            this.saving = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute("content");

                // Use FormData so images + order fields go in ONE request
                const fd = new FormData();
                fd.append("_token", csrfToken);

                // Append all form fields
                Object.entries(this.form).forEach(([key, val]) => {
                    if (val !== null && val !== undefined) fd.append(key, val);
                });

                // Append images if any
                this.imageFiles.forEach((file, i) => {
                    fd.append(`images[${i}]`, file);
                });

                const res = await axios.post("/api/orders/products/store-with-images", fd, {
                    headers: { "Content-Type": "multipart/form-data" },
                });

                if (!res.data.success) throw new Error(res.data.message || "Save failed.");

                await Swal.fire({
                    icon: "success",
                    title: "Order Added!",
                    text: "The new order has been saved successfully.",
                    timer: 2000,
                    confirmButtonText: "OK",
                });

                this.$emit("order-added");
                this.closeModal();

            } catch (error) {
                console.error("Error saving order:", error);
                const msg = error.response?.data?.message
                    || error.response?.data?.errors
                    || "An error occurred. Please try again.";
                Swal.fire({ icon: "error", title: "Save Failed", text: JSON.stringify(msg), confirmButtonText: "OK" });
            } finally {
                this.saving = false;
            }
        },

        closeModal()  { this.$emit("update:visible", false); },
        resetForm() {
            this.form = this.defaultForm();
            this.errors = {};
            this.imageFiles = [];
            this.previewImages = [];
            this.activePreviewIndex = 0;
        },
    },
};
</script>

<style scoped>
/* Override the inherited form-grid-wrapper column sizing */
:deep(.edit-order-container) .form-col-center {
    max-width: 420px;
    flex: 0 0 420px;
}

.upload-dropzone {
    border: 2px dashed #ced4da;
    border-radius: 8px;
    padding: 1.25rem;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
}
.upload-dropzone:hover,
.upload-dropzone.drag-over {
    border-color: #0d6efd;
    background: #f0f5ff;
}
.thumbnail {
    position: relative;
    cursor: pointer;
}
.remove-thumb-btn {
    position: absolute;
    top: 2px;
    right: 2px;
    background: rgba(220, 53, 69, 0.85);
    border: none;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 0;
}
.remove-thumb-btn i {
    font-size: 0.55rem;
    color: white;
}
</style>