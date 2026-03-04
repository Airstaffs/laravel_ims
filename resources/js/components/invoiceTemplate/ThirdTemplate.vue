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

                    <!-- Top Bar -->
                    <div class="invoice-topbar">
                        <div class="topbar-accent" />
                        <div class="topbar-content">
                            <div class="topbar-left">
                                <span class="invoice-label">INVOICE</span>
                                <span class="invoice-num">#{{ invoiceNumber }}</span>
                            </div>
                            <div class="topbar-right">
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
                    </div>

                    <!-- Parties -->
                    <div class="invoice-parties">
                        <div class="party-block">
                            <div class="party-label"><span class="dot" />SELLER</div>
                            <div class="party-name">{{ currentSupplier.name }}</div>
                            <div class="party-text">{{ currentSupplier.address1 }}</div>
                            <div class="party-text">{{ currentSupplier.address2 }}</div>
                            <div class="party-text">{{ currentSupplier.contact }}</div>
                        </div>
                        <div class="party-divider" />
                        <div class="party-block">
                            <div class="party-label"><span class="dot" />BILL TO</div>
                            <div class="party-name">[Client's Company Name]</div>
                            <div class="party-text">[Client's Company Address Line 1]</div>
                            <div class="party-text">[Client's Company Address Line 2]</div>
                        </div>
                        <div class="party-divider" />
                        <div class="party-block">
                            <div class="party-label"><span class="dot" />PAYMENT DETAILS</div>
                            <div class="party-text">{{ currentSupplier.email }}</div>
                            <div class="party-text">{{ currentSupplier.websiteAddress }}</div>
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

                    <!-- Totals + Footer -->
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
    name: 'ProductInvoiceGreen',
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
        subtotal() { return this.currentSupplier?.products.reduce((s, p) => s + p.totalPrice, 0) ?? 0; },
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
    font-family: 'Arial', sans-serif;
    font-size: 12px;
    color: #222;
    width: 100%;
}

/* Tabs */
.supplier-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 16px;
}

.tab-btn {
    padding: 6px 16px;
    border-radius: 4px;
    border: 2px solid #059669;
    background: #fff;
    color: #059669;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    transition: all 0.2s;
}

.tab-btn.active,
.tab-btn:hover {
    background: #059669;
    color: #fff;
}

/* Invoice shell */
.invoice-scaler {
    overflow: hidden;
}

.invoice {
    background: #fff;
    border-radius: 0;
    overflow: hidden;
    border: 1px solid #d1fae5;
    width: 700px;
    box-sizing: border-box;
}

/* Top bar */
.invoice-topbar {
    background: #fff;
    border-left: 6px solid #059669;
}

.topbar-accent {
    height: 6px;
    background: linear-gradient(90deg, #059669, #34d399);
}

.topbar-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 32px;
}

.topbar-left {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.invoice-label {
    font-size: 38px;
    font-weight: 900;
    letter-spacing: 6px;
    color: #059669;
    line-height: 1;
}

.invoice-num {
    font-size: 14px;
    color: #6b7280;
    letter-spacing: 1px;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

.date-item {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
}

.date-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #9ca3af;
}

.date-val {
    font-size: 13px;
    font-weight: 700;
    color: #111;
}

.date-divider {
    width: 1px;
    height: 32px;
    background: #d1fae5;
}

/* Parties */
.invoice-parties {
    display: flex;
    padding: 20px 32px;
    gap: 0;
    border-top: 1px solid #d1fae5;
    border-bottom: 1px solid #d1fae5;
    background: #f0fdf4;
}

.party-block {
    flex: 1;
    padding: 0 16px;
}

.party-block:first-child {
    padding-left: 0;
}

.party-block:last-child {
    padding-right: 0;
}

.party-divider {
    width: 1px;
    background: #d1fae5;
    margin: 4px 0;
}

.party-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.5px;
    color: #059669;
    margin-bottom: 8px;
    text-transform: uppercase;
}

.dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #059669;
    display: inline-block;
}

.party-name {
    font-weight: 700;
    font-size: 13px;
    color: #111;
    margin-bottom: 4px;
}

.party-text {
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

.invoice-table th,
.invoice-table td {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.invoice-table thead tr {
    background: #ecfdf5;
}

.invoice-table th {
    padding: 10px 24px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1px;
    color: #059669;
    text-transform: uppercase;
    border-bottom: 2px solid #d1fae5;
}

.invoice-table td {
    padding: 10px 24px;
    border-bottom: 1px solid #f3f4f6;
    color: #374151;
}

.row-alt td {
    background: #f9fafb;
}

/* Bottom */
.invoice-bottom {
    display: flex;
    justify-content: flex-end;
    padding: 16px 24px;
    border-top: 2px solid #d1fae5;
    background: #f0fdf4;
}

.invoice-totals {
    width: 280px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.total-line {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    color: #6b7280;
    padding: 4px;
    border-bottom: 1px solid #d1fae5;
    background-color: #059669;
    color: #fff;
    font-weight: 600;
    border-radius: 0.4rem;
}

.total-line:last-child {
    border-bottom: none;
}

.total-line.grand {
    margin-top: 6px;
    background: #059669;
    color: #fff;
    font-weight: 800;
    font-size: 13px;
    padding: 10px 12px;
    border-radius: 6px;
}

/* Footer */
.invoice-footer {
    background: #059669;
    color: #fff;
    text-align: center;
    padding: 12px;
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