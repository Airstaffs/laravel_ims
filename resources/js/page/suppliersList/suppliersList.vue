<template>
    <div class="vue-container">
        <div class="ms-4 mt-4">
            <Button @click="backToHouseage" severity="secondary" label="Back to Houseage" size="small"
                icon="pi pi-arrow-left" />
        </div>
        <TitlePage title="Suppliers List" />

        <AnimateDiv :delay="200" class="p-4">
            <XDataTable :columns="columns" :value="suppliers" :loading="loading" tableClass="desktop-view"
                :paginator="false">
                <template #name="{ data }">
                    <span class="fw-semibold">{{ data.name }}</span>
                </template>
                <template #contact="{ data }">
                    <span>{{ data.contact || "--" }}</span>
                </template>
                <template #address1="{ data }">
                    <span>{{ data.address1 || "--" }}</span>
                </template>
                <template #address2="{ data }">
                    <span>{{ data.address2 || "--" }}</span>
                </template>
                <template #email="{ data }">
                    <span>{{ data.email || "--" }}</span>
                </template>
                <template #websiteAddress="{ data }">
                    <span>{{ data.websiteAddress || "--" }}</span>
                </template>
                <template #actions="{ data }">
                    <Button size="small" severity="contrast" variant="text" icon="pi pi-pencil" label="Edit"
                        class="text-primary" @click="handleOpenEditModal(data)" />
                </template>
            </XDataTable>

            <!--Mobile View--->
            <div class="mobile-view">
                <div v-if="loading"></div>
                <Card v-else v-for="supplier in suppliers" :key="supplier.id" class="card-supplier" >
                    <template #title>
                        <div class="mobile-supplier-name">{{ supplier.name }}</div>
                    </template>
                    <template #content>
                       <div class="d-flex flex-column">
                            <span class="mobile-label">Supplier Contact:</span>
                            <span class="mobile-value">{{ supplier.contact || "--" }}</span>
                       </div>
                       <div class="d-flex flex-column">
                            <span class="mobile-label">Supplier Address1:</span>
                            <span class="mobile-value">{{ supplier.address1 || "--" }}</span>
                       </div>
                        <div class="d-flex flex-column">
                            <span class="mobile-label">Supplier Address2:</span>
                            <span class="mobile-value">{{ supplier.address2 || "--" }}</span>
                       </div>
                        <div class="d-flex flex-column">
                            <span class="mobile-label">Email Address:</span>
                            <span class="mobile-value">{{ supplier.email || "--" }}</span>
                       </div>
                       <div class="d-flex flex-column">
                            <span class="mobile-label">Website Address:</span>
                            <span class="mobile-value">{{ supplier.websiteAddress || "--" }}</span>
                       </div>
                       <hr>
                       <div class="d-flex flex-column">
                            <Button size="small" severity="contrast" variant="text" icon="pi pi-pencil" label="Edit"
                                class="text-primary" @click="handleOpenEditModal(supplier)" />
                       </div>
                    </template>
                </Card>
            </div>

            <Paginator :first="first" :rows="perPage" :total-records="totalRecords"
                :rows-per-page-options="[10, 20, 50]"
                template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
                currentPageReportTemplate="Showing {first} to {last} of {totalRecords}" class="small-paginator"
                @page="onPageChange"
            />
        </AnimateDiv>

        <Dialog
            v-model:visible="openEditModal"
            :modal="true"
            header="Edit Supplier"
            :style="{ width: '80vw' }"
            :pt="{
                root: { class: 'mobile-fullscreen-dialog' },
            }"
        >
            <div>
                <div class="supplier-name-container">
                    <span>Supplier Name:</span>
                    <span class="supplier-name">{{ supplierData.name }}</span>
                </div>

                <div class="form-container">
                    <div class="form-field">
                        <label for="contact">Supplier Contact</label>
                        <InputText id="contact" v-model="supplierData.contact"
                            placeholder="Enter Supplier Contact" size="small" class="w-full" />
                    </div>

                    <div class="form-field">
                        <label for="address1">Supplier Address 1</label>
                        <InputText id="address1" v-model="supplierData.address1"
                            placeholder="Enter Supplier Address" size="small" class="w-full" />
                    </div>

                    <div class="form-field">
                        <label for="address2">Supplier Address 2</label>
                        <InputText id="address2" v-model="supplierData.address2"
                            placeholder="Enter Supplier Address 2" size="small" class="w-full" />
                    </div>

                    <div class="form-field">
                        <label for="email">Supplier Email</label>
                        <InputText id="email" v-model="supplierData.email"
                            placeholder="Enter Supplier Email" size="small" class="w-full" />
                    </div>

                    <div class="form-field form-field--full">
                        <label for="websiteAddress">Supplier Website Address</label>
                        <InputText id="websiteAddress" v-model="supplierData.websiteAddress"
                            placeholder="Enter Supplier Website Address" size="small" class="w-full" />
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="openEditModal = false" severity="danger" size="small" :disabled="isUpdating"/>
                <Button :label="isUpdating ? 'Updating...' : 'Save'" :icon="isUpdating ? 'pi pi-spin pi-spinner' : 'pi pi-check'" @click="handleUpdateSupplier" severity="success" size="small" :disabled="isUpdating"/>
            </template>
        </Dialog>
    </div>
</template>

<script>
import TitlePage from '../../components/TitlePage/TitlePage.vue';
import { Button, Paginator, Dialog, InputText, Card } from 'primevue';
import XDataTable from '../../components/DataTable/XDataTable.vue';
import AnimateDiv from '../../components/AnimationDiv/AnimateDiv.vue';
import SuppliersList from './suppliersList.js';

const TABLE_COLUMNS = [
    { slot: "name",           header: "Supplier Name",     headerStyle: "font-size: 16px;", bodyStyle: { fontSize: "14px" } },
    { slot: "contact",        header: "Supplier Contact",  headerStyle: "font-size: 16px;", bodyStyle: { fontSize: "14px" } },
    { slot: "address1",       header: "Supplier Address 1",  headerStyle: "font-size: 16px;", bodyStyle: { fontSize: "14px" } },
    { slot: "address2",       header: "Supplier Address 2",headerStyle: "font-size: 16px;", bodyStyle: { fontSize: "14px" } },
    { slot: "email",          header: "Email Address",     headerStyle: "font-size: 16px;", bodyStyle: { fontSize: "14px" } },
    { slot: "websiteAddress", header: "Website Address",   headerStyle: "font-size: 16px;", bodyStyle: { fontSize: "14px" } },
];

export default {
    mixins: [SuppliersList],
    components: { TitlePage, Button, XDataTable, Paginator, Dialog, InputText, Card, AnimateDiv },
    data() {
        return { columns: TABLE_COLUMNS };
    },
    methods: {
        backToHouseage() {
            window.loadContent('houseage');
        }
    }
}
</script>

<style scoped>
button {
    cursor: pointer;
}

.supplier-name-container {
    background: linear-gradient(135deg, #0056b3 0%, #007bff 100%);
    display: flex;
    align-items: center;
    gap: 1rem;
    color: white;
    padding: 1rem;
    border-radius: 5px;
    font-size: 20px;
}

.supplier-name {
    font-weight: bold;
}

.form-container {
    margin-top: 1rem;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.form-field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

/* Website address spans full width since it's the 5th item (odd one out) */
.form-field--full {
    grid-column: 1 / -1;
}

label {
    font-weight: bold;
}

@media (max-width: 768px) {
    .supplier-name-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .form-container {
        grid-template-columns: 1fr;
    }

    .form-field--full {
        grid-column: 1;
    }

    .card-supplier {
        margin-bottom: 1rem;
        width: 100%;
    }
    .mobile-supplier-name {
        background: linear-gradient(135deg, #0056b3 0%, #007bff 100%);
        color: white;
        padding: .5rem;
        border-radius: .5rem;
        font-size: 1rem;
        width: 100%;
        margin-bottom: 1rem;
    }
    .mobile-label {
        font-size: .8rem;
        font-weight: bold;
    }
    .mobile-value {
        font-weight: normal;
    }
}
</style>