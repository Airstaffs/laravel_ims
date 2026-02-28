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
                            <div class="invoice-title">INVOICE</div>
                            <div class="invoice-number">#{{ invoiceNumber }}</div>
                        </div>
                        <div class="header-right">
                            <div class="wave-bg" />
                            <div class="date-box">
                                <div class="date-item">
                                    <span class="date-label">Invoice Date</span>
                                    <span class="date-val">{{ invoiceDate }}</span>
                                </div>
                                <div class="date-sep" />
                                <div class="date-item">
                                    <span class="date-label">Due Date</span>
                                    <span class="date-val">{{ dueDate }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Parties -->
                    <div class="invoice-parties">
                        <div class="party">
                            <div class="party-label">SELLER</div>
                            <div class="party-name">{{ currentSupplier.name }}</div>
                            <div class="party-info">{{ currentSupplier.address1 }}</div>
                            <div class="party-info">{{ currentSupplier.address2 }}</div>
                            <div class="party-info">{{ currentSupplier.contact }}</div>
                            <div class="party-info">{{ currentSupplier.email }}</div>
                        </div>
                        <div class="party">
                            <div class="party-label">BILL TO</div>
                            <div class="party-name">[Client's Company Name]</div>
                            <div class="party-info">[Client's Company Address Line 1]</div>
                            <div class="party-info">[Client's Company Address Line 2]</div>
                        </div>
                        <div class="party">
                            <div class="party-label">PAYMENT DETAILS</div>
                            <div class="party-info">{{ currentSupplier.email }}</div>
                            <div class="party-info">{{ currentSupplier.websiteAddress }}</div>
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

                    <!-- Totals + Footer -->
                    <div class="invoice-bottom">
                        <div class="bottom-left">
                            <div class="total-line"><span>Subtotal</span><span>{{ fmt(subtotal) }}</span></div>
                            <div class="total-line"><span>Tax ({{ taxRate }}%)</span><span>{{ fmt(taxAmount) }}</span>
                            </div>
                            <div class="total-grand">
                                <span>Total to Pay</span>
                                <span>{{ fmt(total) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="invoice-footer">
                        <div class="footer-circles">
                            <div class="circle c1" />
                            <div class="circle c2" />
                            <div class="circle c3" />
                        </div>
                        <span class="footer-text">THANK YOU FOR YOUR BUSINESS!</span>
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
    name: 'ProductInvoiceBlue',
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
    font-family: Arial, sans-serif;
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
    border: 2px solid #1d4ed8;
    background: #fff;
    color: #1d4ed8;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
}

.tab-btn.active {
    background: #1d4ed8;
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
    border: 1px solid #dbeafe;
    box-shadow: 0 4px 24px rgba(29, 78, 216, 0.08);
}

/* Header */
.invoice-header {
    display: flex;
    justify-content: space-between;
    align-items: stretch;
    min-height: 110px;
}

.header-left {
    padding: 28px 32px;
    background: #fff;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 6px;
}

.invoice-title {
    font-size: 42px;
    font-weight: 900;
    letter-spacing: 6px;
    color: #1d4ed8;
    line-height: 1;
}

.invoice-number {
    font-size: 13px;
    color: #93c5fd;
    font-weight: 600;
    letter-spacing: 1px;
}

.header-right {
    position: relative;
    background: linear-gradient(135deg, #1d4ed8 0%, #0ea5e9 100%);
    min-width: 260px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 24px 28px;
    overflow: hidden;
}

.wave-bg {
    position: absolute;
    left: -40px;
    top: -40px;
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.07);
}

.date-box {
    display: flex;
    gap: 20px;
    align-items: center;
    position: relative;
    z-index: 1;
}

.date-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
    align-items: flex-end;
}

.date-label {
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: rgba(255, 255, 255, 0.65);
}

.date-val {
    font-size: 13px;
    font-weight: 800;
    color: #fff;
}

.date-sep {
    width: 1px;
    height: 36px;
    background: rgba(255, 255, 255, 0.2);
}

/* Parties */
.invoice-parties {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    background: #eff6ff;
    border-top: 3px solid #bfdbfe;
    border-bottom: 3px solid #bfdbfe;
}

.party {
    padding: 20px 24px;
    border-right: 1px solid #dbeafe;
}

.party:last-child {
    border-right: none;
}

.party-label {
    font-size: 9px;
    font-weight: 900;
    letter-spacing: 2px;
    color: #1d4ed8;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.party-name {
    font-weight: 700;
    font-size: 13px;
    color: #111;
    margin-bottom: 4px;
}

.party-info {
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
    background: #1d4ed8;
}

.invoice-table th {
    padding: 10px 16px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1px;
    color: #1d4ed8;
    text-transform: uppercase;
}

.invoice-table td {
    padding: 10px 16px;
    border-bottom: 1px solid #eff6ff;
    color: #374151;
}

.alt td {
    background: #f0f7ff;
}

/* Bottom totals */
.invoice-bottom {
    display: flex;
    justify-content: flex-end;
    padding: 16px 24px;
    border-top: 2px solid #dbeafe;
    background: #f8faff;
}

.bottom-left {
    width: 280px;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.total-line {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #6b7280;
    padding: 4px 0;
    border-bottom: 1px dashed #dbeafe;
}

.total-grand {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 6px;
    padding: 10px 14px;
    background: linear-gradient(135deg, #1d4ed8, #0ea5e9);
    border-radius: 6px;
    color: #fff;
    font-weight: 900;
    font-size: 14px;
}

/* Footer */
.invoice-footer {
    background: linear-gradient(135deg, #1d4ed8 0%, #0ea5e9 100%);
    padding: 14px 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.footer-circles {
    position: absolute;
    right: -20px;
    top: -20px;
    display: flex;
    gap: -10px;
}

.circle {
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
    position: absolute;
}

.c1 {
    width: 80px;
    height: 80px;
    right: 10px;
    top: -30px;
}

.c2 {
    width: 50px;
    height: 50px;
    right: 60px;
    top: -10px;
}

.c3 {
    width: 30px;
    height: 30px;
    right: 100px;
    top: 5px;
}

.footer-text {
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 2px;
    color: #fff;
    text-transform: uppercase;
    position: relative;
    z-index: 1;
}

.text-left {
    text-align: left;
}

.text-right {
    text-align: right;
}
</style>