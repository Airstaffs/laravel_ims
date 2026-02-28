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

                    <!-- Top Banner -->
                    <div class="invoice-banner">
                        <div class="banner-left">
                            <div class="invoice-title">INVOICE</div>
                            <div class="invoice-number">#{{ invoiceNumber }}</div>
                        </div>
                        <div class="banner-right">
                            <div class="date-row"><span class="date-label">Invoice Date</span><span
                                    class="date-value">{{ invoiceDate }}</span></div>
                            <div class="date-row"><span class="date-label">Due Date</span><span class="date-value">{{
                                    dueDate }}</span></div>
                        </div>
                    </div>

                    <!-- Seller / Bill To / Payment Details -->
                    <div class="invoice-parties">
                        <div class="party-card seller-card">
                            <div class="party-label">SELLER</div>
                            <div class="party-content">
                                <div class="party-name">{{ currentSupplier.name }}</div>
                                <div>{{ currentSupplier.address1 }}</div>
                                <div>{{ currentSupplier.address2 }}</div>
                                <div>{{ currentSupplier.contact }}</div>
                            </div>
                        </div>
                        <div class="party-card bill-card">
                            <div class="party-label">BILL TO</div>
                            <div class="party-content">
                                <div class="party-name">[Client's Company Name]</div>
                                <div>[Client's Company Address Line 1]</div>
                                <div>[Client's Company Address Line 2]</div>
                            </div>
                        </div>
                        <div class="party-card payment-card">
                            <div class="party-label">PAYMENT DETAILS</div>
                            <div class="party-content">
                                <div>{{ currentSupplier.email }}</div>
                                <div>{{ currentSupplier.websiteAddress }}</div>
                            </div>
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
                            <tr v-for="n in emptyRows" :key="`empty-${n}`"
                                :class="{ 'row-alt': (currentSupplier.products.length + n - 1) % 2 !== 0 }">
                                <td colspan="5">&nbsp;</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Totals -->
                    <div class="invoice-totals">
                        <table>
                            <tbody>
                                <tr>
                                    <td class="total-label">Subtotal</td>
                                    <td class="text-right">{{ fmt(subtotal) }}</td>
                                </tr>
                                <tr>
                                    <td class="total-label">Tax ({{ taxRate }}%)</td>
                                    <td class="text-right">{{ fmt(taxAmount) }}</td>
                                </tr>
                                <tr class="total-row">
                                    <td>Total to Pay</td>
                                    <td class="text-right">{{ fmt(total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer -->
                    <div class="invoice-footer">
                        <span>THANK YOU FOR YOUR BUSINESS!</span>
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
    name: 'ProductInvoiceColored',
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
    data() {
        return { currentIndex: 0 };
    },
    computed: {
        currentSupplier() { return this.suppliers[this.currentIndex] ?? null; },
        subtotal() { return this.currentSupplier?.products.reduce((sum, p) => sum + p.totalPrice, 0) ?? 0; },
        taxRate() { return this.currentSupplier?.products[0]?.tax ?? 0; },
        taxAmount() { return this.subtotal * (this.taxRate / 100); },
        total() { return this.subtotal + this.taxAmount; },
        emptyRows() { return Math.max(0, 8 - (this.currentSupplier?.products.length ?? 0)); },
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
        async downloadPdf() {
            const el = this.$refs.invoiceRef;
            const canvas = await html2canvas(el, { scale: 2, useCORS: true });
            const imgData = canvas.toDataURL('image/png');
            const pdf = new jsPDF('p', 'mm', 'a4');
            const pageWidth = pdf.internal.pageSize.getWidth();
            const pageHeight = (canvas.height * pageWidth) / canvas.width;
            pdf.addImage(imgData, 'PNG', 0, 0, pageWidth, pageHeight);
            pdf.save(`invoice-${this.invoiceNumber}.pdf`);
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
.invoice-wrapper {
    font-family: Arial, sans-serif;
    font-size: 12px;
    color: #333;
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
    border: 2px solid #4f46e5;
    background: #fff;
    color: #4f46e5;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    transition: all 0.2s;
}

.tab-btn.active,
.tab-btn:hover {
    background: #4f46e5;
    color: #fff;
}

.invoice-scaler {
    overflow: hidden;
}

.invoice {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 24px rgba(79, 70, 229, 0.08);
    width: 700px;
    box-sizing: border-box;
}

.invoice-banner {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: #fff;
    padding: 32px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}

.invoice-title {
    font-size: 40px;
    font-weight: 800;
    letter-spacing: 4px;
    line-height: 1;
}

.invoice-number {
    font-size: 16px;
    opacity: 0.85;
    letter-spacing: 1px;
}

.banner-right {
    display: flex;
    flex-direction: column;
    gap: 8px;
    text-align: right;
}

.date-row {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    align-items: center;
}

.date-label {
    font-size: 11px;
    opacity: 0.75;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.date-value {
    font-weight: 700;
    font-size: 13px;
    background: rgba(255, 255, 255, 0.15);
    padding: 2px 10px;
    border-radius: 20px;
}

.invoice-parties {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    border-bottom: 2px solid #f3f4f6;
}

.party-card {
    padding: 24px 28px;
    border-right: 1px solid #f3f4f6;
}

.party-card:last-child {
    border-right: none;
}

.seller-card,
.bill-card {
    border-top: 4px solid #4f46e5;
}

.payment-card {
    border-top: 4px solid #7c3aed;
}

.party-label {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.5px;
    color: #4f46e5;
    margin-bottom: 10px;
    text-transform: uppercase;
}

.party-name {
    font-weight: 700;
    font-size: 13px;
    color: #111;
    margin-bottom: 4px;
}

.party-content {
    line-height: 1.8;
    color: #6b7280;
    font-size: 12px;
}

.invoice-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.invoice-table th:first-child,
.invoice-table td:first-child {
    width: 35%;
    text-wrap: wrap;
}

.invoice-table th:not(:first-child),
.invoice-table td:not(:first-child) {
    width: 18%;
}

/* Second column QTY specific styles */
.invoice-table th:nth-child(2),
.invoice-table td:nth-child(2) {
    width: 12%;
    text-wrap: wrap;
}

/* Last columns */
.invoice-table th:last-child,
.invoice-table td:last-child {
    width: 12%;
}

.invoice-table thead tr {
    background: #f5f3ff;
}

.invoice-table th {
    padding: 12px 24px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1px;
    color: #4f46e5;
    text-transform: uppercase;
    border-bottom: 2px solid #e5e7eb;
}

.invoice-table td {
    padding: 11px 24px;
    border-bottom: 1px solid #f3f4f6;
    color: #374151;
}

.row-alt td,
.row-alt {
    background: #fafafa;
}

.invoice-totals {
    display: flex;
    justify-content: flex-end;
    padding: 16px 24px;
    background: #fafafa;
    border-top: 2px solid #f3f4f6;
}

.invoice-totals table {
    width: 280px;
    font-size: 12px;
}

.invoice-totals td {
    padding: 5px 12px;
}

.total-label {
    color: #6b7280;
}

.total-row td {
    font-weight: 800;
    font-size: 14px;
    color: #fff;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    padding: 10px 12px;
    border-radius: 4px;
}

.invoice-footer {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: #fff;
    text-align: center;
    padding: 14px;
    font-weight: 800;
    letter-spacing: 2px;
    font-size: 12px;
    text-transform: uppercase;
}

.text-left {
    text-align: left;
}

.text-right {
    text-align: right;
}
</style>