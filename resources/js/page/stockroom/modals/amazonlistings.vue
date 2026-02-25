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
            <DataTable :value="rows" :loading="loading" dataKey="sku" responsiveLayout="scroll" class="p-datatable-sm">
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
                                    <span><b>SKU</b> {{ data.sku || '—' }}</span>
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
                        <div class="text-xs text-500" v-if="data.imsMatchedBy">
                            {{ data.imsMatchedBy }}
                        </div>
                    </template>
                </Column>

                <!-- NEW: FBA Qty column -->
                <Column header="FBA Qty" style="width: 140px;">
                    <template #body="{ data }">
                        <span class="text-sm font-medium">{{ data.fbaQtyDisplay }}</span>
                        <div class="text-xs text-500" v-if="data.fbaChannels?.length">
                            {{ data.fbaChannels.join(", ") }}
                        </div>
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

                            <div v-if="data._errorQty" class="error-chip" v-tooltip.top="data._errorQty">
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

                            <div v-if="data._errorQty" class="error-chip" v-tooltip.top="data._errorQty">
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

            issuesModal: {
                visible: false,
                sku: null,
                asin: null,
                title: null,
                issues: [],
                raw: null,
            },

            _suppressAutosave: false,
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
            return [10, 20, 30, 50, 100, 200].map(n => ({ label: String(n), value: n }));
        },
        hasPendingChanges() {
            return this.rows.some(r => this.isValidInt(r.newQty) || this.isValidMoney(r.newPrice));
        },
    },
    methods: {
        onClose() {
            this._suppressAutosave = true;

            // cancel any pending debounced saves & revert edits
            (this.rows || []).forEach(r => {
                if (r._saveTimer) clearTimeout(r._saveTimer);

                // revert editing state (do NOT touch currentQty/currentPrice)
                r.newQty = "";
                r.newPrice = "";
                r._touchedQty = false;
                r._touchedPrice = false;

                // clear UI statuses
                r._savingQty = false;
                r._savingPrice = false;
                r._savedQty = false;
                r._savedPrice = false;
                r._errorQty = "";
                r._errorPrice = "";
            });

            // close dialog
            this.visibleProxy = false;

            // re-enable autosave for next open
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

                // fulfillmentAvailability often: [{ fulfillmentChannelCode: "DEFAULT", quantity: 7 }]
                const fa = it?.fulfillmentAvailability || it?.attributes?.fulfillment_availability || [];
                const faArr = Array.isArray(fa) ? fa : [];

                // FBM quantity (DEFAULT)
                const fbmEntry = faArr.find(x => x?.fulfillmentChannelCode === "DEFAULT");
                const currentQty = fbmEntry?.quantity ?? null;

                // FBA quantity = sum of non-DEFAULT channels
                const fbaEntries = faArr.filter(x => x?.fulfillmentChannelCode && x?.fulfillmentChannelCode !== "DEFAULT");
                const fbaQty = fbaEntries.reduce((sum, x) => sum + (Number(x?.quantity) || 0), 0);

                const hasFBM = !!fbmEntry;
                const hasFBA = fbaEntries.length > 0;

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

                const imsQty = it?.ims?.count ?? null;
                const imsMatchedBy = it?.ims?.matchedBy ?? null;

                return {
                    raw: it,

                    sku,
                    asin,
                    title,
                    image,
                    status,
                    lastUpdatedDate,
                    conditionType,

                    // IMS qty
                    imsQty,
                    imsQtyDisplay: (imsQty === null || imsQty === undefined) ? "—" : imsQty,
                    imsMatchedBy,

                    // quantities
                    currentQty,
                    fbaQty,
                    fbaQtyDisplay: hasFBA ? fbaQty : "—",
                    fbaChannels: fbaEntries.map(x => x.fulfillmentChannelCode).slice(0, 2),
                    hasFBM,
                    hasFBA,

                    // pricing
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
            this.queueAutoSave(row); // clearing also autosaves
        },

        queueAutoSave(row) {
            // Debounce per-row so typing doesn't spam API
            if (row._saveTimer) clearTimeout(row._saveTimer);
            row._saveTimer = setTimeout(() => this.autoSaveRow(row), 450);
        },

        // Allow 0 and empty ("").
        // Empty means "clear" -> send null + a flag so backend can decide.
        parseQty(v) {
            if (v === null || v === undefined) return { value: null, cleared: true };
            const s = String(v).trim();
            if (s === "") return { value: null, cleared: true };
            const n = Number(s);
            if (!Number.isFinite(n)) return { value: null, invalid: true };
            // allow 0+
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

        catch(err) {
            const msg =
                err?.response?.data?.message ||
                err?.response?.data?.error ||
                err?.response?.data?.errors?.[0]?.message ||
                "Save failed";

            if (row._touchedQty) {
                row._errorQty = msg;
                this.autoDismissError(row, "qty");
            }

            if (row._touchedPrice) {
                row._errorPrice = msg;
                this.autoDismissError(row, "price");
            }
        },

        autoDismissError(row, field) {
            const key = field === "qty" ? "_errorQty" : "_errorPrice";

            if (!row[key]) return;

            // clear any existing timer
            if (!row._errorTimer) row._errorTimer = {};
            if (row._errorTimer[field]) {
                clearTimeout(row._errorTimer[field]);
            }

            row._errorTimer[field] = setTimeout(() => {
                row[key] = "";
            }, 5000); // 5 seconds
        },

        async autoSaveRow(row) {
            const touched = row._touchedQty || row._touchedPrice;
            if (!touched) return;

            // if we're closing, cancel autosave
            if (this._suppressAutosave) return;

            const qty = row._touchedQty ? this.parseQty(row.newQty) : null;
            const price = row._touchedPrice ? this.parsePrice(row.newPrice) : null;

            // client validation
            if (qty?.invalid) { row._errorQty = "Invalid quantity"; return; }
            if (price?.invalid) { row._errorPrice = "Invalid price"; return; }

            // ---- NEW: skip "empty -> empty" ----
            // qty cleared AND already null/empty in current display => do nothing, no errors
            if (row._touchedQty && qty?.cleared && (row.currentQty === null || row.currentQty === undefined || row.currentQty === "")) {
                row._touchedQty = false;
                row._errorQty = "";
                row.newQty = "";
            }

            // price cleared AND already null/empty => do nothing, no errors
            if (row._touchedPrice && price?.cleared && (row.currentPrice === null || row.currentPrice === undefined || row.currentPrice === "")) {
                row._touchedPrice = false;
                row._errorPrice = "";
                row.newPrice = "";
            }

            // After skipping no-op clears, if nothing is still touched, bail out.
            if (!row._touchedQty && !row._touchedPrice) return;

            // clear old states
            row._errorQty = "";
            row._errorPrice = "";
            row._savedQty = false;
            row._savedPrice = false;

            if (row._touchedPrice) {
                row._savedPrice = true;
            }
            if (row._touchedQty) {
                row._savedQty = true;
            }

            this.resetRowStatus(row, row._touchedPrice ? "price" : "qty");

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

                // success UI updates...
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
                // ---- NEW: If cleared + amazon complains, don't show error (treat as no-op) ----
                const isEmptyPatchError =
                    err?.response?.data?.errors?.some(e => String(e?.message || "").toLowerCase().includes("invalid empty value"));

                if (isEmptyPatchError && ((qty && qty.cleared) || (price && price.cleared))) {
                    // silently cancel changes
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
                    const msg = it.message || it.summary || '';
                    const code = it.code ? `(${it.code})` : '';
                    return `#${idx + 1} ${code} ${msg}`.trim();
                });

                const header = `SKU: ${this.issuesModal.sku || '—'} | ASIN: ${this.issuesModal.asin || '—'}\n`;
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

            // stop spinner immediately
            row[savingKey] = false;

            // clear success after 1.5s
            if (row[savedKey]) {
                setTimeout(() => {
                    row[savedKey] = false;
                }, 1500);
            }

            // clear error after 5s
            if (row[errorKey]) {
                setTimeout(() => {
                    row[errorKey] = "";
                }, 5000);
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
</style>