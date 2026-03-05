<template>
    <div class="invoice-wrapper">
        <!-- Supplier Tabs -->
        <div v-if="suppliers.length > 1" class="supplier-tabs">
            <button v-for="(s, i) in suppliers" :key="i" :class="['tab-btn', { active: currentIndex === i }]"
                @click="currentIndex = i">
                {{ s.name }}
            </button>
        </div>

        <div ref="wrapperRef" style="width:100%; overflow:hidden;">
            <div class="invoice-scaler" ref="scalerRef">
                <div class="invoice" ref="invoiceRef" v-if="currentSupplier">

                    <!-- ── Company Header ── -->
                    <div class="company-header">
                        <div class="ch-top">
                            <div class="ch-logo-wrap">
                                <img :src="logoSrc" alt="Logo" class="ch-logo" />
                            </div>
                            <div class="ch-divider-v" />
                            <div class="ch-title-wrap">
                                <div class="ch-title">{{ title }}</div>
                                <div class="ch-warranty">{{ warrantyFrom }} {{ warrantyFromUnitText }} to {{ warrantyTo }} {{ warrantyToUnitText }} Warranty</div>
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

                    <!-- Top Bar -->
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

                    <!-- Parties -->
                    <div class="invoice-parties">
                        <div class="party-divider" />
                        <div class="party-block">
                            <div class="party-label">BILL TO</div>
                            <div v-if="billToName" class="party-name">{{ billToName }}</div>
                            <div v-if="billToAddress1" class="party-text">{{ billToAddress1 }}</div>
                            <div v-if="billToAddress2" class="party-text">{{ billToAddress2 }}</div>
                            <div v-if="billToContact" class="party-text">{{ billToContact }}</div>
                        </div>
                        <div class="party-divider" />
                        <div class="party-block">
                            <div class="party-label">SHIP TO</div>
                            <div v-if="shipToName" class="party-name">{{ shipToName }}</div>
                            <div v-if="shipToAddress1" class="party-text">{{ shipToAddress1 }}</div>
                            <div v-if="shipToAddress2" class="party-text">{{ shipToAddress2 }}</div>
                            <div v-if="shipToContact" class="party-text">{{ shipToContact }}</div>
                            <div v-if="shipToEmail" class="party-text">{{ shipToEmail }}</div>
                        </div>
                        <div class="party-divider" />
                        <div class="party-block">
                            <div class="party-label">PAYMENT DETAILS</div>
                            <div class="party-text">Paypal</div>
                        </div>
                    </div>

                    <!-- Table -->
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
                            <tr v-for="(p, i) in currentSupplier.products" :key="i" :class="{ 'row-alt': i % 2 !== 0 }">
                                <td>{{ p.ProductTitle }}</td>
                                <td class="text-right">{{ p.quantity }}</td>
                                <td class="text-right">{{ fmt(p.price) }}</td>
                                <td class="text-right">{{ fmt(p.totalPrice) }}</td>
                                <td class="text-right">{{ p.tax }}%</td>
                            </tr>
                            <tr v-for="n in emptyRows" :key="`e-${n}`"
                                :class="{ 'row-alt': (currentSupplier.products.length + n - 1) % 2 !== 0 }">
                                <td colspan="5">&nbsp;</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Totals -->
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

                    <!-- Footer -->
                    <div class="invoice-footer">
                        <span>THANK YOU FOR YOUR BUSINESS!</span>
                        <span class="footer-warranty">{{ warrantyFrom }} {{ warrantyFromUnitText }} – {{ warrantyTo }} {{ warrantyToUnitText }} Warranty</span>
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>

<script>
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
        // Header
        logoSrc:          { type: String,            default: '/images/all-renewed-logo.png' },
        title:            { type: String,            default: 'ALL RENEWED ELECTRONICS' },
        warrantyFrom:     { type: [String, Number],  default: 90 },
        warrantyFromUnit: { type: String,            default: 'days' },
        warrantyTo:       { type: [String, Number],  default: 1 },
        warrantyToUnit:   { type: String,            default: 'years' },
        ownerAddress:     { type: String,            default: '4620 Northgate Blvd., Ste 180, Sacramento, CA 95834' },
        ownerContact:     { type: String,            default: '(415) 882-6949' },
        ownerEmail:       { type: String,            default: 'sales@allrenewed.com' },
        ownerWebsite:     { type: String,            default: 'www.allrenewed.com' },
        // Tracking & Order
        trackingNumber:   { type: String,            default: '' },
        orderNumber:      { type: String,            default: '' },
        // Bill To
        billToName:       { type: String,            default: '' },
        billToAddress1:   { type: String,            default: '' },
        billToAddress2:   { type: String,            default: '' },
        billToContact:    { type: String,            default: '' },
        // Ship To
        shipToName:       { type: String,            default: '' },
        shipToAddress1:   { type: String,            default: '' },
        shipToAddress2:   { type: String,            default: '' },
        shipToContact:    { type: String,            default: '' },
        shipToEmail:      { type: String,            default: '' },
    },
    data() {
        return { currentIndex: 0 };
    },
    computed: {
        currentSupplier() { return this.suppliers[this.currentIndex] ?? null; },
        subtotal()        { return this.currentSupplier?.products.reduce((s, p) => s + p.totalPrice, 0) ?? 0; },
        taxRate()         { return this.currentSupplier?.products[0]?.tax ?? 0; },
        taxAmount()       { return this.subtotal * (this.taxRate / 100); },
        total()           { return this.subtotal + this.taxAmount; },
        emptyRows()       { return Math.max(0, 8 - (this.currentSupplier?.products.length ?? 0)); },
        warrantyFromUnitText() {
            const u = this.warrantyFromUnit?.toLowerCase() ?? '';
            return Number(this.warrantyFrom) === 1 ? u.replace(/s$/, '') : u;
        },
        warrantyToUnitText() {
            const u = this.warrantyToUnit?.toLowerCase() ?? '';
            return Number(this.warrantyTo) === 1 ? u.replace(/s$/, '') : u;
        },
    },
    methods: {
        fmt(n) {
            return `$${Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;
        },
        updateScale() {
            this.$nextTick(() => {
                const scaler = this.$refs.scalerRef;
                const wrapper = this.$refs.wrapperRef;
                if (!scaler || !wrapper) return;
                const scale = Math.min(1, wrapper.offsetWidth / 700);
                scaler.style.width = '700px';
                scaler.style.transform = `scale(${scale})`;
                scaler.style.transformOrigin = 'top left';
                scaler.style.height = 'auto';
                wrapper.style.height = `${scaler.offsetHeight * scale}px`;
            });
        },
    },
    mounted() {
        this.updateScale();
        window.addEventListener('resize', this.updateScale);
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.updateScale);
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
.invoice-wrapper { font-family: 'Arial', sans-serif; font-size: 12px; color: #222; width: 100%; }

.supplier-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
.tab-btn { padding: 6px 16px; border-radius: 4px; border: 2px solid #059669; background: #fff; color: #059669; cursor: pointer; font-size: 12px; font-weight: 600; }
.tab-btn.active { background: #059669; color: #fff; }

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

.invoice-scaler { overflow: hidden; }
.invoice { background: #fff; border: 1px solid #d1fae5; width: 700px; box-sizing: border-box; overflow: hidden; }

.invoice-topbar { background: #fff; border-left: 6px solid #059669; }
.topbar-accent { height: 5px; background: linear-gradient(90deg, #059669, #34d399); }
.topbar-content { padding: 20px 32px 8px; }
.invoice-label { font-size: 38px; font-weight: 900; letter-spacing: 6px; color: #059669; line-height: 1; }
.topbar-dates { display: flex; align-items: center; padding: 0 32px 20px; gap: 0; border-top: 1px solid #d1fae5; padding-top: 12px; }
.date-item { display: flex; flex-direction: column; gap: 2px; padding: 0 16px; }
.date-item:first-child { padding-left: 0; }
.date-item:last-child  { padding-right: 0; }
.date-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; }
.date-val { font-size: 13px; font-weight: 700; color: #111; }
.date-divider { width: 1px; height: 32px; background: #d1fae5; flex-shrink: 0; }

.invoice-parties { display: flex; padding: 20px 32px; border-top: 1px solid #d1fae5; border-bottom: 1px solid #d1fae5; background: #f0fdf4; }
.party-block { flex: 1; padding: 0 16px; }
.party-block:first-of-type { padding-left: 0; }
.party-block:last-of-type  { padding-right: 0; }
.party-divider { width: 1px; background: #d1fae5; margin: 4px 0; }
.party-label { display: flex; align-items: center; gap: 6px; font-size: 10px; font-weight: 800; letter-spacing: 1.5px; color: #059669; margin-bottom: 8px; }
.party-name { font-weight: 700; font-size: 13px; color: #111; margin-bottom: 4px; }
.party-text { color: #6b7280; line-height: 1.7; }

.invoice-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.invoice-table th:first-child, .invoice-table td:first-child { width: 28%; text-wrap: wrap; }
.invoice-table th:nth-child(2), .invoice-table td:nth-child(2) { width: 12%; }
.invoice-table th:not(:first-child):not(:nth-child(2)), .invoice-table td:not(:first-child):not(:nth-child(2)) { width: 17.67%; }
.invoice-table th:last-child, .invoice-table td:last-child { width: 12%; }
.invoice-table thead tr { background: #ecfdf5; }
.invoice-table th { padding: 10px 24px; font-size: 11px; font-weight: 800; letter-spacing: 1px; color: #059669; border-bottom: 2px solid #d1fae5; }
.invoice-table td { padding: 10px 24px; border-bottom: 1px solid #f3f4f6; color: #374151; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.row-alt td { background: #f9fafb; }

.invoice-bottom { display: flex; justify-content: flex-end; padding: 16px 24px; border-top: 2px solid #d1fae5; background: #f0fdf4; }
.invoice-totals { width: 280px; display: flex; flex-direction: column; gap: 6px; }
.total-line { display: flex; justify-content: space-between; font-size: 0.8rem; padding: 6px 10px; border-radius: 4px; background: #059669; color: #fff; font-weight: 600; }
.total-line.grand { margin-top: 4px; background: #065f46; font-weight: 800; font-size: 13px; padding: 10px 12px; border-radius: 6px; }

.invoice-footer { background: #059669; color: #fff; display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; font-weight: 800; letter-spacing: 2px; font-size: 11px; }
.footer-warranty { font-weight: 600; font-size: 10px; color: #d1fae5; letter-spacing: 0.5px; }

.text-left  { text-align: left; }
.text-right { text-align: right; }
</style>