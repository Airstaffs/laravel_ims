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
                        <div class="ch-top">
                            <div class="ch-logo-wrap">
                                <img :src="logoSrc" alt="Logo" class="ch-logo" />
                            </div>
                            <div class="ch-divider-v" />
                            <div class="ch-title-wrap">
                                <div class="ch-title">{{ title }}</div>
                                <div class="ch-warranty">{{ warrantyText(warrantyFrom, warrantyFromUnit) }} to {{ warrantyText(warrantyTo, warrantyToUnit) }} Warranty</div>
                            </div>
                        </div>
                        <div class="ch-bottom">
                            <div class="ch-bottom-row">
                                <div class="ch-contact-group">
                                    <span class="ch-contact-label">Web</span>
                                    <span class="ch-contact-value">{{ ownerWebsite }}</span>
                                </div>
                                <div class="ch-sep" />
                                <div class="ch-contact-group">
                                    <span class="ch-contact-label">Email</span>
                                    <span class="ch-contact-value">{{ ownerEmail }}</span>
                                </div>
                                <div class="ch-sep" />
                                <div class="ch-contact-group">
                                    <span class="ch-contact-label">Tel</span>
                                    <span class="ch-contact-value">{{ ownerContact }}</span>
                                </div>
                            </div>
                            <div class="ch-bottom-row ch-bottom-row--address">
                                <div class="ch-contact-group">
                                    <span class="ch-contact-label">Address</span>
                                    <span class="ch-contact-value">{{ ownerAddress }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── PAGE 1: topbar + parties ── -->
                    <template v-if="pi === 0">
                        <div class="invoice-topbar">
                            <div class="topbar-accent" />
                            <div class="topbar-content">
                                <span class="invoice-label">INVOICE</span>
                            </div>
                            <div class="topbar-dates">
                                <div v-if="trackingNumber" class="date-item">
                                    <span class="date-label">Tracking #</span>
                                    <span class="date-val">{{ trackingNumber }}</span>
                                </div>
                                <div v-if="trackingNumber" class="date-divider" />
                                <div v-if="orderNumber" class="date-item">
                                    <span class="date-label">Order #</span>
                                    <span class="date-val">{{ orderNumber }}</span>
                                </div>
                                <div v-if="orderNumber" class="date-divider" />
                                <div class="date-item">
                                    <span class="date-label">Invoice Date</span>
                                    <span class="date-val">{{ invoiceDate }}</span>
                                </div>
                                <div class="date-divider" />
                                <div class="date-item">
                                    <span class="date-label">Due Date</span>
                                    <span class="date-val">{{ dueDate }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="invoice-parties">
                            <div class="party-divider" />
                            <div class="party-block">
                                <div class="party-label">BILL TO</div>
                                <div v-if="billToName"     class="party-name">{{ billToName }}</div>
                                <div v-if="billToAddress1" class="party-text">{{ billToAddress1 }}</div>
                                <div v-if="billToAddress2" class="party-text">{{ billToAddress2 }}</div>
                                <div v-if="billToContact"  class="party-text">{{ billToContact }}</div>
                            </div>
                            <div class="party-divider" />
                            <div class="party-block">
                                <div class="party-label">SHIP TO</div>
                                <div v-if="shipToName"     class="party-name">{{ shipToName }}</div>
                                <div v-if="shipToAddress1" class="party-text">{{ shipToAddress1 }}</div>
                                <div v-if="shipToAddress2" class="party-text">{{ shipToAddress2 }}</div>
                                <div v-if="shipToContact"  class="party-text">{{ shipToContact }}</div>
                                <div v-if="shipToEmail"    class="party-text">{{ shipToEmail }}</div>
                            </div>
                            <div class="party-divider" />
                            <div class="party-block">
                                <div class="party-label">PAYMENT DETAILS</div>
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
                            <tr v-for="(p, i) in page.rows" :key="i" :class="{ 'row-alt': i % 2 !== 0 }">
                                <td>{{ p.ProductTitle }}</td>
                                <td class="text-right">{{ p.quantity }}</td>
                                <td class="text-right">{{ fmt(p.price) }}</td>
                                <td class="text-right">{{ fmt(p.totalPrice) }}</td>
                                <td class="text-right">{{ p.tax }}%</td>
                            </tr>
                            <template v-if="pi === pages.length - 1">
                                <tr
                                    v-for="n in emptyRows(page.rows.length)" :key="`e-${n}`"
                                    :class="{ 'row-alt': (page.rows.length + n - 1) % 2 !== 0 }"
                                >
                                    <td colspan="5">&nbsp;</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <!-- Totals + footer: last page only -->
                    <template v-if="pi === pages.length - 1">
                        <div class="invoice-bottom">
                            <div class="invoice-totals">
                                <div class="total-line">
                                    <span>Subtotal</span>
                                    <span>{{ fmt(subtotal) }}</span>
                                </div>
                                <div class="total-line">
                                    <span>Tax ({{ taxRate }}%)</span>
                                    <span>{{ fmt(taxAmount) }}</span>
                                </div>
                                <div class="total-line grand">
                                    <span>Total to Pay</span>
                                    <span>{{ fmt(total) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="invoice-footer">
                            <span>THANK YOU FOR YOUR BUSINESS!</span>
                            <span class="footer-warranty">{{ warrantyText(warrantyFrom, warrantyFromUnit) }} – {{ warrantyText(warrantyTo, warrantyToUnit) }} Warranty</span>
                        </div>
                    </template>

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
    name: 'ThirdTemplate',
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
.invoice-wrapper { font-family: 'Arial', sans-serif; font-size: 12px; color: #222; width: 100%; }
.invoice-outer   { width: 100%; overflow: hidden; }

.supplier-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
.tab-btn { padding: 6px 16px; border-radius: 4px; border: 2px solid #059669; background: #fff; color: #059669; cursor: pointer; font-size: 12px; font-weight: 600; }
.tab-btn.active { background: #059669; color: #fff; }

/* Page separation */
.invoice-scaler { overflow: hidden; }
.invoice { background: #fff; border: 1px solid #d1fae5; width: 700px; box-sizing: border-box; overflow: hidden; }
.page-break { margin-bottom: 32px; border-bottom: 3px dashed #6ee7b7; }

/* Company header (every page) */
.company-header { border: 2px solid #059669; border-radius: 6px 6px 0 0; overflow: hidden; }
.ch-top { display: flex; align-items: center; padding: 14px 20px; background: #fff; border-bottom: 1px solid #d1fae5; }
.ch-logo-wrap { display: flex; align-items: center; justify-content: center; padding-right: 20px; flex-shrink: 0; }
.ch-logo { width: 56px; height: auto; filter: brightness(0) saturate(100%) invert(29%) sepia(89%) saturate(500%) hue-rotate(120deg); }
.ch-divider-v { width: 1.5px; height: 44px; background: #d1fae5; margin-right: 20px; flex-shrink: 0; }
.ch-title-wrap { display: flex; flex-direction: column; gap: 4px; }
.ch-title { font-size: 17px; font-weight: 900; color: #065f46; letter-spacing: 0.8px; text-transform: uppercase; }
.ch-warranty { font-size: 10.5px; color: #059669; font-weight: 600; letter-spacing: 0.4px; text-transform: uppercase; }
.ch-bottom { display: flex; flex-direction: column; background: #f0fdf4; }
.ch-bottom-row { display: flex; align-items: center; padding: 5px 20px; }
.ch-bottom-row + .ch-bottom-row { border-top: 1px solid #d1fae5; }
.ch-contact-group { display: flex; align-items: baseline; gap: 5px; padding: 0 12px; }
.ch-contact-group:first-child { padding-left: 0; }
.ch-contact-group:last-child  { padding-right: 0; }
.ch-contact-label { font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: #059669; flex-shrink: 0; }
.ch-contact-value { font-size: 10.5px; color: #374151; }
.ch-sep { width: 1px; height: 16px; background: #6ee7b7; flex-shrink: 0; }

/* "— continued —" label */
.continued-top { text-align: right; font-size: 11px; color: #9ca3af; padding: 6px 32px 0; font-style: italic; }

/* Topbar (page 1 only) */
.invoice-topbar { background: #fff; border-left: 6px solid #059669; }
.topbar-accent { height: 5px; background: linear-gradient(90deg, #059669, #34d399); }
.topbar-content { padding: 20px 32px 8px; }
.invoice-label { font-size: 38px; font-weight: 900; letter-spacing: 6px; color: #059669; line-height: 1; }
.topbar-dates { display: flex; align-items: center; padding: 12px 32px 20px; border-top: 1px solid #d1fae5; }
.date-item { display: flex; flex-direction: column; gap: 2px; padding: 0 16px; }
.date-item:first-child { padding-left: 0; }
.date-item:last-child  { padding-right: 0; }
.date-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; }
.date-val { font-size: 13px; font-weight: 700; color: #111; }
.date-divider { width: 1px; height: 32px; background: #d1fae5; flex-shrink: 0; }

/* Parties (page 1 only) */
.invoice-parties { display: flex; padding: 20px 32px; border-top: 1px solid #d1fae5; border-bottom: 1px solid #d1fae5; background: #f0fdf4; }
.party-block { flex: 1; padding: 0 16px; }
.party-block:first-of-type { padding-left: 0; }
.party-block:last-of-type  { padding-right: 0; }
.party-divider { width: 1px; background: #d1fae5; margin: 4px 0; }
.party-label { font-size: 10px; font-weight: 800; letter-spacing: 1.5px; color: #059669; margin-bottom: 8px; }
.party-name { font-weight: 700; font-size: 13px; color: #111; margin-bottom: 4px; }
.party-text { color: #6b7280; line-height: 1.7; }

/* Table */
.invoice-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.invoice-table th:first-child,                             .invoice-table td:first-child                             { width: 28%; text-wrap: wrap; }
.invoice-table th:nth-child(2),                            .invoice-table td:nth-child(2)                            { width: 12%; }
.invoice-table th:not(:first-child):not(:nth-child(2)),    .invoice-table td:not(:first-child):not(:nth-child(2))    { width: 17.67%; }
.invoice-table th:last-child,                              .invoice-table td:last-child                              { width: 12%; }
.invoice-table thead tr { background: #ecfdf5; }
.invoice-table th { padding: 10px 24px; font-size: 11px; font-weight: 800; letter-spacing: 1px; color: #059669; border-bottom: 2px solid #d1fae5; }
.invoice-table td { padding: 10px 24px; border-bottom: 1px solid #f3f4f6; color: #374151; }
.row-alt td { background: #f9fafb; }

/* Totals (last page only) */
.invoice-bottom { display: flex; justify-content: flex-end; padding: 16px 24px; border-top: 2px solid #d1fae5; background: #f0fdf4; }
.invoice-totals { width: 280px; display: flex; flex-direction: column; gap: 6px; }
.total-line { display: flex; justify-content: space-between; font-size: 0.8rem; padding: 6px 10px; border-radius: 4px; background: #059669; color: #fff; font-weight: 600; }
.total-line.grand { margin-top: 4px; background: #065f46; font-weight: 800; font-size: 13px; padding: 10px 12px; border-radius: 6px; }

/* Footer (last page only) */
.invoice-footer { background: #059669; color: #fff; display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; font-weight: 800; letter-spacing: 2px; font-size: 11px; }
.footer-warranty { font-weight: 600; font-size: 10px; color: #d1fae5; letter-spacing: 0.5px; }

.text-left  { text-align: left; }
.text-right { text-align: right; }
</style>