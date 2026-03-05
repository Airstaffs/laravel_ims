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
                        <div class="ch-left">
                            <img :src="logoSrc" alt="Logo" class="ch-logo" />
                            <div class="ch-brand">
                                <div class="ch-title">{{ title }}</div>
                                <div class="ch-warranty">
                                    <span class="warranty-badge">
                                        {{ warrantyText(warrantyFrom, warrantyFromUnit) }} – {{ warrantyText(warrantyTo, warrantyToUnit) }} Warranty
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="ch-right">
                            <div class="ch-contact-item"><span>{{ ownerWebsite }}</span></div>
                            <div class="ch-contact-item"><span>{{ ownerEmail }}</span></div>
                            <div class="ch-contact-item"><span>{{ ownerContact }}</span></div>
                            <div class="ch-contact-item"><span>{{ ownerAddress }}</span></div>
                        </div>
                    </div>
                    <div class="accent-bar"></div>

                    <!-- ── PAGE 1: invoice header + parties ── -->
                    <template v-if="pi === 0">
                        <div class="invoice-header">
                            <div class="invoice-meta">
                                <div><strong>Invoice Date</strong> &nbsp;&nbsp;&nbsp;{{ invoiceDate }}</div>
                                <div><strong>Due Date</strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ dueDate }}</div>
                                <div v-if="trackingNumber"><strong>Tracking #</strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ trackingNumber }}</div>
                                <div v-if="orderNumber"><strong>Order #</strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ orderNumber }}</div>
                            </div>
                            <div class="invoice-title">INVOICE</div>
                        </div>

                        <hr class="divider" />

                        <div class="invoice-parties">
                            <div>
                                <div class="section-label">BILL TO</div>
                                <div class="section-content">
                                    <div v-if="billToName">{{ billToName }}</div>
                                    <div v-if="billToAddress1">{{ billToAddress1 }}</div>
                                    <div v-if="billToAddress2">{{ billToAddress2 }}</div>
                                    <div v-if="billToContact">{{ billToContact }}</div>
                                </div>
                            </div>
                            <div>
                                <div class="section-label">SHIP TO</div>
                                <div class="section-content">
                                    <div v-if="shipToName">{{ shipToName }}</div>
                                    <div v-if="shipToAddress1">{{ shipToAddress1 }}</div>
                                    <div v-if="shipToAddress2">{{ shipToAddress2 }}</div>
                                    <div v-if="shipToContact">{{ shipToContact }}</div>
                                    <div v-if="shipToEmail">{{ shipToEmail }}</div>
                                </div>
                            </div>
                            <div>
                                <div class="section-label">PAYMENT DETAILS</div>
                                <div class="section-content">
                                    <div>{{ currentSupplier.paymentType || 'Paypal' }}</div>
                                </div>
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
                            <tr v-for="(p, i) in page.rows" :key="i" :class="{ 'row-even': i % 2 !== 0 }">
                                <td>{{ p.ProductTitle }}</td>
                                <td class="text-right">{{ p.quantity }}</td>
                                <td class="text-right">{{ fmt(p.price) }}</td>
                                <td class="text-right">{{ fmt(p.totalPrice) }}</td>
                                <td class="text-right">{{ p.tax }}%</td>
                            </tr>
                            <template v-if="pi === pages.length - 1">
                                <tr v-for="n in emptyRows(page.rows.length)" :key="`e-${n}`" :class="{ 'row-even': (page.rows.length + n - 1) % 2 !== 0 }">
                                    <td colspan="5">&nbsp;</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <!-- Totals + footer: last page only -->
                    <template v-if="pi === pages.length - 1">
                        <div class="invoice-totals">
                            <table>
                                <tbody>
                                    <tr>
                                        <td>Subtotal</td>
                                        <td class="text-right">{{ fmt(subtotal) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Tax ({{ taxRate }}%)</td>
                                        <td class="text-right">{{ fmt(taxAmount) }}</td>
                                    </tr>
                                    <tr class="total-row">
                                        <td>Total to Pay</td>
                                        <td class="text-right">{{ fmt(total) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <hr class="divider" />

                        <div class="invoice-footer">
                            <span>THANK YOU FOR YOUR BUSINESS!</span>
                            <span class="footer-warranty">
                                {{ warrantyText(warrantyFrom, warrantyFromUnit) }} – {{ warrantyText(warrantyTo, warrantyToUnit) }} Warranty Included
                            </span>
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
    name: 'SecondTemplate',
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
        subtotal()        { return this.currentSupplier?.products.reduce((sum, p) => sum + p.totalPrice, 0) ?? 0; },
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
.tab-btn { padding: 6px 16px; border-radius: 4px; border: 1px solid #ccc; background: #fff; color: #111; cursor: pointer; font-size: 12px; }
.tab-btn.active { background: #1a3a5c; color: #fff; border-color: #1a3a5c; }

/* Page separation */
.invoice-scaler { overflow: hidden; }
.invoice { background: #fff; padding: 30px 40px 40px; border: 1px solid #ddd; width: 700px; box-sizing: border-box; }
.page-break { margin-bottom: 32px; border-bottom: 3px dashed #b0c4de; padding-bottom: 0; }

/* Company header (every page) */
.company-header { display: flex; align-items: stretch; background: #1a3a5c; border-radius: 4px 4px 0 0; overflow: hidden; min-height: 90px; }
.ch-left { display: flex; align-items: center; gap: 14px; padding: 16px 20px; background: #1a3a5c; border-right: 1px solid rgba(255,255,255,0.15); flex: 0 0 auto; max-width: 320px; }
.ch-logo { width: 52px; height: auto; filter: brightness(0) invert(1); flex-shrink: 0; }
.ch-brand { display: flex; flex-direction: column; gap: 6px; }
.ch-title { color: #fff; font-size: 15px; font-weight: bold; letter-spacing: 0.5px; line-height: 1.2; }
.warranty-badge { display: inline-block; background: rgba(255,255,255,0.15); color: #c8dff5; font-size: 10px; padding: 3px 8px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.25); letter-spacing: 0.3px; white-space: nowrap; }
.ch-right { flex: 1; display: flex; flex-direction: column; justify-content: center; gap: 5px; padding: 14px 20px; background: #223f5e; }
.ch-contact-item { display: flex; align-items: center; gap: 8px; color: #c8dff5; font-size: 11px; }

/* Accent bar (every page) */
.accent-bar { height: 4px; background: linear-gradient(90deg, #2980b9, #1abc9c); margin-bottom: 20px; }

/* "— continued —" label */
.continued-top { text-align: right; font-size: 11px; color: #9ca3af; margin-bottom: 12px; font-style: italic; }

/* Invoice header (page 1 only) */
.invoice-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; line-height: 1.8; }
.invoice-title { font-size: 36px; font-weight: bold; letter-spacing: 2px; color: #1a3a5c; }

.divider { border: none; border-top: 1px solid #aaa; margin-bottom: 16px; }

/* Parties (page 1 only) */
.invoice-parties { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 32px; }
.section-label { font-weight: bold; font-size: 11px; margin-bottom: 6px; color: #1a3a5c; letter-spacing: 0.5px; }
.section-content { line-height: 1.7; color: #444; }

/* Table */
.invoice-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 16px; }
.invoice-table th:first-child,     .invoice-table td:first-child     { width: 35%; text-wrap: wrap; }
.invoice-table th:not(:first-child), .invoice-table td:not(:first-child) { width: 16.25%; }
.invoice-table thead tr { background: linear-gradient(90deg, #1a3a5c, #2980b9); }
.invoice-table th { padding: 8px 10px; font-size: 11px; font-weight: bold; letter-spacing: 0.5px; color: #fff; background-color: #1a3a5c; }
.invoice-table td { padding: 8px 10px; border-bottom: 1px solid #e5e5e5; }
.row-even td { background: #f5f9ff; }

/* Totals (last page only) */
.invoice-totals { display: flex; justify-content: flex-end; margin-bottom: 24px; }
.invoice-totals table { width: 260px; font-size: 12px; }
.invoice-totals td { padding: 4px 10px; color: #444; }
.total-row td { font-weight: bold; font-size: 13px; border-top: 2px solid #1a3a5c; padding-top: 6px; color: #1a3a5c; }

/* Footer (last page only) */
.invoice-footer { background: linear-gradient(90deg, #1a3a5c, #2980b9); color: #fff; display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; font-weight: bold; letter-spacing: 1px; font-size: 11px; border-radius: 0 0 4px 4px; }
.footer-warranty { font-weight: normal; font-size: 10px; color: #c8dff5; letter-spacing: 0.3px; }

.text-left  { text-align: left; }
.text-right { text-align: right; }
</style>