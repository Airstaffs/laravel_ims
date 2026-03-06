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
                            <img :src="logoSrc" alt="Logo" class="ch-logo" />
                            <div class="ch-center">
                                <div class="ch-title">{{ title }}</div>
                                <div class="ch-warranty">{{ warrantyText(warrantyFrom, warrantyFromUnit) }} &mdash; {{ warrantyText(warrantyTo, warrantyToUnit) }} Warranty</div>
                            </div>
                        </div>
                        <div class="ch-bottom">
                            <div class="ch-col">{{ ownerWebsite }}</div>
                            <div class="ch-col-sep" />
                            <div class="ch-col">{{ ownerEmail }}</div>
                            <div class="ch-col-sep" />
                            <div class="ch-col">{{ ownerContact }}</div>
                            <div class="ch-col-sep" />
                            <div class="ch-col">{{ ownerAddress }}</div>
                        </div>
                    </div>

                    <!-- ── PAGE 1: invoice header + info strip ── -->
                    <template v-if="pi === 0">
                        <div class="invoice-header">
                            <div class="header-left">
                                <div class="brand-bar" />
                                <div class="header-text">
                                    <div class="invoice-title">INVOICE</div>
                                </div>
                            </div>
                            <div class="header-right">
                                <div class="header-dates">
                                    <div v-if="trackingNumber" class="hdate">
                                        <span>Tracking #</span>
                                        <strong>{{ trackingNumber }}</strong>
                                    </div>
                                    <div v-if="orderNumber" class="hdate">
                                        <span>Order #</span>
                                        <strong>{{ orderNumber }}</strong>
                                    </div>
                                    <div class="hdate">
                                        <span>Issued</span>
                                        <strong>{{ invoiceDate }}</strong>
                                    </div>
                                    <div class="hdate">
                                        <span>Due</span>
                                        <strong>{{ dueDate }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="info-strip">
                            <div class="info-block">
                                <div class="info-label">BILL TO</div>
                                <div v-if="billToName"    class="info-name">{{ billToName }}</div>
                                <div v-if="billToAddress1" class="info-text">{{ billToAddress1 }}</div>
                                <div v-if="billToAddress2" class="info-text">{{ billToAddress2 }}</div>
                                <div v-if="billToContact"  class="info-text">{{ billToContact }}</div>
                            </div>
                            <div class="info-divider" />
                            <div class="info-block">
                                <div class="info-label">SHIP TO</div>
                                <div v-if="shipToName"    class="info-name">{{ shipToName }}</div>
                                <div v-if="shipToAddress1" class="info-text">{{ shipToAddress1 }}</div>
                                <div v-if="shipToAddress2" class="info-text">{{ shipToAddress2 }}</div>
                                <div v-if="shipToContact"  class="info-text">{{ shipToContact }}</div>
                                <div v-if="shipToEmail"    class="info-text">{{ shipToEmail }}</div>
                            </div>
                            <div class="info-divider" />
                            <div class="info-block">
                                <div class="info-label">PAYMENT</div>
                                <div class="info-text">{{ currentSupplier.paymentType || 'Paypal' }}</div>
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
                            <!-- empty rows only on last page -->
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
                            <div class="footer-msg">Thank you for your business!</div>
                            <div class="footer-warranty">{{ warrantyText(warrantyFrom, warrantyFromUnit) }} – {{ warrantyText(warrantyTo, warrantyToUnit) }} Warranty Included</div>
                        </div>
                        <div class="footer-totals">
                            <div class="ftotal-line"><span>Subtotal</span><span>{{ fmt(subtotal) }}</span></div>
                            <div class="ftotal-line"><span>Tax ({{ taxRate }}%)</span><span>{{ fmt(taxAmount) }}</span></div>
                            <div class="ftotal-grand">
                                <span>Total to Pay</span>
                                <span class="grand-amt">{{ fmt(total) }}</span>
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
    name: 'FourthTemplate',
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
    updated() {
        this.updateScale();
    },
    beforeUnmount() { this._ro?.disconnect(); },
    watch: {
        suppliers() { this.currentIndex = 0; this.$nextTick(this.updateScale); },
    },
};
</script>

<style scoped>
.invoice-wrapper { font-family: 'Arial', sans-serif; font-size: 12px; width: 100%; }
.invoice-outer   { width: 100%; overflow: hidden; }

.supplier-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
.tab-btn { padding: 6px 16px; border-radius: 20px; border: 2px solid #e11d48; background: #fff; color: #e11d48; cursor: pointer; font-size: 12px; font-weight: 600; }
.tab-btn.active { background: #e11d48; color: #fff; }

/* Page separation */
.invoice-scaler { overflow: hidden; }
.invoice { width: 700px; box-sizing: border-box; background: #fff; overflow: hidden; border: 1px solid #ffe4e6; }
.page-break { margin-bottom: 32px; border-bottom: 3px dashed #f9a8b8; }

/* Company header (shared every page) */
.company-header { overflow: hidden; }
.ch-top { display: flex; align-items: center; gap: 16px; padding: 16px 24px; background: #fff3f5; border-bottom: 1px solid #ffe4e6; }
.ch-logo { width: 50px; height: auto; filter: brightness(0) saturate(100%) invert(17%) sepia(95%) saturate(2000%) hue-rotate(336deg) brightness(90%); flex-shrink: 0; }
.ch-center { display: flex; flex-direction: column; gap: 4px; }
.ch-title { font-size: 18px; font-weight: 900; color: #9f1239; letter-spacing: 1px; text-transform: uppercase; line-height: 1; }
.ch-warranty { font-size: 10px; font-weight: 700; color: #e11d48; text-transform: uppercase; letter-spacing: 0.5px; }
.ch-bottom { display: flex; align-items: center; padding: 8px 24px; background: #e11d48; }
.ch-col { font-size: 10.5px; color: #fff; flex: 1; min-width: 0; white-space: normal; word-break: break-word; }
.ch-col-sep { width: 1px; height: 14px; background: rgba(255,255,255,0.4); margin: 0 12px; flex-shrink: 0; }

/* "— continued —" label */
.continued-top { text-align: right; font-size: 11px; color: #9ca3af; padding: 6px 24px 0; font-style: italic; }

/* Invoice header (page 1 only) */
.invoice-header { display: flex; justify-content: space-between; align-items: stretch; background: #fff3f5; border-bottom: 3px solid #e11d48; }
.header-left { display: flex; align-items: stretch; }
.brand-bar { width: 8px; background: #e11d48; flex-shrink: 0; }
.header-text { padding: 24px 20px; }
.invoice-title { font-size: 36px; font-weight: 900; letter-spacing: 5px; color: #e11d48; line-height: 1; }
.header-right { padding: 20px 24px; display: flex; align-items: center; }
.header-dates { display: flex; gap: 20px; }
.hdate { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; }
.hdate span { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; }
.hdate strong { font-size: 12px; color: #111; }

/* Info strip (page 1 only) */
.info-strip { display: flex; padding: 20px 24px; border-bottom: 1px solid #ffe4e6; }
.info-block { flex: 1; padding: 0 16px; }
.info-block:first-child { padding-left: 0; }
.info-block:last-child  { padding-right: 0; }
.info-divider { width: 1px; background: #ffe4e6; }
.info-label { font-size: 9px; font-weight: 900; letter-spacing: 2px; color: #e11d48; text-transform: uppercase; margin-bottom: 6px; }
.info-name  { font-weight: 700; font-size: 12px; color: #111; margin-bottom: 4px; }
.info-text  { font-size: 11px; color: #6b7280; line-height: 1.7; }

/* Table */
.invoice-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.invoice-table th:first-child, .invoice-table td:first-child { width: 35%; text-wrap: wrap; }
.invoice-table th:nth-child(2), .invoice-table td:nth-child(2) { width: 10%; }
.invoice-table th:nth-child(3), .invoice-table td:nth-child(3) { width: 22%; }
.invoice-table th:nth-child(4), .invoice-table td:nth-child(4) { width: 22%; }
.invoice-table th:nth-child(5), .invoice-table td:nth-child(5) { width: 11%; }
.invoice-table thead tr { background: #e11d48; }
.invoice-table th { padding: 10px 16px; font-size: 10px; font-weight: 800; letter-spacing: 1px; color: #fff; text-transform: uppercase; background-color: #e11d48; }
.invoice-table td { padding: 10px 16px; border-bottom: 1px solid #fff1f2; color: #374151; }
.alt td { background: #fff8f9; }

/* Footer (last page only) */
.invoice-footer { display: flex; justify-content: space-between; align-items: center; border-top: 2px solid #ffe4e6; padding: 20px 24px; }
.footer-msg { font-size: 13px; font-weight: 800; color: #e11d48; letter-spacing: 1px; text-transform: uppercase; }
.footer-warranty { font-size: 10.5px; color: #9ca3af; margin-top: 4px; }
.footer-totals { display: flex; flex-direction: column; gap: 5px; min-width: 220px; }
.ftotal-line { display: flex; justify-content: space-between; font-size: 12px; color: #6b7280; padding: 3px 0; border-bottom: 1px dashed #ffe4e6; }
.ftotal-grand { display: flex; justify-content: space-between; align-items: center; margin-top: 6px; padding: 8px 12px; background: #e11d48; border-radius: 6px; color: #fff; font-weight: 800; font-size: 13px; }
.grand-amt { font-size: 16px; }

.text-left  { text-align: left; }
.text-right { text-align: right; }
</style>