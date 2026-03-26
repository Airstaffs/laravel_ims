<template>
    <Dialog v-model:visible="visibleProxy" modal :closable="true" :draggable="false" header="Amazon Listings"
        style="width: 95%; max-width: 2000px;"
        :contentStyle="{ padding: '0', display: 'flex', flexDirection: 'column', height: '85vh' }" @hide="onClose">
        <!-- Toolbar (Amazon-ish) -->
        <div class="toolbar">
            <!-- Row 1 -->
            <div class="toolbar__row">
                <div class="toolbar__field toolbar__field--store">
                    <label class="toolbar__label">Store</label>
                    <Dropdown v-model="filters.store" :options="storeOptions" optionLabel="label" optionValue="value"
                        class="w-full p-inputtext-sm" />
                </div>

                <div class="toolbar__field toolbar__field--idtype">
                    <label class="toolbar__label">Identifier</label>
                    <Dropdown v-model="filters.identifiersType" :options="identifierTypeOptions" optionLabel="label"
                        optionValue="value" class="w-full p-inputtext-sm" />
                </div>

                <div class="toolbar__field toolbar__field--search">
                    <label class="toolbar__label">Search</label>
                    <InputText v-model="filters.identifiersRaw" class="w-full p-inputtext-sm"
                        placeholder="SKU, ASIN, FNSKU… (comma/new line)" @keyup.enter="runSearch(true)" />
                    <small class="toolbar__hint">Paste multiple values separated by comma or new line</small>
                </div>

                <div class="toolbar__actions">
                    <Button label="Search" icon="pi pi-search" class="p-button-sm" :loading="loading"
                        @click="runSearch(true)" />
                    <Button label="Reset" icon="pi pi-refresh" class="p-button-sm" severity="secondary"
                        :disabled="loading" @click="resetFilters" />
                    <Button label="Assign Selected to Automation" icon="pi pi-link" class="p-button-sm"
                        severity="warning" :disabled="loading || !listingSelectedRows.length"
                        @click="openAssignAutomationModal" />
                    <Button label="Amazon Automated Pricing" icon="pi pi-bolt" class="p-button-sm" severity="help"
                        :disabled="loading" @click="openAutomationModal" />


                </div>
            </div>

            <!-- Row 2 -->
            <div class="toolbar__row toolbar__row--secondary">
                <div class="toolbar__field toolbar__field--small">
                    <label class="toolbar__label">Sort</label>
                    <Dropdown v-model="filters.sortBy" :options="sortByOptions" optionLabel="label" optionValue="value"
                        class="w-full p-inputtext-sm" />
                </div>

                <div class="toolbar__field toolbar__field--small">
                    <label class="toolbar__label">Order</label>
                    <Dropdown v-model="filters.sortOrder" :options="sortOrderOptions" optionLabel="label"
                        optionValue="value" class="w-full p-inputtext-sm" />
                </div>

                <div class="toolbar__field toolbar__field--small">
                    <label class="toolbar__label">Page Size</label>
                    <Dropdown v-model="filters.pageSize" :options="pageSizeOptions" optionLabel="label"
                        optionValue="value" class="w-full p-inputtext-sm" />
                </div>

                <div class="toolbar__spacer"></div>

                <div class="toolbar__pager">
                    <Button label="Prev" icon="pi pi-angle-left" class="p-button-sm" severity="secondary"
                        :disabled="loading || !page.prevToken" @click="goPrev" />
                    <Button label="Next" icon="pi pi-angle-right" iconPos="right" class="p-button-sm"
                        severity="secondary" :disabled="loading || !page.nextToken" @click="goNext" />
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="p-0 table-wrap">
            <DataTable :value="rows" :loading="loading" dataKey="sku" responsiveLayout="scroll" class="p-datatable-sm"
                v-model:selection="listingSelectedRows">
                <Column selectionMode="multiple" headerStyle="width: 3rem" />
                <Column header="Listing status" style="width: 220px;">
                    <template #body="{ data }">
                        <div class="font-medium">
                            <span class="inline-flex align-items-center gap-2">
                                <span class="status-dot"
                                    :class="data.status === 'ACTIVE' ? 'status-active' : 'status-other'"></span>
                                {{ data.status || '—' }}
                            </span>
                        </div>

                        <!-- FBM / FBA badges -->
                        <div class="mt-1 flex gap-1 flex-wrap">
                            <Tag v-if="data.hasFBM" value="FBM" severity="info" />
                            <Tag v-if="data.hasFBA" value="FBA" severity="success" />
                        </div>

                        <div class="text-500 text-xs mt-1" v-if="data.lastUpdatedDate">
                            {{ data.lastUpdatedDate }}
                        </div>
                    </template>
                </Column>

                <Column header="Product details" style="min-width: 420px;">
                    <template #body="{ data }">
                        <div class="flex gap-3 align-items-start">
                            <img v-if="data.image" :src="data.image" alt="img"
                                style="width: 52px; height: 52px; object-fit: contain; border: 1px solid var(--surface-border); border-radius: 6px;" />
                            <div class="min-w-0">
                                <div class="font-medium text-primary truncate">
                                    {{ data.title || '—' }}
                                </div>
                                <div class="text-500 text-sm mt-1">
                                    <span class="mr-3"><b>ASIN</b> {{ data.asin || '—' }}</span>
                                </div>
                                <div class="text-500 text-sm mt-1">
                                    <span><b>SKU</b> {{ data.sku || '—' }}</span>
                                </div>
                                <div class="text-500 text-sm mt-1">
                                    <span><b>FNSKU</b> {{ data.fnsku || '—' }}</span>
                                </div>
                                <div class="text-500 text-xs mt-1" v-if="data.conditionType">
                                    Condition: {{ data.conditionType }}
                                </div>
                            </div>
                        </div>
                    </template>
                </Column>

                <!-- NEW: IMS Qty column -->
                <Column header="IMS Qty" style="width: 140px;">
                    <template #body="{ data }">
                        <span class="text-sm font-medium">{{ data.imsQtyDisplay }}</span>
                        <!--
                        <div class="text-xs text-500" v-if="data.imsMatchedBy">
                            {{ data.imsMatchedBy }}
                        </div>
                        -->
                    </template>
                </Column>

                <!-- NEW: FBA Qty column -->
                <Column header="FBA Qty" style="width: 140px;">
                    <template #body="{ data }">
                        <span class="text-sm font-medium">{{ data.fbaQtyDisplay }}</span>
                    </template>
                </Column>

                <!-- Inventory (FBM) -->
                <Column header="Inventory (FBM)" style="width: 260px;">
                    <template #body="{ data }">
                        <div class="text-sm">
                            <div class="mb-2 flex justify-content-between">
                                <div><b>Available</b>: <span class="ml-2">{{ data.currentQty ?? '—' }}</span></div>

                                <!-- row save status -->
                                <div class="row-status">
                                    <i v-if="data._savingQty" class="pi pi-spin pi-spinner"></i>
                                    <i v-else-if="data._savedQty" class="pi pi-check"></i>
                                    <i v-else-if="data._errorQty" class="pi pi-exclamation-triangle"></i>
                                </div>
                            </div>

                            <div class="flex align-items-center gap-2">
                                <InputText v-model="data.newQty" placeholder="Qty (blank allowed)"
                                    class="w-full p-inputtext-sm compact-input" @focus="markTouched(data, 'qty')"
                                    @blur="queueAutoSave(data)" @keyup.enter="queueAutoSave(data)" />
                                <Button icon="pi pi-times" severity="secondary" text v-tooltip.top="'Clear'"
                                    @click="clearField(data, 'qty')" class="p-button-sm" />
                            </div>

                            <small class="text-500">Auto-saves per item. 0 = out of stock. Blank = clear.</small>

                            <div v-if="data._errorPrice" class="error-chip" v-tooltip.top="data._errorPrice">
                                <i class="pi pi-exclamation-triangle mr-1"></i>
                                Save failed
                            </div>
                        </div>
                    </template>
                </Column>

                <!-- Price -->
                <Column header="Price" style="width: 260px;">
                    <template #body="{ data }">
                        <div class="text-sm">
                            <div class="mb-2 flex justify-content-between">
                                <div><b>Current</b>: <span class="ml-2">{{ data.currentPrice ?? '—' }}</span></div>

                                <!-- row save status -->
                                <div class="row-status">
                                    <i v-if="data._savingPrice" class="pi pi-spin pi-spinner"></i>
                                    <i v-else-if="data._savedPrice" class="pi pi-check"></i>
                                    <i v-else-if="data._errorPrice" class="pi pi-exclamation-triangle"></i>
                                </div>
                            </div>

                            <div class="flex align-items-center gap-2">
                                <InputText v-model="data.newPrice" placeholder="Price (blank allowed)"
                                    class="w-full p-inputtext-sm compact-input" @focus="markTouched(data, 'price')"
                                    @blur="queueAutoSave(data)" @keyup.enter="queueAutoSave(data)" />
                                <span class="text-500">{{ data.currency || 'USD' }}</span>
                                <Button icon="pi pi-times" severity="secondary" text v-tooltip.top="'Clear'"
                                    @click="clearField(data, 'price')" class="p-button-sm" />
                            </div>

                            <small class="text-500">Auto-saves per item. Blank = clear.</small>

                            <div v-if="data._errorPrice" class="error-chip" v-tooltip.top="data._errorPrice">
                                <i class="pi pi-exclamation-triangle mr-1"></i>
                                Save failed
                            </div>
                        </div>
                    </template>
                </Column>

                <Column header="Issues" style="width: 220px;">
                    <template #body="{ data }">
                        <div v-if="data.issues?.length" class="issues-cell">
                            <Tag severity="success" :value="`${data.issues.length} issue(s)`" class="issues-tag"
                                @click="openIssuesModal(data)" />
                            <div class="text-xs text-500 mt-1 truncate-issue">
                                {{ (data.issues?.[0]?.message || data.issues?.[0]?.code || 'Issue') }}
                            </div>
                        </div>
                        <div v-else class="text-500">—</div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Footer actions -->
        <div class="p-3 border-top-1 surface-border flex justify-content-between align-items-center">
            <div class="text-500 text-sm">
                {{ rows.length }} item(s)
            </div>
            <div class="flex gap-2">
                <Button label="Close" severity="secondary" @click="onClose" class="p-button-sm" />
            </div>
        </div>
    </Dialog>

    <Dialog v-model:visible="issuesModal.visible" modal :draggable="false" :closable="true" header="Listing Issues"
        :style="{ width: '720px', maxWidth: '95vw' }" :contentStyle="{ padding: '0' }">
        <!-- Header strip -->
        <div class="issues-modal-head">
            <div class="issues-modal-title">
                <div class="font-medium">{{ issuesModal.title || '—' }}</div>
                <div class="text-500 text-sm mt-1">
                    <span class="mr-3"><b>SKU</b> {{ issuesModal.sku || '—' }}</span>
                    <span><b>ASIN</b> {{ issuesModal.asin || '—' }}</span>
                </div>
            </div>

            <Button label="Copy all" icon="pi pi-copy" severity="secondary" class="p-button-sm"
                @click="copyIssuesToClipboard" />
        </div>

        <!-- Body -->
        <div class="issues-modal-body">
            <div v-if="issuesModal.issues?.length" class="issues-list">
                <div v-for="(it, idx) in issuesModal.issues" :key="idx" class="issues-item">
                    <div class="issues-item-top">
                        <span class="issues-index">#{{ idx + 1 }}</span>
                        <Tag v-if="it.severity" :value="String(it.severity)" severity="warning" class="ml-2" />
                        <span v-if="it.code" class="issues-code ml-auto">{{ it.code }}</span>
                    </div>

                    <div class="issues-message">
                        {{ it.message || it.summary || it.code || 'Issue' }}
                    </div>

                    <div v-if="it.attributeName || it.enforcements?.length" class="issues-meta">
                        <span v-if="it.attributeName"><b>Field:</b> {{ it.attributeName }}</span>
                        <span v-if="it.enforcements?.length" class="ml-3">
                            <b>Enforcements:</b> {{ it.enforcements.length }}
                        </span>
                    </div>
                </div>
            </div>

            <div v-else class="text-500 p-3">No issues.</div>
        </div>

        <!-- Footer -->
        <div class="issues-modal-foot">
            <Button label="Close" severity="secondary" class="p-button-sm" @click="issuesModal.visible = false" />
        </div>
    </Dialog>

    <Dialog v-model:visible="automationModal.visible" modal :draggable="false" :closable="true"
        header="Amazon Automated Pricing" :style="{ width: '1200px', maxWidth: '98vw' }"
        :contentStyle="{ padding: '0' }" class="automation-pricing-modal">
        <!-- Header -->
        <div class="auto-head">
            <div class="auto-head-left">
                <div class="font-medium text-lg">Create / Update Automation</div>
                <div class="text-500 text-sm mt-1">
                    Configure pricing windows and view assigned MSKUs from Amazon Listings.
                </div>
            </div>

            <div class="auto-head-right flex gap-2 align-items-end flex-wrap">
                <div style="min-width: 320px;">
                    <label class="auto-label">Automation</label>
                    <Dropdown v-model="automationModal.selectedAutomationId" :options="automationModal.list"
                        optionLabel="label" optionValue="id" class="w-full p-inputtext-sm"
                        placeholder="Select existing..."
                        :disabled="automationModal.loading || automationModal.saving || automationModal.deleting"
                        @change="onSelectAutomation" />
                </div>

                <Button label="New" icon="pi pi-plus" class="p-button-sm" severity="secondary"
                    :disabled="automationModal.loading || automationModal.saving || automationModal.deleting"
                    @click="newAutomation" />

                <Button label="Delete" icon="pi pi-trash" class="p-button-sm" severity="danger"
                    :disabled="automationModal.loading || automationModal.saving || automationModal.deleting || !automationModal.id"
                    @click="deleteAutomation" />

                <Button label="Save" icon="pi pi-save" class="p-button-sm" :loading="automationModal.saving"
                    :disabled="automationModal.saving || automationModal.deleting || !automationCanSave"
                    @click="saveAutomation" />
            </div>
        </div>

        <!-- Body -->
        <div class="auto-body">
            <!-- Left -->
            <div class="auto-left">
                <!-- Automation Settings -->
                <div class="auto-card">
                    <div class="auto-card-title">Automation Settings</div>

                    <div class="auto-form-grid">
                        <div class="auto-field">
                            <label class="auto-label">Automation Name</label>
                            <InputText v-model="automationModal.name" class="w-full p-inputtext-sm"
                                placeholder="Example: Morning Repricing" />
                        </div>

                        <div class="auto-field">
                            <label class="auto-label">Timezone</label>
                            <InputText v-model="automationModal.timezone" class="w-full p-inputtext-sm"
                                placeholder="America/Los_Angeles" />
                        </div>
                    </div>
                </div>

                <!-- Pricing Rules -->
                <div class="auto-card">
                    <div class="auto-card-top">
                        <div class="auto-card-title">Pricing Rules</div>

                        <div class="flex gap-2">
                            <Button label="Add Rule" icon="pi pi-plus" class="p-button-sm" severity="secondary"
                                @click="addRule" />
                            <Button label="Sort by Min" icon="pi pi-sort-amount-up" class="p-button-sm"
                                severity="secondary" @click="sortRules" />
                        </div>
                    </div>

                    <div class="rules-table-wrap">
                        <div class="rules-head">
                            <div class="rules-col">Start</div>
                            <div class="rules-col">End</div>
                            <div class="rules-col">Min</div>
                            <div class="rules-col">Max</div>
                            <div class="rules-col">Delta</div>
                            <div class="rules-col actions">Action</div>
                        </div>

                        <div v-for="(r, idx) in automationModal.rules" :key="'rule-' + idx" class="rules-row">
                            <InputText v-model="automationModal.rules[idx].start" class="p-inputtext-sm w-full"
                                placeholder="09:00" />
                            <InputText v-model="automationModal.rules[idx].end" class="p-inputtext-sm w-full"
                                placeholder="10:00" />
                            <InputText v-model="automationModal.rules[idx].min" class="p-inputtext-sm w-full"
                                placeholder="100" />
                            <InputText v-model="automationModal.rules[idx].max" class="p-inputtext-sm w-full"
                                placeholder="200" />
                            <InputText v-model="automationModal.rules[idx].delta" class="p-inputtext-sm w-full"
                                placeholder="-50" />

                            <Button icon="pi pi-trash" class="p-button-sm" severity="danger" text
                                :disabled="automationModal.rules.length <= 1" @click="removeRule(idx)" />
                        </div>
                    </div>

                    <div class="auto-field mt-3">
                        <label class="auto-label">Default Delta (if no rule matches)</label>
                        <InputText v-model="automationModal.defaultDelta" class="w-full p-inputtext-sm"
                            placeholder="0" />
                    </div>

                    <small class="text-500 block mt-2">
                        Pricing runs every cron tick, but only rules whose current time window matches will apply.
                    </small>
                </div>
            </div>

            <!-- Right -->
            <div class="auto-right">
                <div class="auto-card">
                    <div class="auto-card-title">Assigned MSKUs</div>

                    <div class="auto-assigned">
                        <div class="auto-assigned-top">
                            <div class="text-500 text-sm">
                                {{ assignedCount }} assigned
                            </div>

                            <Button label="Clear Selected" icon="pi pi-times" severity="secondary" class="p-button-sm"
                                :disabled="assignedCount === 0" @click="automationModal.selectedRows = []" />
                        </div>

                        <div v-if="assignedCount" class="auto-assigned-list">
                            <div v-for="r in automationModal.selectedRows" :key="r.assigned_item_id"
                                class="auto-assigned-item">
                                <div class="auto-assigned-main">
                                    <div class="font-medium">{{ r.MSKU }}</div>
                                    <div class="text-500 text-xs mt-1">
                                        <span class="mr-3"><b>ASIN</b> {{ r.ASIN || '—' }}</span>
                                        <span class="mr-3"><b>FNSKU</b> {{ r.FNSKU || '—' }}</span>
                                        <span><b>Store</b> {{ r.storename || '—' }}</span>
                                    </div>
                                </div>

                                <Button icon="pi pi-trash" text severity="danger" class="p-button-sm"
                                    @click="removeAssigned(r)" />
                            </div>
                        </div>

                        <div v-else class="text-500 p-2">
                            No assigned MSKUs yet. Select listings in Amazon Listings and assign them to an automation.
                        </div>
                    </div>
                </div>

                <div class="auto-card">
                    <div class="auto-card-title">Summary</div>

                    <div class="auto-summary">
                        <div class="summary-row">
                            <span>Rules</span>
                            <b>{{ automationModal.rules?.length || 0 }}</b>
                        </div>
                        <div class="summary-row">
                            <span>Assigned</span>
                            <b>{{ assignedCount }}</b>
                        </div>
                        <div class="summary-row">
                            <span>Timezone</span>
                            <b>{{ automationModal.timezone || '—' }}</b>
                        </div>
                        <div class="summary-row">
                            <span>Store</span>
                            <b>{{ automationModal.store || '—' }}</b>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="auto-foot">
            <Button label="Close" severity="secondary" class="p-button-sm" @click="automationModal.visible = false" />
        </div>
    </Dialog>

    <Dialog v-model:visible="assignAutomationModal.visible" modal :draggable="false" :closable="true"
        header="Assign Selected Listings to Automation" :style="{ width: '520px', maxWidth: '95vw' }">
        <div class="flex flex-column gap-3">
            <div>
                <label class="auto-label">Automation</label>
                <Dropdown v-model="assignAutomationModal.selectedAutomationId" :options="assignAutomationModal.list"
                    optionLabel="label" optionValue="id" class="w-full p-inputtext-sm" placeholder="Select automation"
                    :disabled="assignAutomationModal.loading || assignAutomationModal.saving" />
            </div>

            <div class="text-500 text-sm">
                {{ listingSelectedRows.length }} selected listing(s) will be assigned.
            </div>

            <div class="border-1 surface-border border-round p-2 max-h-12rem overflow-auto">
                <div v-for="row in listingSelectedRows" :key="row.sku" class="py-2 border-bottom-1 surface-border">
                    <div class="font-medium">{{ row.sku || '—' }}</div>
                    <div class="text-500 text-xs">
                        <span class="mr-3"><b>ASIN</b> {{ row.asin || '—' }}</span>
                        <span><b>Store</b> {{ filters.store || '—' }}</span>
                    </div>
                </div>
            </div>

            <div class="flex justify-content-end gap-2">
                <Button label="Cancel" severity="secondary" class="p-button-sm"
                    @click="assignAutomationModal.visible = false" />
                <Button label="Save to Automation" icon="pi pi-save" class="p-button-sm"
                    :loading="assignAutomationModal.saving"
                    :disabled="!assignAutomationModal.selectedAutomationId || !listingSelectedRows.length"
                    @click="saveSelectedListingsToAutomation" />
            </div>
        </div>
    </Dialog>
</template>

<script>
import axios from "axios";

import Dialog from "primevue/dialog";
import Dropdown from "primevue/dropdown";
import InputText from "primevue/inputtext";
import Button from "primevue/button";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Tag from "primevue/tag";
import Tooltip from "primevue/tooltip";

const API_BASE_URL = import.meta.env.VITE_API_URL || window.location.origin;

export default {
    name: "AmazonListingsModal",
    components: {
        Dialog,
        Dropdown,
        InputText,
        Button,
        DataTable,
        Column,
        Tag,
    },

    directives: {
        tooltip: Tooltip,
    },

    props: {
        visible: { type: Boolean, default: false },
        storeOptions: {
            type: Array,
            default: () => ([
                { label: "Renovartech", value: "Renovartech" },
                { label: "Allrenewed", value: "Allrenewed" },
            ]),
        },
    },

    emits: ["update:visible", "applied"],

    data() {
        return {
            loading: false,
            rows: [],
            page: {
                nextToken: null,
                prevToken: null,
                stack: [],
            },
            filters: {
                store: "Renovartech",
                marketplaceIds: ["ATVPDKIKX0DER"],
                identifiersType: "SKU",
                identifiersRaw: "",
                sortBy: "lastUpdatedDate",
                sortOrder: "DESC",
                pageSize: 10,
                includedData: [
                    "summaries",
                    "attributes",
                    "issues",
                    "offers",
                    "fulfillmentAvailability",
                    "procurement",
                    "relationships",
                    "productTypes",
                ],
            },

            issuesModal: {
                visible: false,
                sku: null,
                asin: null,
                title: null,
                issues: [],
                raw: null,
            },

            _suppressAutosave: false,

            listingSelectedRows: [],
            assignAutomationModal: {
                visible: false,
                loading: false,
                saving: false,
                list: [],
                selectedAutomationId: null,
            },

            automationModal: {
                visible: false,
                saving: false,
                deleting: false,
                loading: false,

                list: [],
                selectedAutomationId: null,

                id: null,
                name: "",
                store: "Renovartech",
                marketplaceIds: ["ATVPDKIKX0DER"],
                timezone: "America/Los_Angeles",
                isEnabled: 1,

                rules: [
                    {
                        start: "09:00",
                        end: "10:00",
                        min: "100",
                        max: "200",
                        delta: "-50",
                    },
                ],

                defaultDelta: "0",
                selectedRows: [],
            },
        };
    },

    computed: {
        visibleProxy: {
            get() { return this.visible; },
            set(v) { this.$emit("update:visible", v); },
        },

        identifierTypeOptions() {
            return [
                { label: "SKU", value: "SKU" },
                { label: "ASIN", value: "ASIN" },
                { label: "FNSKU", value: "FNSKU" },
                { label: "EAN", value: "EAN" },
                { label: "GTIN", value: "GTIN" },
                { label: "ISBN", value: "ISBN" },
                { label: "JAN", value: "JAN" },
                { label: "MINSAN", value: "MINSAN" },
                { label: "UPC", value: "UPC" },
            ];
        },

        sortByOptions() {
            return [
                { label: "lastUpdatedDate", value: "lastUpdatedDate" },
                { label: "createdDate", value: "createdDate" },
                { label: "sku", value: "sku" },
            ];
        },

        sortOrderOptions() {
            return [
                { label: "DESC", value: "DESC" },
                { label: "ASC", value: "ASC" },
            ];
        },

        pageSizeOptions() {
            return [10, 20, 30, 50, 100, 200].map(n => ({
                label: String(n),
                value: n,
            }));
        },

        hasPendingChanges() {
            return this.rows.some(r => this.isValidInt(r.newQty) || this.isValidMoney(r.newPrice));
        },

        assignedCount() {
            return (this.automationModal.selectedRows || []).length;
        },

        automationCanSave() {
            const a = this.automationModal;
            if (!a.store) return false;
            if (!a.timezone) return false;
            if (!Array.isArray(a.marketplaceIds) || a.marketplaceIds.length < 1) return false;
            if (!Array.isArray(a.rules) || a.rules.length < 1) return false;

            const timeOk = (t) => /^([01]\d|2[0-3]):[0-5]\d$/.test(String(t || "").trim());

            const num = (v) => {
                const n = Number(String(v ?? "").trim());
                return Number.isFinite(n) ? n : null;
            };

            for (const r of a.rules) {
                const start = String(r.start || "").trim();
                const end = String(r.end || "").trim();
                const min = num(r.min);
                const max = num(r.max);
                const delta = num(r.delta);

                if (!timeOk(start) || !timeOk(end)) return false;
                if (start === end) return false;
                if (min === null || max === null || delta === null) return false;
                if (!(min < max)) return false;
            }

            return true;
        },

        marketplaceOptions() {
            return [
                { label: "US (ATVPDKIKX0DER)", value: "ATVPDKIKX0DER" },
            ];
        },

        enabledOptions() {
            return [
                { label: "Yes", value: 1 },
                { label: "No", value: 0 },
            ];
        },
    },

    methods: {
        onClose() {
            this._suppressAutosave = true;

            (this.rows || []).forEach(r => {
                if (r._saveTimer) clearTimeout(r._saveTimer);

                r.newQty = "";
                r.newPrice = "";
                r._touchedQty = false;
                r._touchedPrice = false;
                r._savingQty = false;
                r._savingPrice = false;
                r._savedQty = false;
                r._savedPrice = false;
                r._errorQty = "";
                r._errorPrice = "";
            });

            this.visibleProxy = false;
            setTimeout(() => { this._suppressAutosave = false; }, 0);
        },

        resetFilters() {
            this.filters.identifiersRaw = "";
            this.filters.identifiersType = "SKU";
            this.filters.sortBy = "lastUpdatedDate";
            this.filters.sortOrder = "DESC";
            this.filters.pageSize = 10;
            this.page = { nextToken: null, prevToken: null, stack: [] };
            this.rows = [];
        },

        parseIdentifiers(raw) {
            return (raw || "")
                .split(/[\n,]+/g)
                .map(s => s.trim())
                .filter(Boolean);
        },

        async runSearch(resetPaging = false) {
            const identifiers = this.parseIdentifiers(this.filters.identifiersRaw);

            if (!this.filters.store) return;
            if (!identifiers.length && !this.page.nextToken && resetPaging) return;

            if (resetPaging) {
                this.page = { nextToken: null, prevToken: null, stack: [] };
            }

            const payload = {
                store: this.filters.store,
                marketplaceIds: this.filters.marketplaceIds,
                identifiersType: this.filters.identifiersType,
                identifiers,
                includedData: this.filters.includedData,
                sortBy: this.filters.sortBy,
                sortOrder: this.filters.sortOrder,
                pageSize: this.filters.pageSize,
                pageToken: resetPaging ? null : this.page.prevToken ? this.page.prevToken : null,
            };

            this.loading = true;
            try {
                const res = await axios.post(`${API_BASE_URL}/amazon/search-listings`, payload);
                const raw = res?.data?.data || res?.data || {};
                const mapped = this.mapSearchListingsResponse(raw);

                this.rows = mapped.rows;
                this.page.nextToken = mapped.nextToken || null;
            } catch (err) {
                console.error("page error:", err?.response?.data || err);
            } finally {
                this.loading = false;
            }
        },

        async goNext() {
            if (!this.page.nextToken) return;
            this.page.stack.push(this.page.prevToken);
            this.page.prevToken = this.page.nextToken;
            await this.fetchByPageToken(this.page.nextToken);
        },

        async goPrev() {
            const prev = this.page.stack.pop();
            if (!prev) return;
            this.page.prevToken = prev;
            await this.fetchByPageToken(prev);
        },

        async fetchByPageToken(token) {
            const identifiers = this.parseIdentifiers(this.filters.identifiersRaw);

            const payload = {
                store: this.filters.store,
                marketplaceIds: this.filters.marketplaceIds,
                includedData: this.filters.includedData,
                sortBy: this.filters.sortBy,
                sortOrder: this.filters.sortOrder,
                pageSize: this.filters.pageSize,
                pageToken: token,
                identifiersType: this.filters.identifiersType,
                identifiers,
            };

            this.loading = true;
            try {
                const res = await axios.post(`${API_BASE_URL}/amazon/search-listings`, payload);
                const raw = res?.data?.data || res?.data || {};
                const mapped = this.mapSearchListingsResponse(raw);

                this.rows = mapped.rows;
                this.page.nextToken = mapped.nextToken || null;
            } catch (err) {
                console.error("page error:", err?.response?.data || err);
            } finally {
                this.loading = false;
            }
        },

        mapSearchListingsResponse(raw) {
            const items = raw?.items || raw?.payload?.items || [];
            const nextToken =
                raw?.pagination?.nextToken ||
                raw?.payload?.pagination?.nextToken ||
                null;

            const rows = (items || []).map((it) => {
                const sku = it?.sku || it?.summaries?.[0]?.sku || null;
                const asin =
                    it?.asin ||
                    it?.summaries?.[0]?.asin ||
                    it?.attributes?.asin?.[0]?.value ||
                    null;

                const title =
                    it?.summaries?.[0]?.itemName ||
                    it?.attributes?.item_name?.[0]?.value ||
                    it?.attributes?.title?.[0]?.value ||
                    null;

                const status =
                    it?.summaries?.[0]?.status ||
                    it?.summaries?.[0]?.listingStatus ||
                    it?.status ||
                    null;

                const lastUpdatedDate =
                    it?.summaries?.[0]?.lastUpdatedDate ||
                    it?.summaries?.[0]?.lastUpdated ||
                    null;

                const image =
                    it?.summaries?.[0]?.mainImage?.link ||
                    it?.attributes?.main_image?.[0]?.value?.link ||
                    null;

                const conditionType =
                    it?.summaries?.[0]?.conditionType ||
                    it?.attributes?.condition_type?.[0]?.value ||
                    null;

                const fa = it?.fulfillmentAvailability || it?.attributes?.fulfillment_availability || [];
                const faArr = Array.isArray(fa) ? fa : [];

                const fbmEntry = faArr.find(x => x?.fulfillmentChannelCode === "DEFAULT");
                const currentQty = fbmEntry?.quantity ?? null;

                const fbaEntries = faArr.filter(x => x?.fulfillmentChannelCode && x?.fulfillmentChannelCode !== "DEFAULT");
                const fbaQty = fbaEntries.reduce((sum, x) => sum + (Number(x?.quantity) || 0), 0);

                const hasFBM = !!fbmEntry;
                const hasFBA = fbaEntries.length > 0;

                const offers = it?.offers || [];
                const currentPrice =
                    offers?.[0]?.price?.amount ??
                    offers?.[0]?.listingPrice?.amount ??
                    null;

                const currency =
                    offers?.[0]?.price?.currencyCode ||
                    offers?.[0]?.listingPrice?.currencyCode ||
                    "USD";

                const issues = it?.issues || [];

                const imsQty = it?.ims?.count ?? null;
                const imsMatchedBy = it?.ims?.matchedBy ?? null;

                const fnsku =
                    it?.summaries?.[0]?.fnSku ||
                    it?.attributes?.fulfillment_availability?.[0]?.fnsku || // fallback if ever present
                    null;

                return {
                    raw: it,
                    sku,
                    asin,
                    fnsku,
                    title,
                    image,
                    status,
                    lastUpdatedDate,
                    conditionType,
                    imsQty,
                    imsQtyDisplay: (imsQty === null || imsQty === undefined) ? "—" : imsQty,
                    imsMatchedBy,
                    currentQty,
                    fbaQty,
                    fbaQtyDisplay: hasFBA ? fbaQty : "—",
                    fbaChannels: fbaEntries.map(x => x.fulfillmentChannelCode).slice(0, 2),
                    hasFBM,
                    hasFBA,
                    currentPrice,
                    currency,
                    issues,
                    newQty: "",
                    newPrice: "",
                    _touchedQty: false,
                    _touchedPrice: false,
                    _savingQty: false,
                    _savingPrice: false,
                    _savedQty: false,
                    _savedPrice: false,
                    _errorQty: "",
                    _errorPrice: "",
                    _saveTimer: null,
                };
            });

            return { rows, nextToken };
        },

        isValidInt(v) {
            if (v === null || v === undefined || v === "") return false;
            const n = Number(v);
            return Number.isInteger(n) && n >= 0;
        },

        isValidMoney(v) {
            if (v === null || v === undefined || v === "") return false;
            const n = Number(v);
            return !Number.isNaN(n) && n >= 0;
        },

        applyUpdates() {
            const updates = this.rows
                .filter(r => this.isValidInt(r.newQty) || this.isValidMoney(r.newPrice))
                .map(r => ({
                    sku: r.sku,
                    quantity: this.isValidInt(r.newQty) ? Number(r.newQty) : null,
                    price: this.isValidMoney(r.newPrice) ? Number(r.newPrice) : null,
                    currency: r.currency || "USD",
                    asin: r.asin || null,
                }));

            this.$emit("applied", {
                store: this.filters.store,
                marketplaceIds: this.filters.marketplaceIds,
                updates,
            });
        },

        markTouched(row, field) {
            if (field === "qty") row._touchedQty = true;
            if (field === "price") row._touchedPrice = true;
        },

        clearField(row, field) {
            if (field === "qty") {
                row.newQty = "";
                row._touchedQty = true;
            }
            if (field === "price") {
                row.newPrice = "";
                row._touchedPrice = true;
            }
            this.queueAutoSave(row);
        },

        queueAutoSave(row) {
            if (row._saveTimer) clearTimeout(row._saveTimer);
            row._saveTimer = setTimeout(() => this.autoSaveRow(row), 450);
        },

        parseQty(v) {
            if (v === null || v === undefined) return { value: null, cleared: true };
            const s = String(v).trim();
            if (s === "") return { value: null, cleared: true };
            const n = Number(s);
            if (!Number.isFinite(n)) return { value: null, invalid: true };
            if (!Number.isInteger(n) || n < 0) return { value: null, invalid: true };
            return { value: n, cleared: false };
        },

        parsePrice(v) {
            if (v === null || v === undefined) return { value: null, cleared: true };
            const s = String(v).trim();
            if (s === "") return { value: null, cleared: true };
            const n = Number(s);
            if (!Number.isFinite(n) || n < 0) return { value: null, invalid: true };
            return { value: n, cleared: false };
        },

        autoDismissError(row, field) {
            const key = field === "qty" ? "_errorQty" : "_errorPrice";
            if (!row[key]) return;

            if (!row._errorTimer) row._errorTimer = {};
            if (row._errorTimer[field]) clearTimeout(row._errorTimer[field]);

            row._errorTimer[field] = setTimeout(() => {
                row[key] = "";
            }, 5000);
        },

        async autoSaveRow(row) {
            const touched = row._touchedQty || row._touchedPrice;
            if (!touched || this._suppressAutosave) return;

            const qty = row._touchedQty ? this.parseQty(row.newQty) : null;
            const price = row._touchedPrice ? this.parsePrice(row.newPrice) : null;

            if (qty?.invalid) { row._errorQty = "Invalid quantity"; return; }
            if (price?.invalid) { row._errorPrice = "Invalid price"; return; }

            if (row._touchedQty && qty?.cleared && (row.currentQty === null || row.currentQty === undefined || row.currentQty === "")) {
                row._touchedQty = false;
                row._errorQty = "";
                row.newQty = "";
            }

            if (row._touchedPrice && price?.cleared && (row.currentPrice === null || row.currentPrice === undefined || row.currentPrice === "")) {
                row._touchedPrice = false;
                row._errorPrice = "";
                row.newPrice = "";
            }

            if (!row._touchedQty && !row._touchedPrice) return;

            row._errorQty = "";
            row._errorPrice = "";
            row._savedQty = false;
            row._savedPrice = false;

            if (row._touchedQty) row._savingQty = true;
            if (row._touchedPrice) row._savingPrice = true;

            try {
                const payload = {
                    store: this.filters.store,
                    marketplaceIds: this.filters.marketplaceIds,
                    sku: row.sku,
                    asin: row.asin,
                    ...(row._touchedQty ? { quantity: qty.value, quantityCleared: qty.cleared } : {}),
                    ...(row._touchedPrice ? { price: price.value, priceCleared: price.cleared, currency: row.currency || "USD" } : {}),
                };

                await axios.post(`${API_BASE_URL}/amazon/listings/update-one`, payload);

                if (row._touchedQty) {
                    row._savedQty = true;
                    row._touchedQty = false;
                    row.currentQty = qty.cleared ? null : qty.value;
                    row.newQty = "";
                }

                if (row._touchedPrice) {
                    row._savedPrice = true;
                    row._touchedPrice = false;
                    row.currentPrice = price.cleared ? null : price.value;
                    row.newPrice = "";
                }

                setTimeout(() => {
                    row._savedQty = false;
                    row._savedPrice = false;
                }, 1200);

            } catch (err) {
                const isEmptyPatchError =
                    err?.response?.data?.errors?.some(e =>
                        String(e?.message || "").toLowerCase().includes("invalid empty value")
                    );

                if (isEmptyPatchError && ((qty && qty.cleared) || (price && price.cleared))) {
                    if (row._touchedQty && qty?.cleared) { row._touchedQty = false; row.newQty = ""; }
                    if (row._touchedPrice && price?.cleared) { row._touchedPrice = false; row.newPrice = ""; }
                    return;
                }

                const msg = err?.response?.data?.message || err?.response?.data?.error || "Save failed";

                if (row._touchedPrice) {
                    row._errorPrice = msg;
                    this.resetRowStatus(row, "price");
                }

                if (row._touchedQty) {
                    row._errorQty = msg;
                    this.resetRowStatus(row, "qty");
                }
            } finally {
                row._savingQty = false;
                row._savingPrice = false;
            }
        },

        openIssuesModal(row) {
            this.issuesModal = {
                visible: true,
                sku: row?.sku || null,
                asin: row?.asin || null,
                title: row?.title || null,
                issues: Array.isArray(row?.issues) ? row.issues : [],
                raw: row?.raw || null,
            };
        },

        async copyIssuesToClipboard() {
            try {
                const lines = (this.issuesModal.issues || []).map((it, idx) => {
                    const msg = it.message || it.summary || "";
                    const code = it.code ? `(${it.code})` : "";
                    return `#${idx + 1} ${code} ${msg}`.trim();
                });

                const header = `SKU: ${this.issuesModal.sku || "—"} | ASIN: ${this.issuesModal.asin || "—"}\n`;
                const text = header + lines.join("\n");

                await navigator.clipboard.writeText(text);
            } catch (e) {
                console.error("copyIssuesToClipboard failed:", e);
            }
        },

        resetRowStatus(row, field) {
            const isQty = field === "qty";
            const savingKey = isQty ? "_savingQty" : "_savingPrice";
            const savedKey = isQty ? "_savedQty" : "_savedPrice";
            const errorKey = isQty ? "_errorQty" : "_errorPrice";

            row[savingKey] = false;

            if (row[savedKey]) {
                setTimeout(() => {
                    row[savedKey] = false;
                }, 1500);
            }

            if (row[errorKey]) {
                setTimeout(() => {
                    row[errorKey] = "";
                }, 5000);
            }
        },

        async openAutomationModal() {
            const store = this.filters.store || "Renovartech";

            this.automationModal.visible = true;
            this.automationModal.store = store;

            if (!Array.isArray(this.automationModal.marketplaceIds) || !this.automationModal.marketplaceIds.length) {
                this.automationModal.marketplaceIds = ["ATVPDKIKX0DER"];
            }

            await this.loadAutomationList();
            this.newAutomation();
        },

        async loadAutomationList() {
            const a = this.automationModal;
            a.loading = true;

            try {
                const res = await axios.get(`${API_BASE_URL}/amazon/paa/automations`, {
                    params: { store: a.store }
                });

                const rows = res?.data?.rows || [];

                a.list = rows.map(x => ({
                    id: x.id,
                    label: `#${x.id} • ${x.name || "Unnamed"} • ${Number(x.is_enabled) ? "ON" : "OFF"}`
                }));
            } catch (err) {
                console.error("loadAutomationList error:", err?.response?.data || err);
                a.list = [];
            } finally {
                a.loading = false;
            }
        },

        async removeAssigned(row) {
            const id = row?.assigned_item_id;
            if (!id) return;

            try {
                await axios.delete(`${API_BASE_URL}/amazon/paa/assigned-items/${id}`);

                this.automationModal.selectedRows =
                    (this.automationModal.selectedRows || []).filter(x => x.assigned_item_id !== id);
            } catch (err) {
                console.error("removeAssigned error:", err?.response?.data || err);
            }
        },

        safeJsonArray(v) {
            try {
                if (Array.isArray(v)) return v;
                if (typeof v === "string") return JSON.parse(v);
                return null;
            } catch {
                return null;
            }
        },

        safeJsonObject(v) {
            try {
                if (v && typeof v === "object" && !Array.isArray(v)) return v;
                if (typeof v === "string") return JSON.parse(v);
                return null;
            } catch {
                return null;
            }
        },

        newAutomation() {
            const a = this.automationModal;

            a.selectedAutomationId = null;
            a.id = null;
            a.name = "";
            a.timezone = "America/Los_Angeles";
            a.isEnabled = 1;
            a.triggers = ["09:00"];
            a.rules = [
                {
                    start: "09:00",
                    end: "10:00",
                    min: "",
                    max: "",
                    delta: "",
                }
            ];
            a.defaultDelta = "0";
            a.assigned = [];
            a.selectedRows = [];
        },

        async saveAutomation() {
            const a = this.automationModal;
            a.saving = true;

            try {
                const payload = {
                    id: a.id,
                    name: a.name,
                    store: a.store,
                    marketplace_ids: a.marketplaceIds,
                    timezone: a.timezone,
                    is_enabled: a.isEnabled ? 1 : 0,
                    rules: (a.rules || []).map(r => ({
                        start: String(r.start || "").trim(),
                        end: String(r.end || "").trim(),
                        min: Number(r.min),
                        max: Number(r.max),
                        delta: Number(r.delta),
                    })),
                    default_delta: Number(a.defaultDelta || 0),
                };

                const res = await axios.post(`${API_BASE_URL}/amazon/paa/save`, payload);
                const savedId = res?.data?.id || res?.data?.automation_id || a.id;

                await this.loadAutomationList();

                if (savedId) {
                    a.selectedAutomationId = savedId;
                    await this.onSelectAutomation();
                }
            } catch (err) {
                console.error("saveAutomation error:", err?.response?.data || err);
            } finally {
                a.saving = false;
            }
        },

        async onSelectAutomation() {
            const a = this.automationModal;
            const id = a.selectedAutomationId;
            if (!id) return;

            a.loading = true;
            try {
                const res = await axios.get(`${API_BASE_URL}/amazon/paa/automations/${id}`);
                const row = res?.data?.automation || {};
                const items = res?.data?.items || [];

                a.id = row.id || null;
                a.name = row.name || "";
                a.store = row.store || "Renovartech";
                a.marketplaceIds = this.safeJsonArray(row.marketplace_ids) || ["ATVPDKIKX0DER"];
                a.timezone = row.timezone || "America/Los_Angeles";
                a.isEnabled = Number(row.is_enabled) ? 1 : 0;

                a.rules = this.safeJsonArray(row.rules) || [
                    {
                        start: "09:00",
                        end: "10:00",
                        min: "",
                        max: "",
                        delta: "",
                    }
                ];

                a.defaultDelta = String(row.default_delta ?? "0");

                a.assigned = items;

                a.selectedRows = items
                    .filter(x => Number(x.is_active) === 1)
                    .map(x => ({
                        assigned_item_id: x.id,
                        MSKU: x.msku,
                        FNSKU: x.fnsku || null,
                        ASIN: x.asin || null,
                        storename: x.storename || a.store,
                    }));

            } catch (err) {
                console.error("load automation error:", err?.response?.data || err);
            } finally {
                a.loading = false;
            }
        },

        async deleteAutomation() {
            const a = this.automationModal;
            if (!a.id) return;

            if (!confirm(`Delete automation #${a.id}? This will remove its MSKU list and runs/items via FK cascade.`)) return;

            a.deleting = true;
            try {
                await axios.delete(`${API_BASE_URL}/amazon/paa/automations/${a.id}`);
                await this.loadAutomationList();
                this.newAutomation();
            } catch (err) {
                console.error("delete automation error:", err?.response?.data || err);
            } finally {
                a.deleting = false;
            }
        },

        addTrigger() {
            this.automationModal.triggers.push("09:00");
        },

        removeTrigger(idx) {
            if (this.automationModal.triggers.length <= 1) return;
            this.automationModal.triggers.splice(idx, 1);
        },

        addRule() {
            this.automationModal.rules.push({
                start: "",
                end: "",
                min: "",
                max: "",
                delta: "",
            });
        },

        removeRule(idx) {
            if (this.automationModal.rules.length <= 1) return;
            this.automationModal.rules.splice(idx, 1);
        },

        sortRules() {
            const toNum = (v) => {
                const n = Number(String(v ?? "").trim());
                return Number.isFinite(n) ? n : Number.POSITIVE_INFINITY;
            };

            this.automationModal.rules.sort((a, b) => toNum(a.min) - toNum(b.min));
        },

        async openAssignAutomationModal() {
            if (!this.filters.store || !this.listingSelectedRows.length) return;

            this.assignAutomationModal.visible = true;
            this.assignAutomationModal.selectedAutomationId = null;
            this.assignAutomationModal.loading = true;

            try {
                const res = await axios.get(`${API_BASE_URL}/amazon/paa/automations`, {
                    params: { store: this.filters.store }
                });

                const rows = res?.data?.rows || [];

                this.assignAutomationModal.list = rows.map(x => ({
                    id: x.id,
                    label: `#${x.id} • ${x.name || "Unnamed"} • ${Number(x.is_enabled) ? "ON" : "OFF"}`
                }));
            } catch (err) {
                console.error("openAssignAutomationModal error:", err?.response?.data || err);
                this.assignAutomationModal.list = [];
            } finally {
                this.assignAutomationModal.loading = false;
            }
        },

        async saveSelectedListingsToAutomation() {
            const automationId = this.assignAutomationModal.selectedAutomationId;
            if (!automationId || !this.listingSelectedRows.length) return;

            this.assignAutomationModal.saving = true;

            try {
                const items = (this.listingSelectedRows || [])
                    .filter(r => String(r.sku || "").trim() !== "")
                    .map(r => ({
                        msku: String(r.sku || "").trim(),
                        storename: String(this.filters.store || "").trim(),
                        asin: r.asin || null,
                    }));

                await axios.post(`${API_BASE_URL}/amazon/paa/assign-items`, {
                    automation_id: automationId,
                    store: this.filters.store,
                    items,
                });

                this.assignAutomationModal.visible = false;
                this.assignAutomationModal.selectedAutomationId = null;
                this.listingSelectedRows = [];
            } catch (err) {
                console.error("saveSelectedListingsToAutomation error:", err?.response?.data || err);
            } finally {
                this.assignAutomationModal.saving = false;
            }
        },
    },


};
</script>

<style scoped>
.toolbar {
    padding: 12px;
    border-bottom: 1px solid var(--surface-border);
}

.toolbar__row {
    display: flex;
    align-items: flex-end;
    gap: 12px;
}

.toolbar__row--secondary {
    margin-top: 10px;
}

.toolbar__label {
    display: block;
    font-size: 12px;
    color: var(--text-color-secondary);
    margin-bottom: 6px;
}

.toolbar__hint {
    display: block;
    font-size: 11px;
    color: var(--text-color-secondary);
    margin-top: 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.toolbar__field {
    display: flex;
    flex-direction: column;
    min-width: 0;
    /* IMPORTANT: lets flex children shrink instead of forcing wrap */
}

.toolbar__field--store {
    width: 180px;
    flex: 0 0 180px;
}

.toolbar__field--idtype {
    width: 140px;
    flex: 0 0 140px;
}

/* Search flexes */
.toolbar__field--search {
    flex: 1 1 auto;
    min-width: 260px;
}

.toolbar__actions {
    display: flex;
    gap: 8px;
    flex: 0 0 auto;
    align-items: flex-end;
}

/* Secondary row small fields */
.toolbar__field--small {
    width: 160px;
    flex: 0 0 160px;
}

.toolbar__spacer {
    flex: 1 1 auto;
}

.toolbar__pager {
    display: flex;
    gap: 8px;
    flex: 0 0 auto;
}

/* Mobile: stack cleanly */
@media (max-width: 768px) {
    .toolbar__row {
        flex-wrap: wrap;
    }

    .toolbar__field--store,
    .toolbar__field--idtype,
    .toolbar__field--small {
        width: 100%;
        flex: 1 1 100%;
    }

    .toolbar__actions,
    .toolbar__pager {
        width: 100%;
        justify-content: flex-end;
    }
}

/* Your existing table scroll area */
.table-wrap {
    flex: 1;
    min-height: 0;
    overflow: auto;
}

/* Compact PrimeVue dropdown */
:deep(.p-dropdown) {
    height: 34px !important;
    min-height: 34px !important;
    font-size: 13px;
}

:deep(.p-dropdown-label) {
    padding: 6px 10px !important;
    line-height: 20px;
}

:deep(.p-dropdown-trigger) {
    width: 34px !important;
}

/* Compact input text */
:deep(.p-inputtext) {
    height: 34px !important;
    padding: 6px 10px !important;
    font-size: 13px;
}

/* Compact buttons */
:deep(.p-button.p-button-sm) {
    height: 34px;
    padding: 0 12px;
    font-size: 13px;
}

/* Make labels smaller and tighter */
.toolbar__label {
    font-size: 11px;
    margin-bottom: 4px;
    color: var(--text-color-secondary);
}

.row-status {
    width: 20px;
    text-align: right;
    color: var(--text-color-secondary);
}

.compact-input {
    height: 32px !important;
    padding: 6px 10px !important;
    font-size: 13px;
}

.issues-cell {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.issues-tag {
    cursor: pointer;
    user-select: none;
}

.truncate-issue {
    max-width: 220px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Issues modal layout */
.issues-modal-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--surface-border);
}

.issues-modal-body {
    padding: 14px 16px;
    max-height: 65vh;
    overflow: auto;
}

.issues-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.issues-item {
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    padding: 12px;
    background: var(--surface-0);
}

.issues-item-top {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.issues-index {
    font-weight: 600;
    color: var(--text-color);
}

.issues-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 12px;
    color: var(--text-color-secondary);
}

.issues-message {
    font-size: 14px;
    line-height: 1.4;
    word-break: break-word;
    overflow-wrap: anywhere;
}

.issues-meta {
    margin-top: 8px;
    font-size: 12px;
    color: var(--text-color-secondary);
}

.issues-modal-foot {
    padding: 12px 16px;
    border-top: 1px solid var(--surface-border);
    display: flex;
    justify-content: flex-end;
}

/* Prevent table content from stretching layout */
:deep(.p-datatable td) {
    vertical-align: top;
    max-width: 320px;
    overflow: hidden;
}

:deep(.p-datatable td .error-chip) {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #fff4f4;
    color: #c0392b;
    border: 1px solid #f5c6cb;
    border-radius: 6px;
    padding: 4px 8px;
    font-size: 12px;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Hard truncate any unexpected long text */
.text-red-500 {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.auto-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--surface-border);
}

.auto-body {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 12px;
    padding: 12px;
}

.auto-left,
.auto-right {
    min-width: 0;
}

.auto-card {
    border: 1px solid var(--surface-border);
    border-radius: 12px;
    background: var(--surface-0);
    padding: 12px;
    margin-bottom: 12px;
}

.auto-card-title {
    font-weight: 600;
    margin-bottom: 10px;
}

.auto-form {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.auto-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.auto-label {
    font-size: 11px;
    color: var(--text-color-secondary);
}

.auto-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.auto-field-actions {
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
}

.auto-pager {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 10px;
}

.auto-assigned-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.auto-assigned-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 55vh;
    overflow: auto;
    padding-right: 4px;
}

.auto-assigned-item {
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    padding: 10px;
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.auto-foot {
    padding: 12px 16px;
    border-top: 1px solid var(--surface-border);
    display: flex;
    justify-content: flex-end;
}

@media (max-width: 980px) {
    .auto-body {
        grid-template-columns: 1fr;
    }
}

.trigger-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.trigger-row {
    display: flex;
    gap: 8px;
    align-items: center;
}

.trigger-input {
    width: 120px;
}

.rules-table {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.rules-head,
.rules-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr 44px;
    gap: 8px;
    align-items: center;
}

.rules-head {
    font-size: 11px;
    color: var(--text-color-secondary);
}

.rules-col-actions {
    text-align: right;
}

.automation-pricing-modal .auto-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #e5e7eb;
    background: #fff;
}

.automation-pricing-modal .auto-head-left {
    flex: 1;
    min-width: 0;
}

.automation-pricing-modal .auto-head-right {
    flex-wrap: wrap;
    justify-content: flex-end;
}

.automation-pricing-modal .auto-body {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 360px;
    gap: 1rem;
    padding: 1rem 1.25rem;
    background: #f8fafc;
    max-height: 70vh;
    overflow: auto;
}

.automation-pricing-modal .auto-left,
.automation-pricing-modal .auto-right {
    min-width: 0;
}

.automation-pricing-modal .auto-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.automation-pricing-modal .auto-card-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.automation-pricing-modal .auto-card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.automation-pricing-modal .auto-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.automation-pricing-modal .auto-field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.automation-pricing-modal .auto-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #374151;
}

.automation-pricing-modal .trigger-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.automation-pricing-modal .trigger-row {
    display: grid;
    grid-template-columns: 50px minmax(0, 1fr) 40px;
    gap: 0.5rem;
    align-items: center;
}

.automation-pricing-modal .trigger-index {
    font-size: 0.8rem;
    color: #6b7280;
    text-align: center;
}

.automation-pricing-modal .trigger-input {
    width: 100%;
}

.automation-pricing-modal .rules-table-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.automation-pricing-modal .rules-head,
.automation-pricing-modal .rules-row {
    display: grid;
    grid-template-columns: 110px 110px 1fr 1fr 1fr 70px;
    gap: 0.5rem;
    align-items: center;
}

.automation-pricing-modal .rules-head {
    font-size: 0.8rem;
    font-weight: 600;
    color: #6b7280;
    padding-bottom: 0.25rem;
    border-bottom: 1px solid #e5e7eb;
}

.automation-pricing-modal .rules-col.actions {
    text-align: center;
}

.automation-pricing-modal .rules-row {
    padding: 0.35rem 0;
}

.automation-pricing-modal .auto-assigned-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.automation-pricing-modal .auto-assigned-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-height: 420px;
    overflow: auto;
}

.automation-pricing-modal .auto-assigned-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.75rem;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 0.75rem;
    background: #fafafa;
}

.automation-pricing-modal .auto-assigned-main {
    min-width: 0;
    flex: 1;
}

.automation-pricing-modal .auto-summary {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.automation-pricing-modal .summary-row {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    font-size: 0.9rem;
}

.automation-pricing-modal .auto-foot {
    display: flex;
    justify-content: flex-end;
    padding: 1rem 1.25rem;
    border-top: 1px solid #e5e7eb;
    background: #fff;
}

@media (max-width: 960px) {
    .automation-pricing-modal .auto-body {
        grid-template-columns: 1fr;
    }

    .automation-pricing-modal .auto-form-grid {
        grid-template-columns: 1fr;
    }

    .automation-pricing-modal .rules-head,
    .automation-pricing-modal .rules-row {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .automation-pricing-modal .trigger-row {
        grid-template-columns: 40px minmax(0, 1fr) 40px;
    }
}
</style>