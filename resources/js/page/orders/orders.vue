<template>
    <div class="vue-container orders-module">
        <TitlePage title="Order Module"
            subtitle="View and manage all current and past shipment orders, including tracking information and status." />

        <!-- Desktop Table Container -->
        <AnimateDiv :delay="200" class="px-4">
            <XDataTable :value="sortedInventory" :loading="loading" :columns="columns" :paginator="false"
                selectionMode="multiple" selection="multiple" tableClass="desktop-view" dataKey="ProductID">
                <template #gallery="{ data }">
                    <div class="d-flex justify-content-center align-items-center">
                        <TableGallery :data="data" :openImageModal="openImageModal" :handleImageError="handleImageError"
                            :countAdditionalImages="countAdditionalImages" />
                    </div>
                </template>
                <template #ProductTitle="{ data }">
                    <div class="d-flex align-items-start gap-4">
                        <div style="word-break: break-word; white-space: normal; overflow-wrap: break-word; flex: 1;">
                            <p style="font-size: .8rem;">RT# {{ data.rtcounter }}</p>
                            <p class="fw-semibold">
                                {{ data.ProductTitle }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #status="{ data }">
                    <Badge :severity="{
                        Working: 'success',
                        Pending: 'warning'
                    }[data.itemstatus] || 'secondary'" :value="data.itemstatus" />


                </template>

                <template #actions="{ data }">
                    <Button size="small" severity="contrast" class="text-primary" variant="text" icon="pi pi-pencil"
                        @click="openEditModal(data)" label="Edit" />
                </template>
            </XDataTable>
        </AnimateDiv>

        <!-- Mobile Cards View -->
        <div class="mobile-view">
            <div class="mobile-cards">
                <div v-if="loading" class="loading-spinner-mobile">
                    <i class="fas fa-spinner fa-spin"></i>
                    Loading...
                </div>
                <div v-else-if="sortedInventory.length === 0" class="no-data-mobile">
                    No data found
                </div>
                <div class="mobile-card" v-else v-for="(item, index) in sortedInventory" :key="item.id">
                    <div class="mobile-card-header">
                        <div class="mobile-checkbox">
                            <input type="checkbox" v-model="item.checked" />
                        </div>
                        <TableGallery :data="item" :openImageModal="openImageModal" :handleImageError="handleImageError"
                            :countAdditionalImages="countAdditionalImages" />
                        <div class="mobile-product-info">
                            <h5 class="mobile-product-name clickable">
                                <span style="font-size: 1rem;">RT# : {{ item.rtcounter }}</span>
                                <span>{{ item.ProductTitle }}</span>
                            </h5>
                        </div>
                    </div>

                    <hr />
                    <div class="mobile-card-details">
                        <div class="mobile-detail-row mb-2">
                            <span class="mobile-detail-label">Seller Location:</span>
                            <span class="mobile-detal-value">
                                {{ item.Ebay_seller_location }}</span>
                        </div>
                        <div class="mobile-detail-row  mb-2">
                            <span class="mobile-detail-label">Tracking Number:</span>
                            <span class="mobile-detal-value">
                                {{ item.trackingnumber }}</span>
                        </div>
                        <div class="mobile-detail-row  mb-2">
                            <span class="mobile-detail-label">Ordered Condition:</span>
                            <span class="mobile-detal-value">
                                {{ item.listedcondition }}</span>
                        </div>
                        <div class="mobile-detail-row  mb-2">
                            <span class="mobile-detail-label">Condition Status:</span>
                            <span class="mobile-detal-value">
                                <Badge :severity="{
                                    Working: 'success',
                                    Pending: 'warning'
                                }[item.itemstatus] || 'secondary'" :value="item.itemstatus" />
                            </span>
                        </div>
                        <div class="mobile-detail-row  mb-2">
                            <span class="mobile-detail-label">Ordered Date:</span>
                            <span class="mobile-detal-value">
                                {{ item.orderdate }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Delivered Date:</span>
                            <span class="mobile-detal-value">
                                {{ item.datedelivered }}</span>
                        </div>
                    </div>

                    <hr />

                    <div class="mobile-card-actions">
                        <Button severity="info" @click="openEditModal(item)" icon="pi pi-pencil" label="Edit"
                            size="small" style="width: 100%;" />
                    </div>

                    <hr v-if="expandedRows[index]" />

                    <div v-if="expandedRows[index]" class="mobile-expanded-content">
                        <p><strong>Expanded Rows Here</strong></p>
                        <p><strong>Product Name:</strong> {{ item.AStitle }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination with centered layout -->
        <div class="pagination-container">
            <div class="pagination-wrapper">
                <div class="per-page-selector">
                    <span>Rows per page</span>
                    <select v-model="perPage" @change="changePerPage" class="per-page-select">
                        <option v-for="option in [10, 15, 20, 50, 100]" :key="option" :value="option">
                            {{ option }}
                        </option>
                    </select>
                </div>

                <div class="pagination">
                    <button @click="prevPage" :disabled="currentPage === 1" class="pagination-button">
                        <i class="fas fa-chevron-left"></i> Back
                    </button>
                    <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
                    <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button">
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Image Modal -->
        <ViewImageModal v-model:visible="showImageModal" :title="ProductTitle" :imageList="imageList"
            :basePath="basePath" :onImageErrorMain="onImageErrorMain" :onThumbnailError="onThumbnailError"
            @close="closeImageModal" />
        <Dialog v-model:visible="showEditModal" modal :style="{ width: '90%' }" :pt="{
            root: { class: 'mobile-fullscreen-dialog' }
        }">
            <template #header>
                <h5>{{ `RT ${item.rtcounter} - ${item.ProductTitle}` }}</h5>
            </template>
            <div class="edit-order-container">
                <form method="POST" class="editOrderForm">
                    <div class="form-grid-wrapper">
                        <!-- LEFT: IMAGE + GENERAL INFO -->
                        <div class="form-col-left">
                            <div class="image-section" v-if="imageList.length">
                                <!-- Main Image -->
                                <div class="main-image">
                                    <img :src="activeImageUrl" alt="Main Product Image" loading="lazy"
                                        @error="onImageErrorMain" />
                                </div>

                                <!-- Thumbnails -->
                                <div class="thumbnail-carousel">
                                    <div v-for="(
img, index
                                                ) in imageList" :key="index" :class="[
                                                    'thumbnail',
                                                    {
                                                        active:
                                                            index ===
                                                            activeIndex,
                                                    },
                                                ]" @click="activeIndex = index" @mouseenter="
                                                    activeIndex = index
                                                    ">
                                        <img :src="basePath + img" alt="Thumbnail" loading="lazy" @error="
                                            onThumbnailError($event)
                                            " />
                                    </div>
                                </div>
                            </div>
                            <Card>
                                <template #title>
                                    <div>
                                        <h6 class="text-primary">General Information</h6>
                                        <Divider />
                                    </div>
                                </template>
                                <template #content>
                                    <div>
                                        <fieldset>
                                            <label>External Title</label>
                                            <Textarea size="small" fluid v-model="item.ProductTitle" rows="5" />
                                        </fieldset>
                                        <fieldset>
                                            <label>RT:</label>
                                            <InputText size="small" v-model="item.rtcounter" fluid />
                                        </fieldset>
                                        <fieldset>
                                            <label>Order Number:</label>
                                            <InputText size="small" v-model="item.rtid" fluid />
                                        </fieldset>
                                        <fieldset>
                                            <label>Item Number:</label>
                                            <InputText size="small" v-model="item.itemnumber" fluid />
                                        </fieldset>
                                    </div>
                                </template>
                            </Card>
                        </div>

                        <!-- CENTER: ALL OTHER INFO EXCEPT PRICING -->
                        <div class="form-col-center">
                            <div class="form-section other-section bg-white border-0">

                                <!-- SECTION: Dates -->
                                <div class="dates-section">
                                    <Card>
                                        <template #title>
                                            <div>
                                                <h6 class="text-primary">
                                                    Dates
                                                </h6>
                                                <Divider />
                                            </div>
                                        </template>

                                        <template #content>
                                            <div>
                                                <fieldset>
                                                    <label>Order Date:</label>
                                                    <input type="date" class="form-control" v-model="item.orderdate" />
                                                </fieldset>
                                                <fieldset>
                                                    <label>Payment Date:</label>
                                                    <input type="date" class="form-control"
                                                        v-model="item.paymentdate" />
                                                </fieldset>
                                                <fieldset>
                                                    <label>Shipped Date:</label>
                                                    <input type="date" class="form-control" v-model="item.shipdate" />
                                                </fieldset>
                                                <fieldset>
                                                    <label>Delivered Date:</label>
                                                    <input type="date" class="form-control"
                                                        v-model="item.datedelivered" />
                                                </fieldset>
                                            </div>
                                        </template>
                                    </Card>

                                    <Card class="mt-2">
                                        <template #content>
                                            <div>
                                                <fieldset>
                                                    <label>Description:</label>
                                                    <Textarea ref="descriptionarea" size="small" fluid
                                                        v-model="item.description" placeholder="Description" rows="4"
                                                        @input="autoResize" class="no-resize"></Textarea>
                                                </fieldset>
                                                <fieldset>
                                                    <label>Supplier Notes:</label>
                                                    <Textarea ref="supplierNotesarea" size="small" fluid
                                                        v-model="item.supplierNotes" placeholder="Supplier Notes"
                                                        rows="4" @input="autoResize" class="no-resize"></Textarea>
                                                </fieldset>
                                            </div>
                                        </template>
                                    </Card>
                                </div>

                                <!-- SECTION: Serial & Tracking -->
                                <div class="serial-tracking-section">
                                    <Card>
                                        <template #title>
                                            <div>
                                                <h6 class="text-primary">
                                                    Serial & Tracking
                                                </h6>
                                                <Divider />
                                            </div>
                                        </template>
                                        <template #content>
                                            <template v-if="serialKeys.length">
                                                <fieldset v-for="(
key, index
                                                    ) in serialKeys" :key="key">
                                                    <label>Serial Number
                                                        {{
                                                            getLabel(index)
                                                        }}:</label>
                                                    <InputText size="small" fluid v-model="item[key]" />
                                                </fieldset>
                                            </template>
                                            <template v-if="trackingKeys.length">
                                                <fieldset v-for="(
key, index
                                                    ) in trackingKeys" :key="key">
                                                    <label>Tracking Number
                                                        {{
                                                            index + 1
                                                        }}:</label>
                                                    <InputText size="small" fluid v-model="item[key]" />
                                                </fieldset>
                                            </template>
                                        </template>

                                    </Card>
                                </div>

                                <!-- SECTION: Product Info -->
                                <div class="product-info-section">
                                    <Card>
                                        <template #title>
                                            <div>
                                                <h6 class="text-primary">
                                                    Product Info
                                                </h6>
                                                <Divider />
                                            </div>
                                        </template>
                                        <template #content>
                                            <div>
                                                <fieldset>
                                                    <label>Supplier ID/Name:</label>
                                                    <InputText size="small" fluid v-model="item.seller" />
                                                </fieldset>
                                                <fieldset>
                                                    <label>Material:</label>
                                                    <Select v-model="item.materialtype" :options="materialTypesOptions"
                                                        optionLabel="label" optionValue="value"
                                                        placeholder="Select Material Type" fluid size="small" />

                                                </fieldset>
                                                <fieldset>
                                                    <label>Source Type:</label>
                                                    <Select v-model="item.sourceType" :options="sourceTypeOptions"
                                                        optionLabel="label" optionValue="value"
                                                        placeholder="Select Source Type" fluid size="small" />
                                                </fieldset>
                                                <fieldset>
                                                    <label>Carrier /
                                                        Courier:</label>
                                                    <Select v-model="item.carrier" :options="courierOptions"
                                                        optionLabel="label" optionValue="value"
                                                        placeholder="Select courier" fluid size="small" />
                                                </fieldset>

                                                <fieldset>
                                                    <label>Listed Condition:</label>
                                                    <Select v-model="item.listedcondition"
                                                        :options="listedConditionOptions" optionLabel="label"
                                                        optionValue="value" placeholder="Select condition" fluid
                                                        size="small" />
                                                </fieldset>
                                                <fieldset>
                                                    <label>Payment Method:</label>
                                                    <Select v-model="item.paymentmethod" :options="paymentMethodOptions"
                                                        optionLabel="label" optionValue="value"
                                                        placeholder="Select Payment Method" fluid size="small" />
                                                </fieldset>
                                            </div>
                                        </template>
                                    </Card>

                                    <Card class="mt-2">
                                        <template #content>
                                            <div>
                                                <fieldset>
                                                    <label>Employee Notes:</label>
                                                    <Textarea ref="employeeNotesarea" size="small" fluid
                                                        v-model="item.employeeNotes" placeholder="Employee Notes"
                                                        rows="4" @input="autoResize" class="no-resize"></Textarea>
                                                </fieldset>
                                            </div>
                                        </template>
                                    </Card>
                                </div>
                            </div>
                        </div>

                        <!-- RIGHT: PRICING -->
                        <div class="form-col-right">
                            <Card>
                                <template #title>
                                    <div>
                                        <h4 class="text-primary">Pricing</h4>
                                        <Divider />

                                        <fieldset>
                                            <label>Quantity</label>
                                            <InputText type="number" class="text-end" fluid v-model="item.quantity"
                                                size="small" />
                                        </fieldset>
                                        <fieldset>
                                            <label>Total Price</label>
                                            <InputText type="number" class=" text-end" fluid :value="item.price"
                                                size="small" />
                                        </fieldset>

                                        <fieldset>
                                            <label>Discount</label>
                                            <InputText type="number" class=" text-end" fluid v-model="item.Discount"
                                                size="small" />
                                        </fieldset>
                                        <fieldset>
                                            <label>Tax</label>
                                            <InputText type="number" class=" text-end" fluid v-model="item.tax"
                                                size="small" />
                                        </fieldset>
                                        <fieldset>
                                            <label>Shipping</label>
                                            <InputText type="number" class="text-end" fluid v-model="item.priceshipping"
                                                size="small" />
                                        </fieldset>
                                        <fieldset>
                                            <label>Refund</label>
                                            <InputText type="number" class="ftext-end" fluid v-model="item.refund"
                                                size="small" />
                                        </fieldset>
                                        <Divider />
                                        <fieldset>
                                            <label>Unit Price</label>
                                            <InputText type="text" class="text-end" fluid :value="formattedUnitprice"
                                                size="small" readonly />
                                        </fieldset>
                                        <fieldset>
                                            <label>Grand Total</label>
                                            <InputText type="text" class="text-end bg-light fw-bold text-success" fluid
                                                :value="grandTotal" size="small" readonly />
                                        </fieldset>
                                    </div>

                                </template>
                            </Card>

                        </div>
                    </div>
                </form>
            </div>
            <template #footer>
                <div class="pt-2 pr-2">
                    <Button label="Save" severity="info" size="small" @click="saveEditModal" icon="pi pi-save" />
                </div>
            </template>
        </Dialog>


        <ScrollTop />
    </div>
</template>

<script>
import Orders from "./orders.js";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import TableGallery from "../../components/Gallery/tableGallery.vue";
import { Badge, Button, Card, Dialog, Divider, InputText, Textarea, DatePicker, Select, ScrollTop } from "primevue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import ViewImageModal from "../../components/ViewImageModal/ViewImageModal.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";


const TABLE_COLUMNS = [
    {
        field: "gallery",
        header: "Gallery",
        slot: "gallery",
        style: { width: "4rem", minWidth: "4rem" },
    },
    {
        field: "ProductTitle",
        header: "Product Name",
        sortable: true,
        headerStyle: "font-size: 16px;",
        slot: "ProductTitle",
        style: { minWidth: "15rem", maxWidth: "20rem" },
    },
    {
        field: "Ebay_seller_location",
        header: "Seller Location",
        sortable: true,
        headerStyle: "font-size: 16px;",
        style: { fontSize: "14px" }
    },
    {
        field: "trackingnumber",
        header: "Tracking Number",
        sortable: true,
        headerStyle: "font-size: 16px;",
        style: { fontSize: "14px", }
    },
    {
        field: "listedcondition",
        header: "Ordered Condition",
        sortable: true,
        headerStyle: "font-size: 16px;",
        style: { fontSize: "14px", textAlign: "center" }
    },
    {
        field: "itemstatus",
        header: "Condition Status",
        slot: "status",
        sortable: true,
        headerStyle: "font-size: 16px;",
        style: { textAlign: "center" }
    },
    {
        field: "orderdate",
        header: "Ordered Date",
        sortable: true,
        headerStyle: "font-size: 16px;",
        style: { fontSize: "14px", textAlign: "center" }
    },
    {
        field: "datedelivered",
        header: "Delivered Date",
        sortable: true,
        headerStyle: "font-size: 16px;",
        style: { fontSize: "14px", textAlign: "center" }
    }
]
export default {
    mixins: [Orders],
    components: {
        XDataTable,
        TableGallery,
        Button,
        Badge,
        Dialog,
        Divider,
        InputText,
        Textarea,
        Card,
        DatePicker,
        Select,
        ScrollTop,
        TitlePage,
        ViewImageModal,
        AnimateDiv
    },
    data() {
        return {
            selectedRows: [],
            columns: TABLE_COLUMNS,
            sourceTypeOptions: [
                { label: "ES", value: "ES" },
                { label: "AS", value: "AS" },
                { label: "XS", value: "XS" },
                { label: "PS", value: "PS" },
                { label: "RS", value: "RS" },
                { label: "B&H", value: "B&H" }
            ],
            listedConditionOptions: [
                { label: "New", value: "New" },
                { label: "Open Box", value: "Open Box" },
                { label: "Used", value: "Used" },
                { label: "For parts or not working", value: "For parts or not working" }
            ],
            paymentMethodOptions: [
                { label: "PayPal", value: "PayPal" },
                { label: "Credit/Debit Card", value: "Credit/Debit Card" },
                { label: "Cash", value: "Cash" },
                { label: "Bank Transfer", value: "Bank Transfer" },
                { label: "Check", value: "Check" }
            ]
        }
    },
    computed: {
        materialTypesOptions() {
            return this.materialTypes.map((type) => ({
                value: type,
                label: type,
            }));
        },
        courierOptions() {
            return this.carrierOptions.map((carrier) => ({
                value: carrier,
                label: carrier,
            }))
        },
    }

}
</script>
