<template>
    <Dialog :visible="visible" @update:visible="$emit('close')" header="Print Documents" :modal="true"
        :style="{ width: '100%', maxWidth: '600px' }" :breakpoints="{ '960px': '75vw', '640px': '90vw' }">
        <TabView v-model:activeIndex="activeTabIndex" class="w-full">
            <TabPanel header="Invoice" leftIcon="pi pi-file-pdf">
                <div class="flex flex-column gap-4">
                    <div class="mb-4 surface-ground border-round">
                        <p class="text-base m-0">
                            <span class="block mb-2">Are you sure you want to print the invoice for</span>
                            <strong class="text-lg text-primary">
                                Order ID: {{ order?.platform_order_id }}
                            </strong>
                            <span class="block text-sm text-surface-600 mt-1">
                                Store: {{ order?.storename || "N/A" }}
                            </span>
                        </p>
                    </div>

                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex align-items-center gap-2">
                            <ToggleSwitch v-model="displayPrice" onLabel="On" offLabel="Off" />
                            <label class="font-semibold cursor-pointer">Display Price</label>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <ToggleSwitch v-model="testPrint" onLabel="On" offLabel="Off" />
                            <label class="font-semibold cursor-pointer">Test Print</label>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <ToggleSwitch v-model="signatureRequired" onLabel="On" offLabel="Off" />
                            <label class="font-semibold cursor-pointer">Signature Required</label>
                        </div>
                    </div>

                    <div class="button-group mt-4">
                        <Button label="Edit Price" icon="pi pi-pencil" severity="info" size="small" />
                        <Button label="View PDF" icon="pi pi-eye" severity="warning" size="small"
                            @click="handleInvoiceAction('ViewInvoice')" />
                        <Button label="Print" icon="pi pi-print" severity="success" size="small" :loading="loading"
                            @click="handleInvoiceAction('PrintInvoice')" />
                    </div>
                </div>
            </TabPanel>

            <TabPanel header="Shipping Label" leftIcon="pi pi-box">
                <div class="d-flex flex-column gap-4">
                    <div class="field">
                        <InputText id="label-note" fluid size="small" v-model="note" placeholder="Enter label note"
                            class="w-full" />
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <ToggleSwitch v-model="testPrint" />
                        <label class="font-semibold cursor-pointer">Test Print</label>
                    </div>

                    <div class="button-group pt-2">
                        <Button label="View Shipping Label" icon="pi pi-eye" severity="info" size="small"
                            @click="handleShippingLabelAction('ViewShipmentLabel')" />
                        <Button label="Print Shipping Label" icon="pi pi-print" severity="success" size="small"
                            @click="handleShippingLabelAction('PrintShipmentLabel')" />
                    </div>
                </div>
            </TabPanel>
        </TabView>

        <template #footer>
            <Button label="Close" icon="pi pi-times" severity="secondary" @click="closeModal" />
        </template>
    </Dialog>
</template>

<script>
import Dialog from 'primevue/dialog';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import ToggleSwitch from 'primevue/toggleswitch';

const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    components: {
        Dialog,
        TabView,
        TabPanel,
        Button,
        InputText,
        ToggleSwitch,
    },
    props: {
        visible: Boolean,
        order: Object,
    },
    data() {
        return {
            activeTabIndex: 0,
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
                this.loading = true;
                const res = await axios.post(
                    `${API_BASE_URL}/fbm-orders-invoice`,
                    payload,
                    {
                        headers: {
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                        },
                    }
                );

                if (res.data.success) {
                    if (action === "ViewInvoice" && res.data.results?.length) {
                        const pdfUrl = res.data.results[0].pdf_url;
                        if (pdfUrl) {
                            const a = document.createElement("a");
                            a.href = pdfUrl;
                            a.target = "_blank";
                            a.rel = "noopener noreferrer";
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                        } else {
                            this.$toast.add({ severity: 'error', summary: 'Error', detail: 'PDF not available.', life: 3000 });
                        }
                    } else if (action === "PrintInvoice") {
                        this.$toast.add({ severity: 'success', summary: 'Success', detail: 'Invoice sent to printer!', life: 3000 });
                    }
                } else {
                    this.$toast.add({ severity: 'error', summary: 'Failed', detail: res.data.message || 'Unknown error.', life: 3000 });
                }
            } catch (err) {
                console.error(err);
                this.$toast.add({ severity: 'error', summary: 'Error', detail: 'Error occurred while processing invoice.', life: 3000 });
            } finally {
                this.loading = false;
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
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                        },
                    }
                );

                if (res.data.success) {
                    if (action === "ViewShipmentLabel") {
                        const pdfUrl = res.data.results?.[0]?.pdf_url;
                        if (pdfUrl) {
                            const a = document.createElement("a");
                            a.href = pdfUrl;
                            a.target = "_blank";
                            a.rel = "noopener noreferrer";
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                        } else {
                            this.$toast.add({ severity: 'error', summary: 'Error', detail: 'PDF not available.', life: 3000 });
                        }
                    } else {
                        this.$toast.add({ severity: 'success', summary: 'Success', detail: 'Shipping label sent to printer!', life: 3000 });
                    }
                } else {
                    this.$toast.add({ severity: 'error', summary: 'Failed', detail: res.data.message || 'Unknown error.', life: 3000 });
                }
            } catch (err) {
                console.error(err);
                this.$toast.add({ severity: 'error', summary: 'Error', detail: 'Error occurred while processing shipping label.', life: 3000 });
            }
        },
    },
};
</script>

<style scoped>
:deep(.p-dialog) {
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
}

:deep(.p-tabview) {
    background: transparent;
}

:deep(.p-tabview .p-tabview-nav) {
    background: transparent;
    border-bottom: 2px solid var(--surface-border);
}

:deep(.p-tabview .p-tabview-panels) {
    background: transparent;
    padding: 1.5rem 0;
}

:deep(.p-button) {
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.2s ease-in-out;
}

:deep(.p-button:hover) {
    transform: translateY(-2px);
}

:deep(.p-toggleswitch) {
    scale: 1.1;
}

.button-group {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.button-group :deep(.p-button) {
    flex: 1;
    min-width: 100px;
}

@media (max-width: 640px) {
    .button-group {
        flex-direction: column;
    }

    .button-group :deep(.p-button) {
        width: 100%;
    }
}
</style>