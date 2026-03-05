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
                    <div class="row">
                        <div class="col-md-3 border-end">
                            <div class="d-flex justify-content-between mt-4">
                                <h5>Settings</h5>
                               <div class="show-toggle" @click="toggleSettings">
                                    {{ showSettings ? 'Hide' : 'Show' }} Settings
                                </div>
                            </div>
                            <hr>
                    <div class="setting-form" :style="{ display: showSettings ? 'block' : 'none' }">
                        <div>
                            <h6>Set Warranty</h6>
                            <fieldset class="d-flex align-items-center justify-content-between gap-2">
                                <label>From: </label>
                                <InputText v-model="warrantyFrom" type="number" placeholder="Warranty" size="small" fluid />
                                <Select v-model="warrantyFromUnit" :options="[{label: 'Days', value: 'days'},{label: 'Years', value: 'years'}]" size="small" optionLabel="label" optionValue="value" />
                            </fieldset>
                            <fieldset class="d-flex align-items-center justify-content-between gap-2">
                                <label>To: </label>
                                <InputText v-model="warrantyTo" type="number" placeholder="Warranty" size="small" fluid />
                                <Select v-model="warrantyToUnit" :options="[{label: 'Days', value: 'days'},{label: 'Years', value: 'years'}]" size="small" optionLabel="label" optionValue="value" />
                            </fieldset>
                        </div>
                        <hr>
                        <div>
                            <h6>Set Header Information</h6>
                            <fieldset>
                                <label>Title</label>
                                <InputText v-model="title" placeholder="Title" size="small" fluid />
                            </fieldset>
                            <fieldset>
                                <label>Owner Website</label>
                                <InputText v-model="ownerWebsite" placeholder="Owner Website" size="small" fluid />
                            </fieldset>
                            <fieldset>
                                <label>Owner Email</label>
                                <InputText v-model="ownerEmail" placeholder="Owner Email" size="small" fluid />
                            </fieldset>
                            <fieldset>
                                <label>Owner Contact</label>
                                <InputText v-model="ownerContact" placeholder="Owner Contact" size="small" fluid />
                            </fieldset>
                            <fieldset>
                                <label>Owner Address</label>
                                <InputText v-model="ownerAddress" placeholder="Owner Address" size="small" fluid />
                            </fieldset>
                        </div>
                        <hr>
                        <div>
                            <h6>Set Tracking and Order Number</h6>
                            <fieldset>
                                <label for="">Tracking Number</label>
                                <InputText v-model="trackingNumber" placeholder="Tracking Number" size="small" fluid />
                            </fieldset>
                            <fieldset>
                                <label for="">Order Number</label>
                                <InputText v-model="orderNumber" placeholder="Order Number" size="small" fluid />
                            </fieldset>
                        </div>
                        <hr>
                        <div>
                            <h6>
                                Set Bill To Information
                            </h6>
                            <fieldset>
                                <label for="">Name</label>
                                <InputText v-model="billToName" placeholder="Name" size="small" fluid />
                            </fieldset>
                             <fieldset>
                                <label for="">Address 1</label>
                                <InputText v-model="billToAddress1" placeholder="Address 1" size="small" fluid />
                            </fieldset>
                             <fieldset>
                                <label for="">Address 2</label>
                                <InputText v-model="billToAddress2" placeholder="Address 2" size="small" fluid />
                            </fieldset>
                            <fieldset>
                                <label>Contact</label>
                                <InputText v-model="billToContact" placeholder="Contact" size="small" fluid />
                            </fieldset>
                        </div>
                        <hr>
                         <div>
                            <h6>
                                Set Ship To Information
                            </h6>
                            <fieldset>
                                <label for="">Name</label>
                                <InputText v-model="shipToName" placeholder="Name" size="small" fluid />
                            </fieldset>
                             <fieldset>
                                <label for="">Address 1</label>
                                <InputText v-model="shipToAddress1" placeholder="Address 1" size="small" fluid />
                            </fieldset>
                             <fieldset>
                                <label for="">Address 2</label>
                                <InputText v-model="shipToAddress2" placeholder="Address 2" size="small" fluid />
                            </fieldset>
                            <fieldset>
                                <label>Contact</label>
                                <InputText v-model="shipToContact" placeholder="Contact" size="small" fluid />
                            </fieldset>
                            <fieldset>
                                <label>Email</label>
                                <InputText v-model="shipToEmail" placeholder="Email" size="small" fluid />
                            </fieldset>
                        </div>
                    </div>
                           
                        </div>
                        <div class="col-md-9">
                            <div class="d-flex justify-content-center mt-4">
                                <div style="width: 100%; max-width: 700px;">
                                 <FirstTemplate
                                    ref="invoiceTemplate"
                                    :suppliers="selectedProducts"
                                    v-if="selectedTemplate === 'template1'"
                                    :warrantyFrom="warrantyFrom"
                                    :warrantyFromUnit="warrantyFromUnit"
                                    :warrantyTo="warrantyTo"
                                    :warrantyToUnit="warrantyToUnit"
                                    :title="title"
                                    :ownerWebsite="ownerWebsite"
                                    :ownerEmail="ownerEmail"
                                    :ownerContact="ownerContact"
                                    :ownerAddress="ownerAddress"
                                    :trackingNumber="trackingNumber"
                                    :orderNumber="orderNumber"
                                    :billToName="billToName"
                                    :billToAddress1="billToAddress1"
                                    :billToAddress2="billToAddress2"
                                    :billToContact="billToContact"
                                    :shipToName="shipToName"
                                    :shipToAddress1="shipToAddress1"
                                    :shipToAddress2="shipToAddress2"
                                    :shipToContact="shipToContact"
                                    :shipToEmail="shipToEmail"
                                />
                                    
<SecondTemplate
    ref="invoiceTemplate"
    :suppliers="selectedProducts"
    v-else-if="selectedTemplate === 'template2'"
    :warrantyFrom="warrantyFrom"
    :warrantyFromUnit="warrantyFromUnit"
    :warrantyTo="warrantyTo"
    :warrantyToUnit="warrantyToUnit"
    :title="title"
    :ownerWebsite="ownerWebsite"
    :ownerEmail="ownerEmail"
    :ownerContact="ownerContact"
    :ownerAddress="ownerAddress"
    :trackingNumber="trackingNumber"
    :orderNumber="orderNumber"
    :billToName="billToName"
    :billToAddress1="billToAddress1"
    :billToAddress2="billToAddress2"
    :billToContact="billToContact"
    :shipToName="shipToName"
    :shipToAddress1="shipToAddress1"
    :shipToAddress2="shipToAddress2"
    :shipToContact="shipToContact"
    :shipToEmail="shipToEmail"
/>
    <ThirdTemplate  
        ref="invoiceTemplate"
    :suppliers="selectedProducts"
    v-else-if="selectedTemplate === 'template3'"
    :warrantyFrom="warrantyFrom"
    :warrantyFromUnit="warrantyFromUnit"
    :warrantyTo="warrantyTo"
    :warrantyToUnit="warrantyToUnit"
    :title="title"
    :ownerWebsite="ownerWebsite"
    :ownerEmail="ownerEmail"
    :ownerContact="ownerContact"
    :ownerAddress="ownerAddress"
    :trackingNumber="trackingNumber"
    :orderNumber="orderNumber"
    :billToName="billToName"
    :billToAddress1="billToAddress1"
    :billToAddress2="billToAddress2"
    :billToContact="billToContact"
    :shipToName="shipToName"
    :shipToAddress1="shipToAddress1"
    :shipToAddress2="shipToAddress2"
    :shipToContact="shipToContact"
    :shipToEmail="shipToEmail"
                                    />
                                    <FourthTemplate  ref="invoiceTemplate"
    :suppliers="selectedProducts"
    v-else-if="selectedTemplate === 'template4'"
    :warrantyFrom="warrantyFrom"
    :warrantyFromUnit="warrantyFromUnit"
    :warrantyTo="warrantyTo"
    :warrantyToUnit="warrantyToUnit"
    :title="title"
    :ownerWebsite="ownerWebsite"
    :ownerEmail="ownerEmail"
    :ownerContact="ownerContact"
    :ownerAddress="ownerAddress"
    :trackingNumber="trackingNumber"
    :orderNumber="orderNumber"
    :billToName="billToName"
    :billToAddress1="billToAddress1"
    :billToAddress2="billToAddress2"
    :billToContact="billToContact"
    :shipToName="shipToName"
    :shipToAddress1="shipToAddress1"
    :shipToAddress2="shipToAddress2"
    :shipToContact="shipToContact"
    :shipToEmail="shipToEmail"/>
                                    <FifthTemplate   :suppliers="selectedProducts"
    v-else-if="selectedTemplate === 'template5'"
    :warrantyFrom="warrantyFrom"
    :warrantyFromUnit="warrantyFromUnit"
    :warrantyTo="warrantyTo"
    :warrantyToUnit="warrantyToUnit"
    :title="title"
    :ownerWebsite="ownerWebsite"
    :ownerEmail="ownerEmail"
    :ownerContact="ownerContact"
    :ownerAddress="ownerAddress"
    :trackingNumber="trackingNumber"
    :orderNumber="orderNumber"
    :billToName="billToName"
    :billToAddress1="billToAddress1"
    :billToAddress2="billToAddress2"
    :billToContact="billToContact"
    :shipToName="shipToName"
    :shipToAddress1="shipToAddress1"
    :shipToAddress2="shipToAddress2"
    :shipToContact="shipToContact"
    :shipToEmail="shipToEmail""/>
                                </div>
                            </div>
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
import { Dialog, Skeleton, Button, Card, InputText, Select } from 'primevue';
import FirstTemplate  from '../../components/invoiceTemplate/FirstTemplate.vue';
import SecondTemplate from '../../components/invoiceTemplate/SecondTemplate.vue';
import ThirdTemplate  from '../../components/invoiceTemplate/ThirdTemplate.vue';
import FourthTemplate from '../../components/invoiceTemplate/FourthTemplate.vue';
import FifthTemplate  from '../../components/invoiceTemplate/FifthTemplate.vue';

export default {
    name: 'ProductInvoiceModal',
    components: {
        Dialog, Skeleton, Button, Card, InputText,Select,
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
        warrantyFrom: 90,
        warrantyTo: 1,
        warrantyFromUnit: 'days',
        warrantyToUnit: 'years',
        ownerAddress: "4620 Northgate Blvd., Ste 180, Sacramento, CA 95834",
        ownerContact: "(415) 882-6949",
        ownerEmail: "sales@allrenewed.com",
        ownerWebsite: "www.allrenewed.com",
        title: "ALL RENEWED ELECTRONICS",
        showSettings: true,

        // Tracking & Order
        trackingNumber: '',
        orderNumber: '',

        // Bill To
        billToName: 'Julius Sanchez',
        billToAddress1: 'PO Box 1907',
        billToAddress2: 'North Highlands CA 95660',
        billToContact: '(916) 370-5657',

        // Ship To
        shipToName: '',
        shipToAddress1: '',
        shipToAddress2: '',
        shipToContact: '',
        shipToEmail: '',
    };
    },
    methods: {
       toggleSettings() {
            this.showSettings = !this.showSettings;
        },
        async getProductInvoice() {
            this.isLoading = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                const response = await axios.get('/api/product-invoice', {
                    params: { productIds: this.productIds },
                    headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                });
                this.selectedProducts = response.data;
                console.log(this.selectedProducts, "selectedProducts");
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
 async downloadInvoice() {
    this.isDownloading = true;
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const response = await axios.post(
            '/api/product-invoice/generate-invoice-pdf',
            {
                suppliers:        this.selectedProducts,
                selectedTemplate: this.selectedTemplate,
                warrantyFrom:     this.warrantyFrom,
                warrantyFromUnit: this.warrantyFromUnit,
                warrantyTo:       this.warrantyTo,
                warrantyToUnit:   this.warrantyToUnit,
                title:            this.title,
                ownerWebsite:     this.ownerWebsite,
                ownerEmail:       this.ownerEmail,
                ownerContact:     this.ownerContact,
                ownerAddress:     this.ownerAddress,
                trackingNumber:   this.trackingNumber,
                orderNumber:      this.orderNumber,
                billToName:       this.billToName,
                billToAddress1:   this.billToAddress1,
                billToAddress2:   this.billToAddress2,
                billToContact:    this.billToContact,
                shipToName:       this.shipToName,
                shipToAddress1:   this.shipToAddress1,
                shipToAddress2:   this.shipToAddress2,
                shipToContact:    this.shipToContact,
                shipToEmail:      this.shipToEmail,
            },
            {
                headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                responseType: 'blob',
            }
        );

        const url = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
        const a = document.createElement('a');
        a.href = url;
        a.download = `invoice-${Date.now()}.pdf`;
        a.click();
        URL.revokeObjectURL(url);

    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Download Failed', text: e.message });
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

.show-toggle {
    display: none;
}

@media screen and (max-width: 768px) {
    .show-toggle {
        display: block;
        color: rgb(87, 171, 255);
        font-size: 14px;
        font-weight: bold;
    }
}
</style>