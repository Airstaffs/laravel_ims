<template>
    <div class="vue-container fnsku-module">
        <!-- <div class="top-header">
            <div class="header-buttons">
                <button @click="showInsertFnskuModal" class="btn fnsku-button">
                    <i class="bi bi-plus"></i> ADD FNSKU
                </button>
            </div>
        </div> -->

        <h2 class="module-title"></h2>
        <div class="d-flex align-items-center justify-content-between flex-wrap mb-4">
            <TitlePage title="FNSKU Module" />
            <Button @click="showInsertFnskuModal" label="Add FNSKU" severity="info" outlined size="small"
                icon="pi pi-plus" class="me-4" />
        </div>

        <!-- Desktop Table Container -->
        <div class="px-4">
            <XDataTable :value="inventory" :columns="columns" :loading="loading" :paginator="false"
                tableClass="desktop-view" dataKey="FNSKUID" selectionMode="multiple">
                <template #grading="{ data }">
                    <Tag :value="data.grading" :severity="getGradingSeverity(data.grading)"
                        style="font-size: 0.7rem;" />
                </template>

                <template #status="{ data }">
                    <Tag :value="data.fnsku_status" :severity="data.fnsku_status === 'available' ? 'success' : 'danger'"
                        style="font-size: 0.7rem;" />
                </template>

            </XDataTable>
        </div>
        <!-- <div class="table-container desktop-view">
            <table>
                <thead>
                    <tr>
                        <th class="sticky-header first-col">
                            <input type="checkbox" @click="toggleAll" v-model="selectAll" />
                        </th>
                        <th class="">ASIN</th>
                        <th class="">FNSKU</th>
                        <th class="">MSKU</th>
                        <th class="">Grading</th>
                        <th class="">Status</th>
                        <th class="">Store Name</th>
                        <th class="">Units</th>
                        <th class="">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="9" class="text-center">
                            <div class="loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i>
                                Loading...
                            </div>
                        </td>
                    </tr>
                    <tr v-else-if="inventory.length === 0">
                        <td colspan="9" class="text-center">No orders found</td>
                    </tr>
                    <template v-else v-for="(item, index) in inventory" :key="item.FNSKUID">
                        <tr>
                            <td class="sticky-col first-col">
                                <input type="checkbox" v-model="item.checked" />
                            </td>
                            <td>
                                <span>
                                    <strong>{{ item.ASIN }}</strong>
                                </span>
                            </td>
                            <td>
                                <span>
                                    <strong>{{ item.FNSKU }}</strong>
                                </span>
                            </td>
                            <td>
                                <span>
                                    <strong>{{ item.MSKU }}</strong>
                                </span>
                            </td>
                            <td>
                                <span class="badge text-white" :class="{
                                    'bg-primary':
                                        item.grading === 'UsedVeryGood',
                                    'bg-warning':
                                        item.grading === 'UsedGood',
                                    'bg-info':
                                        item.grading === 'UsedLikeNew',
                                    'bg-secondary': ![
                                        'UsedVeryGood',
                                        'UsedGood',
                                        'UsedLikeNew',
                                    ].includes(item.grading),
                                }">
                                    {{ item.grading }}
                                </span>
                            </td>
                            <td>
                                <span class="badge text-white" :class="item.fnsku_status === 'available'
                                    ? 'bg-success'
                                    : 'bg-danger'
                                    ">
                                    {{ item.fnsku_status }}
                                </span>
                            </td>
                            <td>
                                <span>
                                    <strong>{{ item.storename }}</strong>
                                </span>
                            </td>
                            <td>
                                <span>
                                    <strong>{{ item.Units }}</strong>
                                </span>
                            </td>
                            <td>
                                {{ item.totalquantity }}
                                <button @click="toggleDetails(index)" class="more-details-btn">
                                    {{
                                        expandedRows[index]
                                            ? "Less Details"
                                            : "More Details"
                                    }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="expandedRows[index]" class="expanded-row">
                            <td colspan="4">
                                <div class="expanded-content">
                                    <strong>Product Name:</strong>
                                    {{ item.astitle }}
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div> -->

        <!-- Mobile View -->
        <div class="mobile-view">
            <div class="mobile-cards">
                <div v-if="loading" class="loading-spinner-mobile">
                    <i class="fas fa-spinner fa-spin"></i>
                    Loading...
                </div>
                <div v-else-if="inventory.length === 0" class="no-data-mobile">
                    No data found
                </div>
                <div class="mobile-card" v-else v-for="(item, index) in inventory" :key="item.FNSKUID">
                    <div class="mobile-card-details d-flex flex-column gap-2" :style="{ fontSize: '14px' }">
                        <div class="mobile-checkbox">
                            <input type="checkbox" v-model="item.checked" />
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">ASIN:</span>
                            <span class="mobile-detail-value">{{
                                item.ASIN
                                }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">FNSKU:</span>
                            <span class="mobile-detail-value">{{
                                item.FNSKU
                                }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">MSKU:</span>
                            <span class="mobile-detail-value">{{
                                item.MSKU
                                }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Grading:</span>
                            <Tag :value="item.grading" :severity="getGradingSeverity(item.grading)"
                                style="font-size: 0.7rem;" />
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Status:</span>
                            <!-- <span class="mobile-detail-value badge text-white" :class="item.fnsku_status === 'available'
                                ? 'bg-success'
                                : 'bg-danger'
                                ">
                                {{ item.fnsku_status }}
                            </span> -->
                            <Tag :value="item.fnsku_status"
                                :severity="item.fnsku_status === 'available' ? 'success' : 'danger'"
                                style="font-size: 0.7rem;" />
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Store name:</span>
                            <span class="mobile-detail-value">{{
                                item.storename
                                }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Units:</span>
                            <span class="mobile-detail-value">{{
                                item.Units
                                }}</span>
                        </div>
                    </div>

                    <!-- <hr />

                    <div class="mobile-card-actions">
                        <button @click="toggleDetails(index)" class="mobile-btn mobile-btn-details">
                            <i class="fas fa-info-circle"></i>
                            {{
                                expandedRows[index]
                                    ? "Less Details"
                                    : "More Details"
                            }}
                        </button>
                    </div>

                    <hr v-if="expandedRows[index]" />

                    <div v-if="expandedRows[index]" class="mobile-expanded-content">
                        <div class="mobile-section">
                            <strong>Product Name:</strong> {{ item.astitle }}
                        </div>
     
                    </div> -->
                </div>
            </div>
        </div>

        <!-- Pagination with centered layout -->
        <Paginator
            :first="first"
            :rows="perPage"
            :total-records="totalRecords"
            :rows-per-page-options="[10, 20, 50]"
            template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords}"
            class="small-paginator"
            @page="onPageChange"
        />

        <!-- Insert FNSKU Modal -->
        <Dialog v-model:visible="isInsertFnskuModalVisible" modal header="Add New FNSKU" :style="{ width: '50%' }" :pt="{
            root: { class: 'mobile-fullscreen-dialog' }
        }">
            <form>
                <fieldset>
                    <label><span>FNSKU:</span></label>
                    <InputText type="text" id="newFnsku" v-model="newFnskuData.fnsku" placeholder="Enter FNSKU"
                        size="small" fluid ref="newFnskuInput" @input="focusNext('newMskuInput')" />
                </fieldset>

                <fieldset>
                    <label><span>MSKU:</span></label>
                    <InputText type="text" id="newMsku" v-model="newFnskuData.msku" placeholder="Enter MSKU"
                        size="small" fluid ref="newMskuInput" @input="focusNext('newAsinInput')" />
                </fieldset>

                <fieldset>
                    <label><span>ASIN:</span></label>
                    <InputText type="text" id="newAsin" v-model="newFnskuData.asin" placeholder="Enter ASIN"
                        size="small" fluid ref="newAsinInput" @input="focusNext('newTitleInput')" />
                </fieldset>

                <fieldset>
                    <label><span>Title:</span></label>
                    <InputText type="text" id="newTitle" v-model="newFnskuData.astitle"
                        placeholder="Enter Product Title" size="small" fluid ref="newTitleInput"
                        @input="focusNext('newGradingInput')" />
                </fieldset>

                <fieldset>
                    <label><span>Grading:</span></label>
                    <Select v-model="newFnskuData.grading" ref="newGradingInput" :options="gradingOptions" size="small"
                        fluid optionLabel="label" optionValue="value" />
                </fieldset>

                <fieldset>
                    <label><span>Store Name:</span></label>
                    <Select v-model="newFnskuData.storeName" ref="newStoreNameInput" :options="storeOptions"
                        size="small" fluid optionLabel="label" optionValue="value" />
                </fieldset>

                <Button class="mt-4" @click="saveNewFnsku" severity="info" size="small" label="Add FNSKU" />
            </form>
        </Dialog>
        <!-- <div class="modal fnsku-modal" v-if="isInsertFnskuModalVisible">
            <div class="modal-overlay" @click="hideInsertFnskuModal"></div>

            <div class="modal-content">
                <div class="modal-header">
                    <h2>Add New FNSKU</h2>
                    <span class="close" @click="hideInsertFnskuModal">&times;</span>
                </div>

                <div class="modal-body">
                    <form class="fnskuForm">
                        <fieldset>
                            <label><span>FNSKU:</span></label>
                            <input type="text" id="newFnsku" v-model="newFnskuData.fnsku" placeholder="Enter FNSKU"
                                class="form-control" ref="newFnskuInput" @input="focusNext('newMskuInput')" />
                        </fieldset>

                        <fieldset>
                            <label><span>MSKU:</span></label>
                            <input type="text" id="newMsku" v-model="newFnskuData.msku" placeholder="Enter MSKU"
                                class="form-control" ref="newMskuInput" @input="focusNext('newAsinInput')" />
                        </fieldset>

                        <fieldset>
                            <label><span>ASIN:</span></label>
                            <input type="text" id="newAsin" v-model="newFnskuData.asin" placeholder="Enter ASIN"
                                class="form-control" ref="newAsinInput" @input="focusNext('newTitleInput')" />
                        </fieldset>

                        <fieldset>
                            <label><span>Title:</span></label>
                            <input type="text" id="newTitle" v-model="newFnskuData.astitle"
                                placeholder="Enter Product Title" class="form-control" ref="newTitleInput"
                                @input="focusNext('newGradingInput')" />
                        </fieldset>

                        <fieldset>
                            <label><span>Grading:</span></label>
                            <select id="newGrading" v-model="newFnskuData.grading" class="form-control"
                                ref="newGradingInput">
                                <option value="New">New</option>
                                <option value="Like New">Like New</option>
                                <option value="Very Good">Very Good</option>
                                <option value="Good">Good</option>
                                <option value="Acceptable">Acceptable</option>
                            </select>
                        </fieldset>

                        <fieldset>
                            <label><span>Store Name:</span></label>
                            <select id="newStoreName" v-model="newFnskuData.storeName" class="form-control"
                                ref="newStoreNameInput">
                                <option value="Allrenewed">Allrenewed</option>
                                <option value="Renovartech">Renovartech</option>
                            </select>
                        </fieldset>

                        <button @click="saveNewFnsku" class="btn btn-process">
                            Save FNSKU
                        </button>
                    </form>
                </div>
            </div>
        </div> -->
    </div>
</template>

<script>
import FNSKU from "./fnsku.js";
import XDataTable from '../../components/DataTable/XDataTable.vue'
import { Button, Tag, InputText, Select, Dialog, Paginator } from "primevue";
import TitlePage from '../../components/TitlePage/TitlePage.vue'
import { ROWS_PER_PAGE } from "../../constant.js";
const TABLE_COLUMNS = [
    // {
    //     selectionMode: "multiple",
    //     header: "",
    //     style: { width: "3rem", minWidth: "3rem" },
    //     headerStyle: "width: 3rem; min-width: 3rem; max-width: 3rem; padding: 0.25rem;",
    //     bodyStyle: "width: 3rem; min-width: 3rem; max-width: 3rem; padding: 0.25rem;",
    // },
    {
        header: "ASIN",
        field: "ASIN",
        bodyStyle: "fontSize: 14px"
    },
    {
        header: "FNSKU",
        field: "FNSKU",
        bodyStyle: "fontSize: 14px"
    },
    {
        header: "MSKU",
        field: "MSKU",
        bodyStyle: "fontSize: 14px"
    },
    {
        header: "Grading",
        slot: "grading",
        bodyStyle: "fontSize: 14px"
    },
    {
        header: "Status",
        slot: "status",
        bodyStyle: "fontSize: 14px"
    },
    {
        header: "Store Name",
        field: "storename",
        bodyStyle: "fontSize: 14px"
    },
    {
        header: "Units",
        field: "Units",
        bodyStyle: "fontSize: 14px"
    }
]

export default {
    mixins: [FNSKU],
    components: {
        XDataTable,
        Button,
        InputText,
        Tag,
        Select,
        TitlePage,
        Dialog,
        Paginator
    },
    data() {
        return {
            columns: TABLE_COLUMNS,
            gradingOptions: [
                { value: "New", label: "New" },
                { value: "Like New", label: "Like New" },
                { value: "Very Good", label: "Very Good" },
                { value: "Good", label: "Good" },
                { value: "Acceptable", label: "Acceptable" }
            ],
            storeOptions: [
                { value: "Allrenewed", label: "Allrenewed" },
                { value: "Renovartech", label: "Renovartech" }
            ],
            rowsPerPage: ROWS_PER_PAGE
        }
    },
    methods: {
        getGradingSeverity(grading) {
            const severityMap = {
                'UsedVeryGood': 'success',
                'UsedGood': 'warn',
                'UsedLikeNew': 'info'
            };
            return severityMap[grading] || 'secondary';
        }
    }
};
</script>
