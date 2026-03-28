<template>
    <Dialog
        v-model:visible="visible"
        :showHeader="false"
        :modal="true"
        :style="{ width: '720px', padding: '0', height: '90vh' }"
        :breakpoints="{ '768px': '95vw' }"
        :pt="{
            content: {
                style: 'padding: 0; height: 100%; display: flex; flex-direction: column;',
            },
        }"
    >
        <div v-if="log" class="wl-page">
            <!-- Scrollable content -->
            <div class="wl-scrollable">
                <div class="wl-page-number">Page 1 of 1</div>

                <!-- Header -->
                <div class="wl-header">
                    <div class="wl-header-left">
                        <h2 class="wl-title">WORKFLOW LOG REPORT</h2>
                        <p class="wl-subtitle">
                            Complete Item Processing History
                        </p>
                    </div>
                    <div class="wl-serial-badge">
                        <div class="wl-serial-label">Serial Number</div>
                        <div class="wl-serial-value">
                            {{ log.serialnumber || "—" }}
                        </div>
                    </div>
                </div>

                <!-- Meta Row 1 -->
                <div class="wl-meta-grid">
                    <div class="wl-meta-item">
                        <div class="wl-meta-label">ASIN</div>
                        <div class="wl-meta-value">{{ log.asin || "—" }}</div>
                    </div>
                    <div class="wl-meta-item">
                        <div class="wl-meta-label">FNSKU</div>
                        <div class="wl-meta-value">{{ log.fnsku || "—" }}</div>
                    </div>
                    <div class="wl-meta-item">
                        <div class="wl-meta-label">Product</div>
                        <div class="wl-meta-value">
                            {{ log.product_name || "—" }}
                        </div>
                    </div>
                </div>

                <!-- Meta Row 2 -->
                <div class="wl-meta-grid wl-meta-grid--second">
                    <div class="wl-meta-item">
                        <div class="wl-meta-label">Date Received</div>
                        <div class="wl-meta-value">
                            {{ log.date_received || "—" }}
                        </div>
                    </div>
                    <div class="wl-meta-item">
                        <div class="wl-meta-label">Date Labelled</div>
                        <div class="wl-meta-value">
                            {{ log.date_labelled || "—" }}
                        </div>
                    </div>
                    <div class="wl-meta-item">
                        <div class="wl-meta-label">RT#</div>
                        <div class="wl-meta-value">
                            {{ log.rtcounter || "—" }}
                        </div>
                    </div>
                </div>

                <hr class="wl-divider" />

                <!-- 1. Received Module -->
                <div class="wl-section-header wl-section-header--received">
                    <span>📦</span><span>1. RECEIVED MODULE</span>
                </div>
                <div class="wl-section-body">
                    <div class="wl-field">
                        <span class="wl-field-label">Date Received:</span
                        ><span class="wl-field-value">{{
                            log.date_received || "—"
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label">Tracking Number:</span
                        ><span class="wl-field-value">{{
                            log.trackingnumber || "—"
                        }}</span>
                    </div>
                    <template v-if="parsedSerials.length">
                        <div
                            class="wl-field"
                            v-for="(sn, i) in parsedSerials"
                            :key="i"
                        >
                            <span class="wl-field-label"
                                >Serial Number{{
                                    i > 0 ? " " + (i + 1) : ""
                                }}:</span
                            >
                            <span class="wl-field-value">{{ sn }}</span>
                        </div>
                    </template>
                    <div class="wl-field" v-else>
                        <span class="wl-field-label">Serial Number:</span>
                        <span class="wl-field-value">—</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label"
                            >Working / Not Working:</span
                        >
                        <span
                            class="wl-field-value"
                            :class="
                                log.pass_fail_result === 'pass'
                                    ? 'text-success'
                                    : 'text-danger'
                            "
                        >
                            {{
                                log.pass_fail_result === "pass"
                                    ? "Working ✓"
                                    : "Not Working ✗"
                            }}
                        </span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label">Received By:</span
                        ><span class="wl-field-value">{{
                            log.received_by || "—"
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label"
                            >Item received correct on order:</span
                        >
                        <span
                            class="wl-field-value"
                            :class="
                                log.correct_on_order === 'yes'
                                    ? 'text-success'
                                    : 'text-danger'
                            "
                        >
                            {{
                                log.correct_on_order === "yes"
                                    ? "Yes ✓"
                                    : "No ✗"
                            }}
                        </span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label"
                            >Condition on Arrival:</span
                        >
                        <span
                            class="wl-field-value"
                            style="text-transform: capitalize"
                        >
                            {{ log.condition_on_arrival || "—"
                            }}{{
                                log.condition_on_arrival === "good" ? " ✓" : ""
                            }}
                        </span>
                    </div>
                    <div class="wl-field" v-if="log.condition_notes">
                        <span class="wl-field-label">Condition Notes:</span
                        ><span class="wl-field-value">{{
                            log.condition_notes
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label">PCN:</span
                        ><span class="wl-field-value">{{
                            log.pcn_number || "—"
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label">Basket:</span
                        ><span class="wl-field-value">{{
                            log.basket_number || "—"
                        }}</span>
                    </div>
                </div>

                <!-- 2. Labelling Module -->
                <template v-if="log.passed_labeling">
                    <div class="wl-section-header wl-section-header--labelling">
                        <span>🏷️</span><span>2. LABELLING MODULE</span>
                    </div>
                    <div class="wl-section-body">
                        <div class="wl-field">
                            <span class="wl-field-label">Date Labelled:</span
                            ><span class="wl-field-value">{{
                                log.date_labelled || "—"
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">Labelled By:</span
                            ><span class="wl-field-value">{{
                                log.labelled_by || "—"
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">FNSKU:</span
                            ><span class="wl-field-value">{{
                                log.fnsku || "—"
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">ASIN:</span
                            ><span class="wl-field-value">{{
                                log.asin || "—"
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">MSKU:</span
                            ><span class="wl-field-value">{{
                                log.msku || "—"
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">RPN:</span
                            ><span class="wl-field-value">{{
                                log.rpn || "—"
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">PRD:</span
                            ><span class="wl-field-value">{{
                                log.prd || "—"
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">Priority Rank:</span
                            ><span class="wl-field-value">{{
                                log.priority_rank || "—"
                            }}</span>
                        </div>
                        <div class="wl-field" v-if="log.sticker_note">
                            <span class="wl-field-label">Sticker Notes:</span
                            ><span class="wl-field-value">{{
                                log.sticker_note
                            }}</span>
                        </div>
                        <div class="wl-field" v-if="log.employee_note">
                            <span class="wl-field-label">Employee Notes:</span
                            ><span class="wl-field-value">{{
                                log.employee_note
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">Current Location:</span
                            ><span class="wl-field-value">{{
                                log.current_location || "—"
                            }}</span>
                        </div>
                        <template v-if="log.last_edited_at">
                            <div class="wl-field">
                                <span class="wl-field-label">Last Edited:</span
                                ><span class="wl-field-value">{{
                                    log.last_edited_at
                                }}</span>
                            </div>
                            <div class="wl-field">
                                <span class="wl-field-label">Edited By:</span
                                ><span class="wl-field-value">{{
                                    log.last_edited_by || "—"
                                }}</span>
                            </div>
                            <div class="wl-field" v-if="log.edit_before">
                                <span class="wl-field-label">Before Edit:</span
                                ><span class="wl-field-value wl-text-small">{{
                                    log.edit_before
                                }}</span>
                            </div>
                            <div class="wl-field" v-if="log.edit_after">
                                <span class="wl-field-label">After Edit:</span
                                ><span class="wl-field-value wl-text-small">{{
                                    log.edit_after
                                }}</span>
                            </div>
                        </template>
                        <template v-if="log.moved_to_validation_at">
                            <div class="wl-field">
                                <span class="wl-field-label"
                                    >Moved to Validation:</span
                                ><span class="wl-field-value text-success">{{
                                    log.moved_to_validation_at
                                }}</span>
                            </div>
                            <div class="wl-field">
                                <span class="wl-field-label">Moved by:</span
                                ><span class="wl-field-value">{{
                                    log.moved_to_validation_by || "—"
                                }}</span>
                            </div>
                        </template>
                        <template v-if="log.moved_to_stockroom_at">
                            <div class="wl-field">
                                <span class="wl-field-label"
                                    >Moved to Stockroom:</span
                                ><span class="wl-field-value text-success">{{
                                    log.moved_to_stockroom_at
                                }}</span>
                            </div>
                            <div class="wl-field">
                                <span class="wl-field-label">Moved by:</span
                                ><span class="wl-field-value">{{
                                    log.moved_to_stockroom_by || "—"
                                }}</span>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- 3. Testing Module -->
                <template v-if="log.passed_testing">
                    <div class="wl-section-header wl-section-header--testing">
                        <span>🔬</span><span>3. TESTING MODULE</span>
                    </div>
                    <div class="wl-section-body">
                        <div class="wl-field">
                            <span class="wl-field-label">Status:</span
                            ><span class="wl-field-value text-success"
                                >Completed ✓</span
                            >
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">Current Location:</span
                            ><span class="wl-field-value">{{
                                log.current_location || "—"
                            }}</span>
                        </div>
                    </div>
                </template>
            </div>
            <!-- END scrollable -->

            <!-- Fixed Footer -->
            <div class="wl-footer-actions">
                <Button
                    label="Print"
                    icon="pi pi-print"
                    class="p-button-success"
                    @click="$emit('print', log)"
                />
                <Button
                    label="Close"
                    icon="pi pi-times"
                    class="p-button-secondary"
                    @click="visible = false"
                />
            </div>
        </div>
    </Dialog>
</template>

<script>
import { Button, Dialog } from "primevue";

export default {
    name: "FullLog",
    components: { Button, Dialog },

    props: {
        modelValue: { type: Boolean, default: false },
        log: { type: Object, default: null },
    },

    emits: ["update:modelValue", "print"],

    computed: {
        visible: {
            get() {
                return this.modelValue;
            },
            set(val) {
                this.$emit("update:modelValue", val);
            },
        },
        parsedSerials() {
            if (!this.log) return [];
            return [
                this.log.serialnumber,
                this.log.serialnumberb,
                this.log.serialnumberc,
                this.log.serialnumberd,
                this.log.serialnumbere,
            ].filter(Boolean);
        },
    },
};
</script>

<style scoped>
@import "../itemlog.css";
</style>
