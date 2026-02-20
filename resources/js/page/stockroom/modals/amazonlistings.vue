<template>
    <Dialog v-model:visible="visibleProxy" modal :closable="true" :draggable="false" header="Amazon Listings"
        style="width: 95%; max-width: 80%;"
        :contentStyle="{ padding: '0', display: 'flex', flexDirection: 'column', height: '85vh' }" @hide="onClose">
        <!-- Toolbar (Amazon-ish) -->
        <div class="p-2 border-bottom-1 surface-border">
            <div class="toolbar-row">
                <div class="toolbar-field w-12 md:w-3">
                    <label class="block text-sm mb-1">Store</label>
                    <Dropdown v-model="filters.store" :options="storeOptions" optionLabel="label" optionValue="value"
                        placeholder="Select store" class="w-full p-inputtext-sm" />
                </div>

                <div class="toolbar-field w-12 md:w-2">
                    <label class="block text-sm mb-1">Identifier Type</label>
                    <Dropdown v-model="filters.identifiersType" :options="identifierTypeOptions" optionLabel="label"
                        optionValue="value" class="w-full p-inputtext-sm" />
                </div>

                <div class="toolbar-field w-12 md:w-5">
                    <label class="block text-sm mb-1">Search (comma / newline separated)</label>
                    <InputText v-model="filters.identifiersRaw" class="w-full p-inputtext-sm"
                        placeholder="Search SKU, ASIN, FNSKU, UPC/EAN..." @keyup.enter="runSearch(true)" />
                    <small class="text-500">Tip: paste multiple values separated by comma or new line</small>
                </div>

                <div class="toolbar-actions w-12 md:w-2">
                    <Button label="Search" class="p-button-sm" icon="pi pi-search" :loading="loading"
                        @click="runSearch(true)" />
                    <Button label="Reset" class="p-button-sm" icon="pi pi-refresh" severity="secondary"
                        :disabled="loading" @click="resetFilters" />
                </div>
            </div>

            <!-- Secondary row: sort/page size -->
            <div class="grid align-items-end mt-2">
                <div class="col-6 md:col-2">
                    <label class="block text-sm mb-1">Sort By</label>
                    <Dropdown v-model="filters.sortBy" :options="sortByOptions" optionLabel="label" optionValue="value"
                        class="w-full p-inputtext-sm" />
                </div>

                <div class="col-6 md:col-2">
                    <label class="block text-sm mb-1">Sort Order</label>
                    <Dropdown v-model="filters.sortOrder" :options="sortOrderOptions" optionLabel="label"
                        optionValue="value" class="w-full p-inputtext-sm" />
                </div>

                <div class="col-6 md:col-2">
                    <label class="block text-sm mb-1">Page Size</label>
                    <Dropdown v-model="filters.pageSize" :options="pageSizeOptions" optionLabel="label"
                        optionValue="value" class="w-full p-inputtext-sm" />
                </div>

                <div class="col-12 md:col-6 flex justify-content-end gap-2">
                    <Button label="Prev" icon="pi pi-angle-left" severity="secondary"
                        :disabled="loading || !page.prevToken" @click="goPrev" class="p-button-sm" />
                    <Button label="Next" iconPos="right" icon="pi pi-angle-right" severity="secondary"
                        :disabled="loading || !page.nextToken" @click="goNext" class="p-button-sm" />
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="p-0 table-wrap">
            <DataTable :value="rows" :loading="loading" dataKey="sku" responsiveLayout="scroll" class="p-datatable-sm">
                <Column header="Listing status" style="width: 180px;">
                    <template #body="{ data }">
                        <div class="font-medium">
                            <span class="inline-flex align-items-center gap-2">
                                <span class="status-dot"
                                    :class="data.status === 'ACTIVE' ? 'status-active' : 'status-other'"></span>
                                {{ data.status || '—' }}
                            </span>
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
                                    <span><b>SKU</b> {{ data.sku || '—' }}</span>
                                </div>
                                <div class="text-500 text-xs mt-1" v-if="data.conditionType">
                                    Condition: {{ data.conditionType }}
                                </div>
                            </div>
                        </div>
                    </template>
                </Column>

                <Column header="Inventory (FBM)" style="width: 220px;">
                    <template #body="{ data }">
                        <div class="text-sm">
                            <div class="mb-2">
                                <b>Available</b>:
                                <span class="ml-2">{{ data.currentQty ?? '—' }}</span>
                            </div>
                            <div class="flex align-items-center gap-2">
                                <InputText v-model="data.newQty" placeholder="Set qty" class="w-full p-inputtext-sm"
                                    style="width: 120px;" />
                                <Button icon="pi pi-times" severity="secondary" text v-tooltip.top="'Clear'"
                                    @click="data.newQty = ''" class="p-button-sm" />
                            </div>
                            <small class="text-500">Enter whole number (0 = out of stock)</small>
                        </div>
                    </template>
                </Column>

                <Column header="Price" style="width: 220px;">
                    <template #body="{ data }">
                        <div class="text-sm">
                            <div class="mb-2">
                                <b>Current</b>:
                                <span class="ml-2">{{ data.currentPrice ?? '—' }}</span>
                            </div>
                            <div class="flex align-items-center gap-2">
                                <InputText v-model="data.newPrice" placeholder="Set price" style="width: 120px;"
                                    class="w-full p-inputtext-sm" />
                                <span class="text-500">{{ data.currency || 'USD' }}</span>
                                <Button icon="pi pi-times" severity="secondary" text v-tooltip.top="'Clear'"
                                    @click="data.newPrice = ''" class="p-button-sm" />
                            </div>
                            <small class="text-500">No min/max fields (per your request)</small>
                        </div>
                    </template>
                </Column>

                <Column header="Issues" style="width: 320px; max-width: 320px;">
                    <template #body="{ data }">
                        <div class="issue-wrap" v-if="data.issues?.length">
                            <Tag severity="warning" :value="`${data.issues.length} issue(s)`" class="mb-2" />
                            <ul class="m-0 pl-3 text-sm text-700">
                                <li v-for="(it, idx) in data.issues.slice(0, 2)" :key="idx">
                                    {{ it.message || it.code || 'Issue' }}
                                </li>
                            </ul>
                            <div class="text-xs text-500 mt-1" v-if="data.issues.length > 2">
                                +{{ data.issues.length - 2 }} more
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
                <Button label="Apply Updates" icon="pi pi-upload" :disabled="!hasPendingChanges || loading"
                    @click="applyUpdates" class="p-button-sm" />
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
                stack: [], // store previous page tokens to support Prev
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
            return [10, 20].map(n => ({ label: String(n), value: n }));
        },
        hasPendingChanges() {
            return this.rows.some(r => this.isValidInt(r.newQty) || this.isValidMoney(r.newPrice));
        },
    },
    methods: {
        onClose() {
            this.visibleProxy = false;
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

            // push current token state for Prev behavior
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
                // identifiers not needed when paging token is used, but ok to include if you want consistency
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

        // ---- Mapping (Amazon JSON -> rows) ----
        mapSearchListingsResponse(raw) {
            // Amazon commonly returns: { items: [...], pagination: { nextToken } }
            const items = raw?.items || raw?.payload?.items || [];
            const nextToken = raw?.pagination?.nextToken || raw?.payload?.pagination?.nextToken || null;

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

                // fulfillmentAvailability often: [{ fulfillmentChannelCode: "DEFAULT", quantity: 7 }]
                const fa = it?.fulfillmentAvailability || it?.attributes?.fulfillment_availability || [];
                const currentQty =
                    Array.isArray(fa) ? (fa.find(x => x?.fulfillmentChannelCode === "DEFAULT")?.quantity ?? fa[0]?.quantity) : null;

                // offers sometimes: [{ price: { amount, currencyCode } }]
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

                return {
                    raw: it,

                    sku,
                    asin,
                    title,
                    image,
                    status,
                    lastUpdatedDate,
                    conditionType,

                    currentQty,
                    currentPrice,
                    currency,
                    issues,

                    // inputs
                    newQty: "",
                    newPrice: "",
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
            // This emits what you need for the next endpoint (PATCH quantities/prices)
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
    },
};
</script>

<style scoped>
.border-bottom-1 {
    border-bottom: 1px solid var(--surface-border);
}

.border-top-1 {
    border-top: 1px solid var(--surface-border);
}

.truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 620px;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    display: inline-block;
}

.status-active {
    background: #22c55e;
}

.status-other {
    background: #f59e0b;
}

/* Force the Issues column to never blow up the dialog width */
.issue-wrap,
.issue-wrap li {
    max-width: 100%;
    overflow: hidden;
    word-break: break-word;
    overflow-wrap: anywhere;
    /* breaks long strings like Amazon error codes */
    white-space: normal;
}

/* Optional: clamp each issue line so it doesn’t get tall */
.issue-wrap li {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    /* show at most 2 lines per issue */
    -webkit-box-orient: vertical;
}

.table-wrap {
    flex: 1;
    /* take remaining height */
    min-height: 0;
    /* IMPORTANT for flex scroll */
    overflow: auto;
    /* scroll only the table area */
}

.toolbar-row {
  display: flex;
  flex-wrap: wrap;      /* allows stacking on small screens */
  gap: 12px;
  align-items: flex-end;
}

.toolbar-field {
  min-width: 220px;     /* prevents tiny squished dropdowns */
}

.toolbar-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  align-items: flex-end;
  min-width: 220px;
}
</style>