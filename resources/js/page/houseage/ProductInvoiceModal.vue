<template>
    <Dialog
        v-model:visible="isVisible"
        modal
        header="Product Invoice"
        :style="{ width: '90vw', maxWidth: '95vw' }"
        :breakpoints="{ '960px': '90vw', '640px': '100vw' }"
        :closable="true"
        @update:visible="handleClose"
        :pt="{ root: { class: 'mobile-fullscreen-dialog' } }"
    >
        <!-- Loading -->
        <div v-if="isLoading">
            <div class="row">
                <div class="col-3">
                    <Skeleton class="mb-2" />
                    <Skeleton width="10rem" class="mb-2" />
                    <Skeleton width="5rem" class="mb-2" />
                </div>
                <div class="col-9">
                    <Skeleton class="mb-2" />
                    <Skeleton width="10rem" class="mb-2" />
                    <Skeleton width="5rem" class="mb-2" />
                </div>
            </div>
        </div>

        <!-- Content -->
        <div v-else>
            <div class="row">
                <!-- Left sidebar -->
                <!-- <div class="col-md-3">
                    <h5>Selected Items</h5>
                    <hr>
                    <Card v-for="(product, index) in selectedProducts" :key="index" class="mb-4">
                        <template #title>
                            <div class="product-title-invoice">{{ product.name }}</div>
                        </template>
                        <template #content>
                            <div class="seller-info-container">
                                <div class="seller-info-label">Contact:</div>
                                <div class="seller-info-value">{{ product.contact }}</div>
                            </div>
                            <div class="seller-info-container" v-if="product.address1">
                                <div class="seller-info-label">Address1:</div>
                                <div class="seller-info-value">{{ product.address1 }}</div>
                            </div>
                            <div class="seller-info-container" v-if="product.address2">
                                <div class="seller-info-label">Address2:</div>
                                <div class="seller-info-value">{{ product.address2 }}</div>
                            </div>
                            <div class="seller-info-container">
                                <div class="seller-info-label">Email:</div>
                                <div class="seller-info-value">{{ product.email }}</div>
                            </div>
                            <div class="seller-info-container">
                                <div class="seller-info-label">Website Address:</div>
                                <div class="seller-info-value">{{ product.websiteAddress }}</div>
                            </div>
                            <hr>
                            <div>
                                <h6>Product/s</h6>
                                <div v-for="(p, i) in product.products" :key="i" class="product-container mb-2">
                                    <div class="product-title-invoice">{{ p.ProductTitle }}</div>
                                    <div class="product-info-container">
                                        <div class="product-info-label">Price:</div>
                                        <div class="product-info-value">{{ p.price }}</div>
                                    </div>
                                    <div class="product-info-container">
                                        <div class="product-info-label">Quantity:</div>
                                        <div class="product-info-value">{{ p.quantity }}</div>
                                    </div>
                                    <div class="product-info-container">
                                        <div class="product-info-label">Total:</div>
                                        <div class="product-info-value">{{ p.totalPrice }}</div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Card>
                </div> -->

                <!-- Right: template viewer -->
                <div>
                    <div class="d-flex justify-content-center">
                        <Button
                            v-for="t in templateOptions"
                            :key="t.value"
                            :label="t.label"
                            @click="selectedTemplate = t.value"
                            size="small"
                            :severity="selectedTemplate === t.value ? 'primary' : 'secondary'"
                            class="me-2"
                            :outlined="selectedTemplate !== t.value"
                        />
                    </div>

                     <div class="d-flex justify-content-center mt-4">
                        <div style="width: 100%; max-width: 700px;">
                            <FirstTemplate  ref="invoiceTemplate" :suppliers="selectedProducts" v-if="selectedTemplate === 'template1'" />
                            <SecondTemplate ref="invoiceTemplate" :suppliers="selectedProducts" v-else-if="selectedTemplate === 'template2'" />
                            <ThirdTemplate  ref="invoiceTemplate" :suppliers="selectedProducts" v-else-if="selectedTemplate === 'template3'" />
                            <FourthTemplate ref="invoiceTemplate" :suppliers="selectedProducts" v-else-if="selectedTemplate === 'template4'" />
                            <FifthTemplate  ref="invoiceTemplate" :suppliers="selectedProducts" v-else-if="selectedTemplate === 'template5'" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <template #footer>
            <div class="d-flex gap-2" v-show="!isLoading">
                <Button label="Close" severity="danger" icon="pi pi-times" @click="handleClose" size="small" />
                <Button label="Download Invoice" severity="success" icon="pi pi-download" size="small" @click="downloadInvoice" :loading="isDownloading" />
            </div>
        </template>
    </Dialog>
</template>

<script>
import { Dialog, Skeleton, Button, Card } from 'primevue';
import FirstTemplate  from '../../components/invoiceTemplate/FirstTemplate.vue';
import SecondTemplate from '../../components/invoiceTemplate/SecondTemplate.vue';
import ThirdTemplate  from '../../components/invoiceTemplate/ThirdTemplate.vue';
import FourthTemplate from '../../components/invoiceTemplate/FourthTemplate.vue';
import FifthTemplate  from '../../components/invoiceTemplate/FifthTemplate.vue';

export default {
    name: 'ProductInvoiceModal',
    components: {
        Dialog, Skeleton, Button, Card,
        FirstTemplate, SecondTemplate, ThirdTemplate, FourthTemplate, FifthTemplate,
    },
    props: {
        productIds: { type: Array, required: true },
        visible:    { type: Boolean, required: true },
    },
    emits: ['update:visible', 'close'],
    data() {
        return {
            isVisible: this.visible,
            isLoading: false,
            isDownloading: false,
            selectedProducts: [],
            selectedTemplate: 'template1',
            templateOptions: [
                { label: 'Template 1', value: 'template1' },
                { label: 'Template 2', value: 'template2' },
                { label: 'Template 3', value: 'template3' },
                { label: 'Template 4', value: 'template4' },
                { label: 'Template 5', value: 'template5' },
            ],
        };
    },
    methods: {
        async getProductInvoice() {
            this.isLoading = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                const response = await axios.get('/api/product-invoice', {
                    params: { productIds: this.productIds },
                    headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                });
                this.selectedProducts = response.data;
            } catch (error) {
                this.$emit('update:visible', false);
                Swal.fire({
                    icon: 'error',
                    title: 'Error...',
                    text: error.response.data.message,
                });
            } finally {
                this.isLoading = false;
            }
        },
        async printInvoice() {
            const tpl = this.$refs.invoiceTemplate;
            if (!tpl) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Template not found.' });
                return;
            }
            this.isDownloading = true;
            try {
                const { default: html2canvas } = await import('html2canvas');
                const { default: jsPDF } = await import('jspdf');
                const pdf = new jsPDF('p', 'mm', 'a4');
                const pw = pdf.internal.pageSize.getWidth();

                for (let i = 0; i < this.selectedProducts.length; i++) {
                    // Switch to this supplier
                    tpl.currentIndex = i;
                    await this.$nextTick();

                    // Small delay to allow DOM to update/scale
                    await new Promise(r => setTimeout(r, 150));

                    const el = tpl.$refs.invoiceRef;
                    if (!el) continue;

                    const canvas = await html2canvas(el, { scale: 2, useCORS: true });
                    const imgData = canvas.toDataURL('image/png');
                    const ph = (canvas.height * pw) / canvas.width;

                    if (i > 0) pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 0, 0, pw, ph);
                }

                pdf.save(`invoice-${Date.now()}.pdf`);
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Print Failed', text: e.message });
            } finally {
                this.isDownloading = false;
            }
        },
        handleClose() {
            this.isVisible = false;
            this.$emit('update:visible', false);
        },
    },
    watch: {
        visible(val) {
            this.isVisible = val;
            if (val) {
                this.selectedTemplate = 'template1';
                this.getProductInvoice();
            }
        },
    },
};
</script>

<style scoped>
.product-title-invoice {
    font-weight: bold;
    margin-bottom: .5rem;
    font-size: .8rem;
}

.seller-info-container,
.product-info-container {
    display: flex;
    flex-direction: row;
    gap: .5rem;
}

.seller-info-label,
.product-info-label {
    font-weight: bold;
    margin-bottom: .5rem;
    font-size: .8rem;
}

.seller-info-value,
.product-info-value {
    margin-bottom: .5rem;
    font-size: .8rem;
}

.product-container {
    background-color: #f9fafb;
    padding: .5rem;
    border-radius: .7rem;
    display: flex;
    flex-direction: column;
    align-items: start;
}
</style>