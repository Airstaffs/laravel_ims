<template>
    <div v-if="show" class="printer-modal">
        <div class="printer-modal-overlay" @click="$emit('close')"></div>
        <div class="printer-modal-content">

            <div class="printer-modal-header">
                <h5>Select Printer</h5>
                <button class="printer-modal-close" @click="$emit('close')">&times;</button>
            </div>

            <div class="printer-modal-body">

                <!-- Loading -->
                <div v-if="loadingPrinters" class="printer-loading">
                    <i class="pi pi-spin pi-spinner"></i>
                    <span>Loading printers...</span>
                </div>

                <div v-else>
                    <!-- Items Summary -->
                    <div class="print-summary">
                        <i class="pi pi-print"></i>
                        Printing <strong>{{ selectedItemsForPrint.length }}</strong>
                        {{ selectedItemsForPrint.length === 1 ? 'item' : 'items' }}
                        <span class="fnsku-badge">FNSKU Label Only</span>
                    </div>

                    <!-- Items Preview -->
                    <div class="items-preview">
                        <div
                            v-for="item in selectedItemsForPrint"
                            :key="item.productId"
                            class="item-preview-row"
                        >
                            <i class="pi pi-barcode"></i>
                            <span class="item-serial">{{ item.serialNumber }}</span>
                            <span class="item-fnsku">{{ item.fnsku }}</span>
                        </div>
                    </div>

                    <hr />

                    <!-- No Printers -->
                    <div v-if="availablePrinters.length === 0" class="no-printers">
                        <i class="pi pi-exclamation-triangle"></i>
                        No active printers found. Please check printer configuration.
                    </div>

                    <div v-else>
                        <!-- Single Printers -->
                        <div v-if="singlePrinters.length > 0" class="printer-group">
                            <p class="printer-group-label">
                                <i class="pi pi-desktop"></i> Single Printers
                            </p>
                            <div
                                v-for="printer in singlePrinters"
                                :key="printer.printerid"
                                class="printer-option"
                                :class="{ selected: localSelected === String(printer.printerid) }"
                                @click="selectPrinter(String(printer.printerid))"
                            >
                                <input
                                    type="radio"
                                    :id="`printer-${printer.printerid}`"
                                    :value="String(printer.printerid)"
                                    :checked="localSelected === String(printer.printerid)"
                                    @change="selectPrinter(String(printer.printerid))"
                                />
                                <label :for="`printer-${printer.printerid}`">
                                    {{ printer.printername_short }}
                                </label>
                            </div>
                        </div>

                        <!-- Paired Printers -->
                        <div v-if="marriedPrinterGroups.length > 0" class="printer-group">
                            <p class="printer-group-label">
                                <i class="pi pi-link"></i> Paired Printers
                            </p>
                            <div
                                v-for="group in marriedPrinterGroups"
                                :key="group.value"
                                class="printer-option"
                                :class="{ selected: localSelected === group.value }"
                                @click="selectPrinter(group.value)"
                            >
                                <input
                                    type="radio"
                                    :id="`group-${group.value}`"
                                    :value="group.value"
                                    :checked="localSelected === group.value"
                                    @change="selectPrinter(group.value)"
                                />
                                <label :for="`group-${group.value}`">
                                    {{ group.label }}
                                </label>
                            </div>
                        </div>

                        <hr />

                        <!-- Remembered Printer -->
                        <div v-if="rememberedPrinterId" class="remembered-printer">
                            <i class="pi pi-bookmark-fill"></i>
                            <span>Last used printer saved</span>
                            <button class="btn-clear-pref" @click="$emit('clear-preference')">
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Debug: show current selection (remove after testing) -->
            <!-- <div style="padding:8px;background:#fff3cd;font-size:11px;">localSelected: {{ localSelected }}</div> -->

            <!-- Full-modal loading overlay -->
            <div v-if="printing" class="printing-overlay">
                <div class="printing-overlay-inner">
                    <div class="printing-spinner"></div>
                    <p class="printing-label">Sending to printer...</p>
                    <p class="printing-sub">Please wait, do not close this window</p>
                </div>
            </div>

            <div class="printer-modal-footer">
   
                <button
                    class="btn-print"
                    :disabled="!localSelected || printing || availablePrinters.length === 0"
                    @click="onConfirm"
                >
                    <span v-if="printing" class="btn-spinner"></span>
                    <i v-else class="pi pi-print"></i>
                    {{ printing ? 'Printing...' : 'Print FNSKU' }}
                </button>
            </div>

        </div>
    </div>
</template>

<script>
export default {
    name: "PrinterSelectionModal",
    props: {
        show:                   { type: Boolean,  required: true },
        loadingPrinters:        { type: Boolean,  default: false },
        isProcessing:           { type: Boolean,  default: false },
        availablePrinters:      { type: Array,    default: () => [] },
        selectedItemsForPrint:  { type: Array,    default: () => [] },
        selectedPrinterForPrint:{ type: [String, Number], default: null },
        printSmallLabelOnly:    { type: Boolean,  default: false },
        rememberedPrinterId:    { type: [String, Number], default: null },
        singlePrinters:         { type: Array,    default: () => [] },
        marriedPrinterGroups:   { type: Array,    default: () => [] },
    },
    emits: ['close', 'confirm', 'clear-preference'],
    data() {
        return {
            localSelected: null,
            printing: false,  // local loading state — don't rely on parent prop
        };
    },
    watch: {
        show(val) {
            if (val) {
                this.localSelected = this.rememberedPrinterId
                    ? String(this.rememberedPrinterId)
                    : null;
                this.printing = false;
            } else {
                this.localSelected = null;
                this.printing = false;
            }
        },
    },
    methods: {
        selectPrinter(id) {
            this.localSelected = String(id);
        },
        onConfirm() {
            if (!this.localSelected || this.printing) return;
            this.printing = true;
            this.$emit('confirm', {
                printerId: this.localSelected,
                fnskuOnly: true,
                done: () => { this.printing = false; }, // callback so parent can stop spinner
            });
        },
    },
};
</script>

<style scoped>
.printer-modal {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
}
.printer-modal-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.5);
}
.printer-modal-content {
    position: relative; /* needed for overlay anchoring */
    z-index: 2;
    background: #fff;
    width: 460px;
    max-width: 95vw;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    overflow: hidden;
}
.printer-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}
.printer-modal-header h5 { margin: 0; font-size: 16px; font-weight: 700; }
.printer-modal-close {
    border: none;
    background: transparent;
    font-size: 22px;
    cursor: pointer;
    color: #6c757d;
    line-height: 1;
}
.printer-modal-body { padding: 16px 20px; max-height: 65vh; overflow-y: auto; }
.printer-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 20px;
    border-top: 1px solid #dee2e6;
    background: #f8f9fa;
}

/* Loading */
.printer-loading {
    display: flex;
    align-items: center;
    gap: 10px;
    justify-content: center;
    padding: 24px 0;
    color: #6c757d;
    font-size: 14px;
}
.printer-loading i { font-size: 1.5rem; color: #0d6efd; }

/* Summary */
.print-summary {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #e9f5ff;
    border: 1px solid #b8daff;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 14px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}
.print-summary i { color: #0d6efd; }
.fnsku-badge {
    margin-left: auto;
    background: #0d6efd;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 20px;
}

/* Items Preview */
.items-preview {
    max-height: 90px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 6px 10px;
    margin-bottom: 4px;
}
.item-preview-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    padding: 2px 0;
    color: #495057;
}
.item-serial { font-weight: 600; flex: 1; }
.item-fnsku  { color: #6c757d; font-size: 11px; }

/* Printer Groups */
.printer-group { margin-bottom: 12px; }
.printer-group-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6c757d;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 5px;
}
.printer-option {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    margin-bottom: 6px;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    user-select: none;
}
.printer-option:hover  { border-color: #0d6efd; background: #f0f7ff; }
.printer-option.selected { border-color: #0d6efd; background: #e9f5ff; }
.printer-option input[type="radio"] { accent-color: #0d6efd; cursor: pointer; flex-shrink: 0; }
.printer-option label  { margin: 0; cursor: pointer; font-size: 14px; font-weight: 500; flex: 1; }

/* Remembered */
.remembered-printer {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    padding: 6px 10px;
    background: #fff8e1;
    border: 1px solid #ffe082;
    border-radius: 6px;
    font-size: 12px;
    color: #795548;
}
.remembered-printer i { color: #f59e0b; }
.btn-clear-pref {
    margin-left: auto;
    border: 1px solid #ced4da;
    background: #fff;
    border-radius: 4px;
    padding: 2px 8px;
    font-size: 11px;
    cursor: pointer;
}

/* No Printers */
.no-printers {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px;
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 8px;
    font-size: 14px;
    color: #856404;
}

/* Footer Buttons */
.btn-cancel {
    padding: 7px 16px;
    border: 1px solid #ced4da;
    background: #fff;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
}
.btn-cancel:hover { background: #f8f9fa; }
.btn-print {
    padding: 7px 18px;
    border: none;
    background: #198754;
    color: #fff;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
    min-width: 130px;
    justify-content: center;
}
.btn-print:hover:not(:disabled) { background: #157347; }
.btn-print:disabled {
    background: #6c757d;
    cursor: not-allowed;
    opacity: 0.65;
}
/* spinner inside button */
.btn-spinner {
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255,255,255,0.4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: btn-spin 0.7s linear infinite;
    flex-shrink: 0;
}
@keyframes btn-spin {
    to { transform: rotate(360deg); }
}

@media (max-width: 576px) {
    .items-preview { max-height: 60px; }
}

/* ── Printing overlay ── */
.printing-overlay {
    position: absolute;
    inset: 0;
    background: rgba(255, 255, 255, 0.92);
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    backdrop-filter: blur(2px);
    pointer-events: all; /* blocks all clicks */
}
.printing-overlay-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 32px;
}
.printing-spinner {
    width: 52px;
    height: 52px;
    border: 5px solid #dee2e6;
    border-top-color: #198754;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.printing-label {
    font-size: 16px;
    font-weight: 700;
    color: #198754;
    margin: 0;
}
.printing-sub {
    font-size: 12px;
    color: #6c757d;
    margin: 0;
}
</style>