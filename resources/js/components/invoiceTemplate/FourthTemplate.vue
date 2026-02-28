<template>
    <div class="invoice-wrapper">
        <div v-if="suppliers.length > 1" class="supplier-tabs">
            <button v-for="(s, i) in suppliers" :key="i" :class="['tab-btn', { active: currentIndex === i }]"
                @click="currentIndex = i">
                {{ s.name }}
            </button>
        </div>

        <div ref="wrapperRef" style="width:100%; overflow:hidden;">
            <div class="invoice-scaler" ref="scalerRef">
                <div class="invoice" ref="invoiceRef" v-if="currentSupplier">

                    <!-- Header -->
                    <div class="invoice-header">
                        <div class="header-left">
                            <div class="brand-bar" />
                            <div class="header-text">
                                <div class="invoice-title">INVOICE</div>
                                <div class="invoice-sub">{{ currentSupplier.name }}</div>
                            </div>
                        </div>
                        <div class="header-right">
                            <div class="header-badge">#{{ invoiceNumber }}</div>
                            <div class="header-dates">
                                <div class="hdate"><span>Issued</span><strong>{{ invoiceDate }}</strong></div>
                                <div class="hdate"><span>Due</span><strong>{{ dueDate }}</strong></div>
                            </div>
                        </div>
                    </div>

                    <!-- Info strip -->
                    <div class="info-strip">
                        <div class="info-block">
                            <div class="info-label">SELLER</div>
                            <div class="info-name">{{ currentSupplier.name }}</div>
                            <div class="info-text">{{ currentSupplier.address1 }}, {{ currentSupplier.address2 }}</div>
                            <div class="info-text">{{ currentSupplier.contact }} · {{ currentSupplier.email }}</div>
                        </div>
                        <div class="info-divider" />
                        <div class="info-block">
                            <div class="info-label">BILL TO</div>
                            <div class="info-name">[Client's Company Name]</div>
                            <div class="info-text">[Client's Company Address Line 1]</div>
                            <div class="info-text">[Client's Company Address Line 2]</div>
                        </div>
                        <div class="info-divider" />
                        <div class="info-block">
                            <div class="info-label">PAYMENT</div>
                            <div class="info-text">{{ currentSupplier.email }}</div>
                            <div class="info-text">{{ currentSupplier.websiteAddress }}</div>
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
                            <tr v-for="(p, i) in currentSupplier.products" :key="i" :class="{ alt: i % 2 !== 0 }">
                                <td>{{ p.ProductTitle }}</td>
                                <td class="text-right">{{ p.quantity }}</td>
                                <td class="text-right">{{ fmt(p.price) }}</td>
                                <td class="text-right">{{ fmt(p.totalPrice) }}</td>
                                <td class="text-right">{{ p.tax }}%</td>
                            </tr>
                            <tr v-for="n in emptyRows" :key="`e-${n}`"
                                :class="{ alt: (currentSupplier.products.length + n - 1) % 2 !== 0 }">
                                <td colspan="5">&nbsp;</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Footer -->
                    <div class="invoice-footer">
                        <div class="footer-left">
                            <div class="footer-msg">Thank you for your business!</div>
                            <div class="footer-web">{{ currentSupplier.websiteAddress }}</div>
                        </div>
                        <div class="footer-totals">
                            <div class="ftotal-line"><span>Subtotal</span><span>{{ fmt(subtotal) }}</span></div>
                            <div class="ftotal-line"><span>Tax ({{ taxRate }}%)</span><span>{{ fmt(taxAmount) }}</span>
                            </div>
                            <div class="ftotal-grand">
                                <span>Total to Pay</span>
                                <span class="grand-amt">{{ fmt(total) }}</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>

<script>
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';

export default {
    name: 'ProductInvoiceTemplate5',
    props: {
        suppliers: { type: Array, required: true },
        invoiceNumber: { type: [String, Number], default: '' },
           invoiceDate: {
            type: String,
            default: () => new Date().toLocaleDateString('en-US', { timeZone: 'America/Los_Angeles', month: '2-digit', day: '2-digit', year: 'numeric' }),
        },
        dueDate: {
            type: String,
            default: () => new Date(Date.now() + 15 * 864e5).toLocaleDateString('en-US', { timeZone: 'America/Los_Angeles', month: '2-digit', day: '2-digit', year: 'numeric' }),
        },
    },
    data() { return { currentIndex: 0 }; },
    computed: {
        currentSupplier() { return this.suppliers[this.currentIndex] ?? null; },
        subtotal() { return this.currentSupplier?.products.reduce((s, p) => s + p.totalPrice, 0) ?? 0; },
        taxRate() { return this.currentSupplier?.products[0]?.tax ?? 0; },
        taxAmount() { return this.subtotal * (this.taxRate / 100); },
        total() { return this.subtotal + this.taxAmount; },
        emptyRows() { return Math.max(0, 8 - (this.currentSupplier?.products.length ?? 0)); },
    },
    methods: {
        fmt(n) { return `$${Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`; },
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
        async downloadPdf() {
            const el = this.$refs.invoiceRef;
            const canvas = await html2canvas(el, { scale: 2, useCORS: true });
            const imgData = canvas.toDataURL('image/png');
            const pdf = new jsPDF('p', 'mm', 'a4');
            const pw = pdf.internal.pageSize.getWidth();
            pdf.addImage(imgData, 'PNG', 0, 0, pw, (canvas.height * pw) / canvas.width);
            pdf.save(`invoice-${this.invoiceNumber}.pdf`);
        },
    },
    mounted() { this.updateScale(); window.addEventListener('resize', this.updateScale); },
    beforeUnmount() { window.removeEventListener('resize', this.updateScale); },
    watch: {
        suppliers() { this.currentIndex = 0; this.$nextTick(this.updateScale); },
    },
};
</script>

<style scoped>
.invoice-wrapper {
    font-family: 'Arial', sans-serif;
    font-size: 12px;
    width: 100%;
}

.supplier-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 16px;
}

.tab-btn {
    padding: 6px 16px;
    border-radius: 20px;
    border: 2px solid #e11d48;
    background: #fff;
    color: #e11d48;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
}

.tab-btn.active {
    background: #e11d48;
    color: #fff;
}

.invoice-scaler {
    overflow: hidden;
}

.invoice {
    width: 700px;
    box-sizing: border-box;
    background: #fff;
    overflow: hidden;
    border: 1px solid #ffe4e6;
    box-shadow: 0 4px 20px rgba(225, 29, 72, 0.07);
}

/* Header */
.invoice-header {
    display: flex;
    justify-content: space-between;
    align-items: stretch;
    padding: 0;
    background: #fff3f5;
    border-bottom: 3px solid #e11d48;
}

.header-left {
    display: flex;
    align-items: stretch;
}

.brand-bar {
    width: 8px;
    background: #e11d48;
    flex-shrink: 0;
}

.header-text {
    padding: 24px 20px;
}

.invoice-title {
    font-size: 36px;
    font-weight: 900;
    letter-spacing: 5px;
    color: #e11d48;
    line-height: 1;
}

.invoice-sub {
    font-size: 12px;
    color: #9ca3af;
    letter-spacing: 1px;
    margin-top: 4px;
}

.header-right {
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: flex-end;
    gap: 10px;
}

.header-badge {
    background: #e11d48;
    color: #fff;
    font-size: 14px;
    font-weight: 800;
    padding: 4px 16px;
    border-radius: 20px;
    letter-spacing: 1px;
}

.header-dates {
    display: flex;
    gap: 20px;
}

.hdate {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
}

.hdate span {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #9ca3af;
}

.hdate strong {
    font-size: 12px;
    color: #111;
}

/* Info strip */
.info-strip {
    display: flex;
    padding: 20px 24px;
    background: #fff;
    border-bottom: 1px solid #ffe4e6;
    gap: 0;
}

.info-block {
    flex: 1;
    padding: 0 16px;
}

.info-block:first-child {
    padding-left: 0;
}

.info-block:last-child {
    padding-right: 0;
}

.info-divider {
    width: 1px;
    background: #ffe4e6;
}

.info-label {
    font-size: 9px;
    font-weight: 900;
    letter-spacing: 2px;
    color: #e11d48;
    text-transform: uppercase;
    margin-bottom: 6px;
}

.info-name {
    font-weight: 700;
    font-size: 12px;
    color: #111;
    margin-bottom: 4px;
}

.info-text {
    font-size: 11px;
    color: #6b7280;
    line-height: 1.7;
}

/* Table */
.invoice-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.invoice-table th:first-child,
.invoice-table td:first-child {
    width: 28%;
text-wrap: wrap;
}

.invoice-table th:nth-child(2),
.invoice-table td:nth-child(2) {
    width: 10%;
}

.invoice-table th:nth-child(3),
.invoice-table td:nth-child(3) {
    width: 22%;
}

.invoice-table th:nth-child(4),
.invoice-table td:nth-child(4) {
    width: 22%;
}

.invoice-table th:nth-child(5),
.invoice-table td:nth-child(5) {
    width: 18%;
}

.invoice-table th,
.invoice-table td {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.invoice-table thead tr {
    background: #e11d48;
}

.invoice-table th {
    padding: 10px 16px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1px;
    color: #e11d48;
    text-transform: uppercase;
}

.invoice-table td {
    padding: 10px 16px;
    border-bottom: 1px solid #fff1f2;
    color: #374151;
}

.alt td {
    background: #fff8f9;
}

/* Footer */
.invoice-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 2px solid #ffe4e6;
    background: #fff;
    padding: 20px 24px;
}

.footer-left {}

.footer-msg {
    font-size: 13px;
    font-weight: 800;
    color: #e11d48;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.footer-web {
    font-size: 11px;
    color: #9ca3af;
    margin-top: 4px;
}

.footer-totals {
    display: flex;
    flex-direction: column;
    gap: 5px;
    min-width: 220px;
}

.ftotal-line {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #6b7280;
    padding: 3px 0;
    border-bottom: 1px dashed #ffe4e6;
}

.ftotal-grand {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 6px;
    padding: 8px 12px;
    background: #e11d48;
    border-radius: 6px;
    color: #fff;
    font-weight: 800;
    font-size: 13px;
}

.grand-amt {
    font-size: 16px;
}

.text-left {
    text-align: left;
}

.text-right {
    text-align: right;
}
</style>