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

                    <!-- Header -->
                    <div class="invoice-header">
                        <div class="invoice-meta">
                            <div><strong>Invoice Number</strong> &nbsp;{{ invoiceNumber }}</div>
                            <div><strong>Invoice Date</strong> &nbsp;&nbsp;&nbsp;{{ invoiceDate }}</div>
                            <div><strong>Due Date</strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ dueDate }}</div>
                        </div>
                        <div class="invoice-title">INVOICE</div>
                    </div>

                    <hr class="divider" />

                    <!-- Seller / Bill To / Payment Details -->
                    <div class="invoice-parties">
                        <div>
                            <div class="section-label">SELLER</div>
                            <div class="section-content">
                                <div>{{ currentSupplier.name }}</div>
                                <div>{{ currentSupplier.address1 }}</div>
                                <div>{{ currentSupplier.address2 }}</div>
                                <div>{{ currentSupplier.contact }}</div>
                            </div>
                        </div>
                        <div>
                            <div class="section-label">BILL TO</div>
                            <div class="section-content">
                                <div>[Client's Company Name]</div>
                                <div>[Client's Company Address Line 1]</div>
                                <div>[Client's Company Address Line 2]</div>
                            </div>
                        </div>
                        <div>
                            <div class="section-label">PAYMENT DETAILS</div>
                            <div class="section-content">
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
                            <tr v-for="(p, i) in currentSupplier.products" :key="i">
                                <td>{{ p.ProductTitle }}</td>
                                <td class="text-right">{{ p.quantity }}</td>
                                <td class="text-right">{{ fmt(p.price) }}</td>
                                <td class="text-right">{{ fmt(p.totalPrice) }}</td>
                                <td class="text-right">{{ p.tax }}%</td>
                            </tr>
                            <tr v-for="n in emptyRows" :key="`empty-${n}`">
                                <td colspan="5">&nbsp;</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Totals -->
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

                    <!-- Footer -->
                    <div class="invoice-footer">THANK YOU FOR YOUR BUSINESS!</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';

export default {
    name: 'ProductInvoice',
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
    color: #222;
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
    border-radius: 4px;
    border: 1px solid #ccc;
    background: #fff;
    color: #111;
    cursor: pointer;
    font-size: 12px;
}

.tab-btn.active {
    background: #111;
    color: #fff;
}

.invoice-scaler {
    overflow: hidden;
}

.invoice {
    background: #fff;
    padding: 40px;
    border: 1px solid #ddd;
    width: 700px;
    box-sizing: border-box;
}

.invoice-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
    line-height: 1.8;
}

.invoice-title {
    font-size: 36px;
    font-weight: bold;
    letter-spacing: 2px;
    color: #111;
}

.divider {
    border: none;
    border-top: 1px solid #aaa;
    margin-bottom: 16px;
}

.invoice-parties {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 16px;
    margin-bottom: 32px;
}

.section-label {
    font-weight: bold;
    font-size: 11px;
    margin-bottom: 6px;
}

.section-content {
    line-height: 1.7;
    color: #444;
}

.invoice-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    margin-bottom: 16px;
}

.invoice-table th:first-child,
.invoice-table td:first-child {
    width: 35%;
    text-wrap: wrap;
}

.invoice-table th:not(:first-child),
.invoice-table td:not(:first-child) {
    width: 16.25%;
}

.invoice-table thead tr {
    background: #111;
    color: #fff;
}

.invoice-table th {
    padding: 8px 10px;
    font-size: 11px;
    font-weight: bold;
    letter-spacing: 0.5px;
}

.invoice-table td {
    padding: 8px 10px;
    border-bottom: 1px solid #e5e5e5;
}

.invoice-totals {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 24px;
}

.invoice-totals table {
    width: 260px;
    font-size: 12px;
}

.invoice-totals td {
    padding: 4px 10px;
    color: #444;
}

.total-row td {
    font-weight: bold;
    font-size: 13px;
    border-top: 2px solid #111;
    padding-top: 6px;
    color: #111;
}

.invoice-footer {
    background: #111;
    color: #fff;
    text-align: center;
    padding: 12px;
    font-weight: bold;
    letter-spacing: 1px;
    font-size: 12px;
}

.text-left {
    text-align: left;
    color: #111;
}

.text-right {
    text-align: right;
    color: #111;
}
</style>