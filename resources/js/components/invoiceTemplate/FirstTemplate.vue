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

                <!-- One .invoice div per page -->
                <div
                    v-for="(page, pi) in pages" :key="pi"
                    class="invoice"
                    :class="{ 'page-break': pi < pages.length - 1 }"
                >

                    <!-- ── PAGE 1: full company header ── -->
                    <template v-if="pi === 0">
                        <div class="company-header">
                            <div class="company-header-left">
                                <img :src="logoSrc" alt="Logo" class="company-logo" />
                            </div>
                            <div class="company-header-center">
                                <div class="company-name">{{ title }}</div>
                                <div class="company-warranty">
                                    {{ warrantyText(warrantyFrom, warrantyFromUnit) }} TO {{ warrantyText(warrantyTo, warrantyToUnit) }} WARRANTY
                                </div>
                            </div>
                        </div>
                        <div class="company-header-bottom">
                            <div class="company-contact-row">
                                <span>{{ ownerWebsite }}</span>
                                <span class="contact-divider">|</span>
                                <span>{{ ownerAddress }}</span>
                            </div>
                            <div class="company-contact-row">
                                <span>{{ ownerEmail }}</span>
                                <span class="contact-divider">|</span>
                                <span>{{ ownerContact }}</span>
                            </div>
                        </div>

                        <div class="invoice-header">
                            <div class="invoice-meta">
                                <div><strong>Tracking Number</strong> &nbsp;{{ trackingNumber }}</div>
                                <div><strong>Invoice Date</strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ invoiceDate }}</div>
                                <div><strong>Due Date</strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ dueDate }}</div>
                                <div><strong>Order Number</strong> &nbsp;&nbsp;&nbsp;{{ orderNumber }}</div>
                            </div>
                            <div class="invoice-title">INVOICE</div>
                        </div>

                        <hr class="divider" />

                        <div class="invoice-parties">
                            <div>
                                <div class="section-label">BILL TO</div>
                                <div class="section-content">
                                    <div>{{ billToName || "[Client's Name]" }}</div>
                                    <div>{{ billToAddress1 || "[Client's Address Line 1]" }}</div>
                                    <div>{{ billToAddress2 || "[Client's Address Line 2]" }}</div>
                                    <div v-if="billToContact">{{ billToContact }}</div>
                                </div>
                            </div>
                            <div>
                                <div class="section-label">SHIP TO</div>
                                <div class="section-content">
                                    <div>{{ shipToName }}</div>
                                    <div>{{ shipToAddress1 }}</div>
                                    <div>{{ shipToAddress2 }}</div>
                                    <div>{{ shipToContact }}</div>
                                    <div>{{ shipToEmail }}</div>
                                </div>
                            </div>
                            <div>
                                <div class="section-label">PAYMENT DETAILS</div>
                                <div class="section-content"><div>Paypal</div></div>
                            </div>
                        </div>
                    </template>

                    <!-- ── PAGE 2+: full header + "continued" label ── -->
                    <template v-else>
                        <div class="company-header">
                            <div class="company-header-left">
                                <img :src="logoSrc" alt="Logo" class="company-logo" />
                            </div>
                            <div class="company-header-center">
                                <div class="company-name">{{ title }}</div>
                                <div class="company-warranty">
                                    {{ warrantyText(warrantyFrom, warrantyFromUnit) }} TO {{ warrantyText(warrantyTo, warrantyToUnit) }} WARRANTY
                                </div>
                            </div>
                        </div>
                        <div class="company-header-bottom">
                            <div class="company-contact-row">
                                <span>{{ ownerWebsite }}</span>
                                <span class="contact-divider">|</span>
                                <span>{{ ownerAddress }}</span>
                            </div>
                            <div class="company-contact-row">
                                <span>{{ ownerEmail }}</span>
                                <span class="contact-divider">|</span>
                                <span>{{ ownerContact }}</span>
                            </div>
                        </div>
                        <div class="continued-top">— continued —</div>
                    </template>

                    <!-- ── Product table (every page) ── -->
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
                            <tr v-for="(p, i) in page.rows" :key="i">
                                <td>{{ p.ProductTitle }}</td>
                                <td class="text-right">{{ p.quantity }}</td>
                                <td class="text-right">{{ fmt(p.price) }}</td>
                                <td class="text-right">{{ fmt(p.totalPrice) }}</td>
                                <td class="text-right">{{ p.tax }}%</td>
                            </tr>
                            <!-- empty rows only on last page -->
                            <tr v-if="pi === pages.length - 1" v-for="n in emptyRows(page.rows.length)" :key="`e-${n}`">
                                <td colspan="5">&nbsp;</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- ── Totals + footer: last page only ── -->
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
                        <div class="invoice-footer">THANK YOU FOR YOUR BUSINESS!</div>
                    </template>



                </div>
                <!-- end page loop -->

            </div>
        </div>
    </div>
</template>

<script>
const INVOICE_WIDTH     = 700;
const MIN_ROWS          = 8;
const ROWS_FIRST_PAGE   = 10;   // products visible on page 1 (header takes vertical space)
const ROWS_OTHER_PAGES  = 15;   // products visible on page 2+
const USD_FORMAT        = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });

export default {
    name: 'FirstTemplate',
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
    data() {
        return { currentIndex: 0 };
    },
    computed: {
        currentSupplier() { return this.suppliers[this.currentIndex] ?? null; },
        subtotal()        { return this.currentSupplier?.products.reduce((sum, p) => sum + p.totalPrice, 0) ?? 0; },
        taxRate()         { return this.currentSupplier?.products[0]?.tax ?? 0; },
        taxAmount()       { return this.subtotal * (this.taxRate / 100); },
        total()           { return this.subtotal + this.taxAmount; },

        // Split products into page chunks
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
        fmt(n) {
            return USD_FORMAT.format(Number(n));
        },
        warrantyText(value, unit) {
            const u = (unit ?? '').toUpperCase();
            return `${value} ${Number(value) === 1 ? u.replace(/S$/, '') : u}`;
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
    beforeUnmount() {
        this._ro?.disconnect();
    },
    watch: {
        suppliers() {
            this.currentIndex = 0;
            this.$nextTick(this.updateScale);
        },
    },
};
</script>

<style scoped>
.invoice-wrapper { font-family: Arial, sans-serif; font-size: 12px; color: #222; width: 100%; }
.invoice-outer   { width: 100%; overflow: hidden; }

.supplier-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
.tab-btn { padding: 6px 16px; border-radius: 4px; border: 1px solid #ccc; background: #fff; color: #111; cursor: pointer; font-size: 12px; }
.tab-btn.active { background: #111; color: #fff; }

/* Page separation */
.invoice { background: #fff; padding: 40px; border: 1px solid #ddd; width: 700px; box-sizing: border-box; }
.page-break { margin-bottom: 32px; border-bottom: 3px dashed #bbb; }

/* Full header (page 1) */
.company-header { display: flex; align-items: stretch; border: 1.5px solid #555; border-bottom: none; }
.company-header-left { display: flex; align-items: center; justify-content: center; padding: 10px 14px; border-right: 1.5px solid #555; background: #fff; min-width: 80px; }
.company-logo { width: 60px; height: auto; filter: brightness(0); }
.company-header-center { flex: 1; background: #555; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 10px 16px; }
.company-name { color: #fff; font-size: 18px; font-weight: bold; letter-spacing: 1px; text-align: center; }
.company-warranty { color: #ddd; font-size: 11px; margin-top: 4px; letter-spacing: 0.5px; text-align: center; }
.company-header-bottom { border: 1.5px solid #555; border-top: 1.5px solid #555; display: flex; flex-direction: column; margin-bottom: 20px; }
.company-contact-row { display: flex; align-items: center; padding: 4px 14px; font-size: 11px; color: #333; border-bottom: 1px solid #ccc; gap: 10px; }
.company-contact-row:last-child { border-bottom: none; }
.contact-divider { color: #aaa; }

/* "— continued —" label top-right on pages 2+ */
.continued-top { text-align: right; font-size: 11px; color: #555; margin-bottom: 8px; font-style: italic; }

.invoice-scaler { overflow: hidden; }

.invoice-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; line-height: 1.8; }
.invoice-title { font-size: 36px; font-weight: bold; letter-spacing: 2px; color: #111; }

.divider { border: none; border-top: 1px solid #aaa; margin-bottom: 16px; }

.invoice-parties { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 32px; }
.section-label { font-weight: bold; font-size: 11px; margin-bottom: 6px; }
.section-content { line-height: 1.7; color: #444; }

.invoice-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 16px; }
.invoice-table th:first-child, .invoice-table td:first-child { width: 35%; text-wrap: wrap; }
.invoice-table th:not(:first-child), .invoice-table td:not(:first-child) { width: 16.25%; }
.invoice-table thead tr { background: #111; color: #fff; }
.invoice-table th { padding: 8px 10px; font-size: 11px; font-weight: bold; letter-spacing: 0.5px; }
.invoice-table td { padding: 8px 10px; border-bottom: 1px solid #e5e5e5; }

.invoice-totals { display: flex; justify-content: flex-end; margin-bottom: 24px; }
.invoice-totals table { width: 260px; font-size: 12px; }
.invoice-totals td { padding: 4px 10px; color: #444; }
.total-row td { font-weight: bold; font-size: 13px; border-top: 2px solid #111; padding-top: 6px; color: #111; }

.invoice-footer { background: #111; color: #fff; text-align: center; padding: 12px; font-weight: bold; letter-spacing: 1px; font-size: 12px; }

.continued-label { text-align: right; font-size: 10px; color: #888; margin-top: 8px; font-style: italic; }

.text-left  { text-align: left;  color: #111; }
.text-right { text-align: right; color: #111; }
</style>