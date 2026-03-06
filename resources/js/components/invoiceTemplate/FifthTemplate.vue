<template>
    <div class="invoice-wrapper">
        <div v-if="suppliers.length > 1" class="supplier-tabs">
            <button
                v-for="(s, i) in suppliers" :key="i"
                :class="['tab-btn', { active: currentIndex === i }]"
                @click="currentIndex = i"
            >{{ s.name }}</button>
        </div>

        <div ref="wrapperRef" class="invoice-outer">
            <div class="invoice-scaler" ref="scalerRef">

                <div
                    v-for="(page, pi) in pages" :key="pi"
                    class="invoice"
                    :class="{ 'page-break': pi < pages.length - 1 }"
                >

                    <!-- Company header (every page) -->
                    <div class="company-header">
                        <div class="ch-logo-wrap">
                            <img :src="logoSrc" alt="Logo" class="ch-logo" />
                        </div>
                        <div class="ch-info">
                            <div class="ch-title">{{ title }}</div>
                            <div class="ch-warranty">{{ warrantyText(warrantyFrom, warrantyFromUnit) }} &mdash; {{ warrantyText(warrantyTo, warrantyToUnit) }} Warranty</div>
                            <div class="ch-contacts">
                                <span>{{ ownerWebsite }}</span>
                                <span class="sep">|</span>
                                <span>{{ ownerEmail }}</span>
                                <span class="sep">|</span>
                                <span>{{ ownerContact }}</span>
                            </div>
                            <div class="ch-address">{{ ownerAddress }}</div>
                        </div>
                        <div class="ch-invoice-label">
                            <div class="ch-inv-text">INVOICE</div>
                        </div>
                    </div>

                    <!-- ── PAGE 1: meta strip + parties ── -->
                    <template v-if="pi === 0">
                        <div class="meta-strip">
                            <div class="meta-item" v-if="trackingNumber">
                                <span class="meta-label">Tracking #</span>
                                <span class="meta-val">{{ trackingNumber }}</span>
                            </div>
                            <div class="meta-item" v-if="orderNumber">
                                <span class="meta-label">Order #</span>
                                <span class="meta-val">{{ orderNumber }}</span>
                            </div>
                            <div class="meta-spacer" />
                            <div class="meta-item">
                                <span class="meta-label">Invoice Date</span>
                                <span class="meta-val">{{ invoiceDate }}</span>
                            </div>
                            <div class="meta-divider" />
                            <div class="meta-item">
                                <span class="meta-label">Due Date</span>
                                <span class="meta-val">{{ dueDate }}</span>
                            </div>
                        </div>

                        <div class="invoice-parties">
                            <div class="party-block">
                                <div class="party-label">BILL TO</div>
                                <div v-if="billToName"     class="party-name">{{ billToName }}</div>
                                <div v-if="billToAddress1" class="party-text">{{ billToAddress1 }}</div>
                                <div v-if="billToAddress2" class="party-text">{{ billToAddress2 }}</div>
                                <div v-if="billToContact"  class="party-text">{{ billToContact }}</div>
                            </div>
                            <div class="party-block">
                                <div class="party-label">SHIP TO</div>
                                <div v-if="shipToName"     class="party-name">{{ shipToName }}</div>
                                <div v-if="shipToAddress1" class="party-text">{{ shipToAddress1 }}</div>
                                <div v-if="shipToAddress2" class="party-text">{{ shipToAddress2 }}</div>
                                <div v-if="shipToContact"  class="party-text">{{ shipToContact }}</div>
                                <div v-if="shipToEmail"    class="party-text">{{ shipToEmail }}</div>
                            </div>
                            <div class="party-block">
                                <div class="party-label">PAYMENT</div>
                                <div class="party-text">{{ currentSupplier.paymentType || 'Paypal' }}</div>
                            </div>
                        </div>
                    </template>

                    <!-- ── PAGE 2+: "— continued —" label ── -->
                    <div v-else class="continued-top">— continued —</div>

                    <!-- Product table (every page) -->
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th class="text-left">DESCRIPTION</th>
                                <th class="text-right">QTY</th>
                                <th class="text-right">UNIT PRICE</th>
                                <th class="text-right">SUBTOTAL</th>
                                <th class="text-right">TAX</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(p, i) in page.rows" :key="i" :class="{ alt: i % 2 !== 0 }">
                                <td>{{ p.ProductTitle }}</td>
                                <td class="text-right">{{ p.quantity }}</td>
                                <td class="text-right">{{ fmt(p.price) }}</td>
                                <td class="text-right">{{ fmt(p.totalPrice) }}</td>
                                <td class="text-right">{{ p.tax }}%</td>
                            </tr>
                            <template v-if="pi === pages.length - 1">
                                <tr
                                    v-for="n in emptyRows(page.rows.length)" :key="`e-${n}`"
                                    :class="{ alt: (page.rows.length + n - 1) % 2 !== 0 }"
                                >
                                    <td colspan="5">&nbsp;</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <!-- Footer: last page only -->
                    <div v-if="pi === pages.length - 1" class="invoice-footer">
                        <div class="footer-left">
                            <div class="footer-msg">THANK YOU FOR YOUR BUSINESS!</div>
                            <div class="footer-warranty">{{ warrantyText(warrantyFrom, warrantyFromUnit) }} &mdash; {{ warrantyText(warrantyTo, warrantyToUnit) }} Warranty Included</div>
                        </div>
                        <div class="footer-totals">
                            <div class="total-row">
                                <span>Subtotal</span><span>{{ fmt(subtotal) }}</span>
                            </div>
                            <div class="total-row">
                                <span>Tax ({{ taxRate }}%)</span><span>{{ fmt(taxAmount) }}</span>
                            </div>
                            <div class="total-grand">
                                <span>Total to Pay</span><span>{{ fmt(total) }}</span>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- end page loop -->

            </div>
        </div>
    </div>
</template>

<script>
const INVOICE_WIDTH    = 700;
const MIN_ROWS         = 8;
const ROWS_FIRST_PAGE  = 10;
const ROWS_OTHER_PAGES = 15;
const USD_FORMAT       = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });

export default {
    name: 'FifthTemplate',
    props: {
        suppliers:        { type: Array,            required: true },
        invoiceDate: {
            type: String,
            default: () => new Date().toLocaleDateString('en-US', { timeZone: 'America/Los_Angeles', month: '2-digit', day: '2-digit', year: 'numeric' }),
        },
        dueDate: {
            type: String,
            default: () => new Date(Date.now() + 15 * 864e5).toLocaleDateString('en-US', { timeZone: 'America/Los_Angeles', month: '2-digit', day: '2-digit', year: 'numeric' }),
        },
        logoSrc:          { type: String,           default: '/images/all-renewed-logo.png' },
        title:            { type: String,           default: 'ALL RENEWED ELECTRONICS' },
        warrantyFrom:     { type: [String, Number], default: 90 },
        warrantyFromUnit: { type: String,           default: 'days' },
        warrantyTo:       { type: [String, Number], default: 1 },
        warrantyToUnit:   { type: String,           default: 'years' },
        ownerAddress:     { type: String,           default: '4620 Northgate Blvd., Ste 180, Sacramento, CA 95834' },
        ownerContact:     { type: String,           default: '(415) 882-6949' },
        ownerEmail:       { type: String,           default: 'sales@allrenewed.com' },
        ownerWebsite:     { type: String,           default: 'www.allrenewed.com' },
        trackingNumber:   { type: String,           default: '' },
        orderNumber:      { type: String,           default: '' },
        billToName:       { type: String,           default: '' },
        billToAddress1:   { type: String,           default: '' },
        billToAddress2:   { type: String,           default: '' },
        billToContact:    { type: String,           default: '' },
        shipToName:       { type: String,           default: '' },
        shipToAddress1:   { type: String,           default: '' },
        shipToAddress2:   { type: String,           default: '' },
        shipToContact:    { type: String,           default: '' },
        shipToEmail:      { type: String,           default: '' },
    },
    data() { return { currentIndex: 0 }; },
    computed: {
        currentSupplier() { return this.suppliers[this.currentIndex] ?? null; },
        subtotal()        { return this.currentSupplier?.products.reduce((s, p) => s + p.totalPrice, 0) ?? 0; },
        taxRate()         { return this.currentSupplier?.products[0]?.tax ?? 0; },
        taxAmount()       { return this.subtotal * (this.taxRate / 100); },
        total()           { return this.subtotal + this.taxAmount; },
        pages() {
            const products = this.currentSupplier?.products ?? [];
            const chunks = [];
            let i = 0;
            while (i < products.length) {
                const limit = chunks.length === 0 ? ROWS_FIRST_PAGE : ROWS_OTHER_PAGES;
                chunks.push({ rows: products.slice(i, i + limit) });
                i += limit;
            }
            if (chunks.length === 0) chunks.push({ rows: [] });
            return chunks;
        },
    },
    methods: {
        fmt(n) { return USD_FORMAT.format(Number(n)); },
        warrantyText(value, unit) {
            const u = (unit ?? '').toLowerCase();
            return `${value} ${Number(value) === 1 ? u.replace(/s$/, '') : u}`;
        },
        emptyRows(rowCount) {
            return Math.max(0, MIN_ROWS - rowCount);
        },
        updateScale() {
            const scaler  = this.$refs.scalerRef;
            const wrapper = this.$refs.wrapperRef;
            if (!scaler || !wrapper) return;
            const scale = Math.min(1, wrapper.offsetWidth / INVOICE_WIDTH);
            scaler.style.width           = `${INVOICE_WIDTH}px`;
            scaler.style.transform       = `scale(${scale})`;
            scaler.style.transformOrigin = 'top left';
            wrapper.style.height         = `${scaler.offsetHeight * scale}px`;
        },
    },
    mounted() {
        this._ro = new ResizeObserver(this.updateScale);
        this._ro.observe(this.$refs.wrapperRef);
        this.updateScale();
    },
    beforeUnmount() { this._ro?.disconnect(); },
    watch: {
        suppliers() { this.currentIndex = 0; this.$nextTick(this.updateScale); },
    },
};
</script>

<style scoped>
.invoice-wrapper { font-family: Arial, sans-serif; font-size: 12px; width: 100%; }
.invoice-outer   { width: 100%; overflow: hidden; }

.supplier-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
.tab-btn { padding: 6px 16px; border-radius: 4px; border: 2px solid #1d4ed8; background: #fff; color: #1d4ed8; cursor: pointer; font-size: 12px; font-weight: 600; }
.tab-btn.active { background: #1d4ed8; color: #fff; }

/* Page separation */
.invoice-scaler { overflow: hidden; }
.invoice { width: 700px; box-sizing: border-box; background: #fff; border: 1px solid #dbeafe; }
.page-break { margin-bottom: 32px; border-bottom: 3px dashed #93c5fd; }

/* Company header (every page) */
.company-header { display: flex; align-items: stretch; background: #1e3a8a; border-bottom: 4px solid #3b82f6; }
.ch-logo-wrap { display: flex; align-items: center; justify-content: center; padding: 18px 20px; background: #fff; border-right: 4px solid #3b82f6; flex-shrink: 0; }
.ch-logo { width: 52px; height: auto; filter: brightness(0) saturate(100%) invert(21%) sepia(96%) saturate(1200%) hue-rotate(213deg); }
.ch-info { flex: 1; padding: 14px 20px; display: flex; flex-direction: column; justify-content: center; gap: 3px; }
.ch-title { font-size: 17px; font-weight: 900; color: #fff; letter-spacing: 1px; text-transform: uppercase; }
.ch-warranty { font-size: 10px; color: #93c5fd; font-weight: 600; letter-spacing: 0.4px; text-transform: uppercase; }
.ch-contacts { font-size: 10px; color: #bfdbfe; margin-top: 4px; display: flex; gap: 6px; flex-wrap: wrap; }
.sep { color: #3b82f6; }
.ch-address { font-size: 10px; color: #93c5fd; }
.ch-invoice-label { display: flex; align-items: center; justify-content: center; padding: 0 28px; border-left: 1px solid rgba(255,255,255,0.1); }
.ch-inv-text { font-size: 32px; font-weight: 900; letter-spacing: 8px; color: #fff; opacity: 0.6; text-transform: uppercase; }

/* "— continued —" label */
.continued-top { text-align: right; font-size: 11px; color: #9ca3af; padding: 6px 20px 0; font-style: italic; }

/* Meta strip (page 1 only) */
.meta-strip { display: flex; align-items: center; padding: 10px 20px; background: #eff6ff; border-bottom: 2px solid #bfdbfe; gap: 24px; }
.meta-item { display: flex; flex-direction: column; gap: 1px; }
.meta-label { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #3b82f6; font-weight: 700; }
.meta-val { font-size: 13px; font-weight: 800; color: #1e3a8a; }
.meta-spacer { flex: 1; }
.meta-divider { width: 1px; height: 30px; background: #bfdbfe; }

/* Parties (page 1 only) */
.invoice-parties { display: grid; grid-template-columns: 1fr 1fr 1fr; border-bottom: 2px solid #bfdbfe; }
.party-block { padding: 16px 20px; border-right: 1px solid #dbeafe; }
.party-block:last-child { border-right: none; }
.party-label { font-size: 9px; font-weight: 900; letter-spacing: 2px; color: #1d4ed8; text-transform: uppercase; margin-bottom: 6px; padding-bottom: 5px; border-bottom: 2px solid #bfdbfe; }
.party-name { font-weight: 700; font-size: 12px; color: #111; margin-bottom: 3px; }
.party-text { font-size: 11px; color: #6b7280; line-height: 1.7; }

/* Table */
.invoice-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.invoice-table th:first-child,  .invoice-table td:first-child  { width: 35%; white-space: normal; word-break: break-word; }
.invoice-table th:nth-child(2), .invoice-table td:nth-child(2) { width: 10%; }
.invoice-table th:nth-child(3), .invoice-table td:nth-child(3) { width: 20%; }
.invoice-table th:nth-child(4), .invoice-table td:nth-child(4) { width: 20%; }
.invoice-table th:nth-child(5), .invoice-table td:nth-child(5) { width: 15%; }
.invoice-table th, .invoice-table td { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.invoice-table th:first-child, .invoice-table td:first-child { white-space: normal; }
.invoice-table thead tr { background: #1d4ed8; }
.invoice-table th { padding: 10px 16px; font-size: 10px; font-weight: 800; letter-spacing: 1px; color: #fff; text-transform: uppercase; background-color: #1d4ed8; }
.invoice-table td { padding: 9px 16px; border-bottom: 1px solid #eff6ff; color: #374151; font-size: 11px; }
.alt td { background: #f0f7ff; }

/* Footer (last page only) */
.invoice-footer { display: flex; justify-content: space-between; align-items: center; background: #1e3a8a; padding: 16px 20px; gap: 20px; }
.footer-left { display: flex; flex-direction: column; gap: 4px; }
.footer-msg { font-size: 12px; font-weight: 900; color: #fff; letter-spacing: 1.5px; text-transform: uppercase; }
.footer-warranty { font-size: 10px; color: #93c5fd; }
.footer-totals { display: flex; flex-direction: column; gap: 4px; min-width: 220px; }
.total-row { display: flex; justify-content: space-between; font-size: 11px; color: #bfdbfe; padding: 3px 8px; border-bottom: 1px solid rgba(255,255,255,0.1); }
.total-grand { display: flex; justify-content: space-between; align-items: center; margin-top: 4px; padding: 8px 12px; background: #3b82f6; border-radius: 4px; color: #fff; font-weight: 900; font-size: 13px; }

.text-left  { text-align: left; }
.text-right { text-align: right; }
</style>