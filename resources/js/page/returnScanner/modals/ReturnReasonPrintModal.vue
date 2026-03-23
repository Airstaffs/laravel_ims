<!-- resources/js/modules/returnscanner/modals/ReturnReasonPrintModal.vue -->
<template>
    <div v-if="show" class="return-reason-print-overlay">
        <div class="return-reason-print-modal">

            <!-- Header -->
            <div class="rrp-header">
                <div class="rrp-header-icon">
                    <i class="fas fa-print"></i>
                </div>
                <div>
                    <h5 class="rrp-title">Print Return Reason Label?</h5>
                    <p class="rrp-subtitle">A return reason label can be attached to the item for processing.</p>
                </div>
                <button class="rrp-close" @click="$emit('skip')">&times;</button>
            </div>

            <!-- Return Info Summary -->
            <div class="rrp-info-card" v-if="returnInfo">
                <div class="rrp-info-row" v-if="returnInfo.serial">
                    <span class="rrp-info-label"><i class="fas fa-barcode"></i> Serial</span>
                    <span class="rrp-info-value">{{ returnInfo.serial }}</span>
                </div>
                <div class="rrp-info-row" v-if="returnInfo.returnId">
                    <span class="rrp-info-label"><i class="fas fa-hashtag"></i> Return ID</span>
                    <span class="rrp-info-value">{{ returnInfo.returnId }}</span>
                </div>
                <div class="rrp-info-row" v-if="returnInfo.returnReason">
                    <span class="rrp-info-label"><i class="fas fa-exclamation-circle"></i> Reason</span>
                    <span class="rrp-info-value reason-text">{{ returnInfo.returnReason }}</span>
                </div>
                <div class="rrp-info-row" v-if="returnInfo.buyerName">
                    <span class="rrp-info-label"><i class="fas fa-user"></i> Buyer</span>
                    <span class="rrp-info-value">{{ returnInfo.buyerName }}</span>
                </div>
                <div class="rrp-info-row" v-if="returnInfo.location">
                    <span class="rrp-info-label"><i class="fas fa-map-marker-alt"></i> Location</span>
                    <span class="rrp-info-value">{{ returnInfo.location }}</span>
                </div>
            </div>

            <!-- Printer Selection -->
            <div class="rrp-printer-section">
                <label class="rrp-section-label">Select Printer</label>

                <!-- Loading -->
                <div v-if="loadingPrinters" class="rrp-loading">
                    <i class="fas fa-spinner fa-spin"></i> Loading printers...
                </div>

                <!-- No printers -->
                <div v-else-if="!availablePrinters.length" class="rrp-no-printers">
                    <i class="fas fa-exclamation-triangle"></i> No printers available.
                </div>

                <!-- Single printers -->
                <div v-else class="rrp-printer-list">
                    <label
                        v-for="p in singlePrinters"
                        :key="p.printerid"
                        class="rrp-printer-option"
                        :class="{ selected: selectedPrinterId === String(p.printerid) }"
                    >
                        <input
                            type="radio"
                            :value="String(p.printerid)"
                            v-model="selectedPrinterId"
                        />
                        <i class="fas fa-print"></i>
                        <span>{{ p.printername_short || p.printername }}</span>
                    </label>

                    <!-- Married printer pairs -->
                    <label
                        v-for="group in marriedPrinterGroups"
                        :key="group.value"
                        class="rrp-printer-option married"
                        :class="{ selected: selectedPrinterId === group.value }"
                    >
                        <input
                            type="radio"
                            :value="group.value"
                            v-model="selectedPrinterId"
                        />
                        <i class="fas fa-link"></i>
                        <span>{{ group.label }}</span>
                    </label>
                </div>

                <!-- Remember preference -->
                <label class="rrp-remember" v-if="selectedPrinterId">
                    <input type="checkbox" v-model="rememberPrinter" />
                    <span>Remember this printer for future returns</span>
                </label>
            </div>

            <!-- Actions -->
            <div class="rrp-actions">
                <button class="rrp-btn-skip" @click="$emit('skip')" :disabled="isPrinting">
                    <i class="fas fa-forward"></i> Skip
                </button>
                <button
                    class="rrp-btn-print"
                    @click="confirmPrint"
                    :disabled="!selectedPrinterId || isPrinting || loadingPrinters"
                >
                    <span v-if="isPrinting">
                        <i class="fas fa-spinner fa-spin"></i> Printing...
                    </span>
                    <span v-else>
                        <i class="fas fa-print"></i> Print Label
                    </span>
                </button>
            </div>

        </div>
    </div>
</template>

<script>
export default {
    name: "ReturnReasonPrintModal",
    props: {
        show: { type: Boolean, default: false },
        returnInfo: { type: Object, default: null },   // { serial, returnId, returnReason, buyerName, location }
        availablePrinters: { type: Array, default: () => [] },
        loadingPrinters: { type: Boolean, default: false },
        rememberedPrinterId: { type: String, default: null },
        singlePrinters: { type: Array, default: () => [] },
        marriedPrinterGroups: { type: Array, default: () => [] },
    },
    emits: ['skip', 'print', 'remember-printer'],
    data() {
        return {
            selectedPrinterId: null,
            rememberPrinter: false,
            isPrinting: false,
        };
    },
    watch: {
        // Auto-select remembered printer when modal opens
        show(val) {
            if (val) {
                this.isPrinting = false;
                this.selectedPrinterId = this.rememberedPrinterId || null;
                this.rememberPrinter = !!this.rememberedPrinterId;
            }
        },
        rememberedPrinterId(val) {
            if (val && !this.selectedPrinterId) {
                this.selectedPrinterId = val;
            }
        },
    },
    methods: {
        confirmPrint() {
            if (!this.selectedPrinterId) return;
            this.isPrinting = true;

            if (this.rememberPrinter) {
                this.$emit('remember-printer', this.selectedPrinterId);
            }

            // done() callback resets the spinner from the parent
            this.$emit('print', {
                printerId: this.selectedPrinterId,
                done: () => { this.isPrinting = false; },
            });
        },
    },
};
</script>

<style scoped>
.return-reason-print-overlay {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.55);
    padding: 16px;
}

.return-reason-print-modal {
    background: #fff;
    border-radius: 14px;
    width: 100%;
    max-width: 480px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
    overflow: hidden;
    animation: rrp-slide-in 0.2s ease;
}

@keyframes rrp-slide-in {
    from { opacity: 0; transform: translateY(-20px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Header ── */
.rrp-header {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 20px 20px 16px;
    border-bottom: 1px solid #f0f0f0;
}

.rrp-header-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: linear-gradient(135deg, #3b82f6, #6366f1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 18px;
    flex-shrink: 0;
}

.rrp-title {
    margin: 0 0 3px;
    font-size: 16px;
    font-weight: 700;
    color: #1e293b;
}

.rrp-subtitle {
    margin: 0;
    font-size: 12px;
    color: #64748b;
}

.rrp-close {
    margin-left: auto;
    background: none;
    border: none;
    font-size: 22px;
    color: #94a3b8;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    flex-shrink: 0;
}
.rrp-close:hover { color: #475569; }

/* ── Info card ── */
.rrp-info-card {
    margin: 14px 20px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}

.rrp-info-row {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 9px 14px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
}
.rrp-info-row:last-child { border-bottom: none; }

.rrp-info-label {
    min-width: 90px;
    color: #64748b;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
}
.rrp-info-label i { width: 14px; text-align: center; }

.rrp-info-value {
    color: #1e293b;
    font-weight: 500;
    flex: 1;
    word-break: break-word;
}

.reason-text {
    color: #dc2626;
    font-style: italic;
}

/* ── Printer section ── */
.rrp-printer-section {
    padding: 0 20px 14px;
}

.rrp-section-label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #475569;
    margin-bottom: 10px;
}

.rrp-loading,
.rrp-no-printers {
    font-size: 13px;
    color: #94a3b8;
    padding: 10px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.rrp-printer-list {
    display: flex;
    flex-direction: column;
    gap: 7px;
    max-height: 170px;
    overflow-y: auto;
}

.rrp-printer-option {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    color: #334155;
    transition: all 0.15s;
}
.rrp-printer-option:hover { border-color: #3b82f6; background: #eff6ff; }
.rrp-printer-option.selected { border-color: #3b82f6; background: #eff6ff; color: #1d4ed8; font-weight: 600; }
.rrp-printer-option.married { border-style: dashed; }
.rrp-printer-option input[type="radio"] { accent-color: #3b82f6; }

.rrp-remember {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    font-size: 12px;
    color: #64748b;
    cursor: pointer;
}
.rrp-remember input { accent-color: #3b82f6; }

/* ── Actions ── */
.rrp-actions {
    display: flex;
    gap: 10px;
    padding: 14px 20px 20px;
    border-top: 1px solid #f0f0f0;
}

.rrp-btn-skip {
    flex: 1;
    padding: 11px;
    border: 1.5px solid #e2e8f0;
    background: #fff;
    color: #64748b;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    transition: all 0.15s;
}
.rrp-btn-skip:hover:not(:disabled) { background: #f8fafc; border-color: #cbd5e1; }

.rrp-btn-print {
    flex: 2;
    padding: 11px;
    border: none;
    background: linear-gradient(135deg, #3b82f6, #6366f1);
    color: #fff;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    transition: all 0.15s;
}
.rrp-btn-print:hover:not(:disabled) { opacity: 0.9; transform: translateY(-1px); }
.rrp-btn-print:disabled,
.rrp-btn-skip:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}
</style>