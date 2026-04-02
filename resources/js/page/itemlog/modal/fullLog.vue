<template>
    <Dialog
        v-model:visible="visible"
        :showHeader="false"
        :modal="true"
        :style="{ width: '1200px', padding: '0', height: '90vh' }"
        :breakpoints="{ '1260px': '95vw' }"
        :pt="{
            content: {
                style: 'padding: 0; height: 100%; display: flex; flex-direction: column;',
            },
        }"
    >
        <div v-if="log" class="wl-page">
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
                        <span class="wl-field-label">Date Received:</span>
                        <span class="wl-field-value">{{
                            log.date_received || "—"
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label">Tracking Number:</span>
                        <span class="wl-field-value">{{
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
                        <span class="wl-field-label">Received By:</span>
                        <span class="wl-field-value">{{
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
                        <span class="wl-field-label">Condition Notes:</span>
                        <span class="wl-field-value">{{
                            log.condition_notes
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label">PCN:</span>
                        <span class="wl-field-value">{{
                            log.pcn_number || "—"
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label">Basket:</span>
                        <span class="wl-field-value">{{
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
                            <span class="wl-field-label">Date Labelled:</span>
                            <span class="wl-field-value">{{
                                log.date_labelled || "—"
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">Labelled By:</span>
                            <span class="wl-field-value">{{
                                log.labelled_by || "—"
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">FNSKU:</span>
                            <span class="wl-field-value">{{
                                log.fnsku || "—"
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">ASIN:</span>
                            <span class="wl-field-value">{{
                                log.asin || "—"
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">MSKU:</span>
                            <span class="wl-field-value">{{
                                log.msku || "—"
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">RPN:</span>
                            <span class="wl-field-value">{{
                                log.rpn || "—"
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">PRD:</span>
                            <span class="wl-field-value">{{
                                log.prd || "—"
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">Priority Rank:</span>
                            <span class="wl-field-value">{{
                                log.priority_rank || "—"
                            }}</span>
                        </div>
                        <div class="wl-field" v-if="log.sticker_note">
                            <span class="wl-field-label">Sticker Notes:</span>
                            <span class="wl-field-value">{{
                                log.sticker_note
                            }}</span>
                        </div>
                        <div class="wl-field" v-if="log.employee_note">
                            <span class="wl-field-label">Employee Notes:</span>
                            <span class="wl-field-value">{{
                                log.employee_note
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label"
                                >Current Location:</span
                            >
                            <span class="wl-field-value">{{
                                log.current_location || "—"
                            }}</span>
                        </div>
                        <template v-if="log.last_edited_at">
                            <div class="wl-field">
                                <span class="wl-field-label">Last Edited:</span>
                                <span class="wl-field-value">{{
                                    log.last_edited_at
                                }}</span>
                            </div>
                            <div class="wl-field">
                                <span class="wl-field-label">Edited By:</span>
                                <span class="wl-field-value">{{
                                    log.last_edited_by || "—"
                                }}</span>
                            </div>
                            <div class="wl-field" v-if="log.edit_before">
                                <span class="wl-field-label">Before Edit:</span>
                                <span class="wl-field-value wl-text-small">{{
                                    log.edit_before
                                }}</span>
                            </div>
                            <div class="wl-field" v-if="log.edit_after">
                                <span class="wl-field-label">After Edit:</span>
                                <span class="wl-field-value wl-text-small">{{
                                    log.edit_after
                                }}</span>
                            </div>
                        </template>
                        <template v-if="log.moved_to_validation_at">
                            <div class="wl-field">
                                <span class="wl-field-label"
                                    >Moved to Validation:</span
                                >
                                <span class="wl-field-value text-success">{{
                                    log.moved_to_validation_at
                                }}</span>
                            </div>
                            <div class="wl-field">
                                <span class="wl-field-label">Moved by:</span>
                                <span class="wl-field-value">{{
                                    log.moved_to_validation_by || "—"
                                }}</span>
                            </div>
                        </template>
                        <template v-if="log.moved_to_stockroom_at">
                            <div class="wl-field">
                                <span class="wl-field-label"
                                    >Moved to Stockroom:</span
                                >
                                <span class="wl-field-value text-success">{{
                                    log.moved_to_stockroom_at
                                }}</span>
                            </div>
                            <div class="wl-field">
                                <span class="wl-field-label">Moved by:</span>
                                <span class="wl-field-value">{{
                                    log.moved_to_stockroom_by || "—"
                                }}</span>
                            </div>
                        </template>

                        <!-- ASIN Config labeling fields -->
                        <div
                            v-for="(field, i) in asinConfigFields"
                            :key="'cfg-' + i"
                            class="wl-field"
                        >
                            <span class="wl-field-label">
                                {{ field.label }}:
                                <span
                                    v-if="field._fromGlobal"
                                    class="wl-global-badge"
                                    >Global</span
                                >
                            </span>
                            <span class="wl-field-value">{{
                                field.defaultValue || "—"
                            }}</span>
                        </div>
                    </div>
                </template>

                <!-- 3. Testing Module -->
                <template v-if="log.passed_testing">
                    <div class="wl-section-header wl-section-header--testing">
                        <span>🔬</span><span>3. TESTING MODULE</span>
                    </div>
                    <div class="wl-section-body">
                        <div class="wl-field">
                            <span class="wl-field-label">Status:</span>
                            <span class="wl-field-value text-success"
                                >Completed ✓</span
                            >
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label"
                                >Current Location:</span
                            >
                            <span class="wl-field-value">{{
                                log.current_location || "—"
                            }}</span>
                        </div>

                        <!-- Saved Testing Work Log values -->
                        <template v-if="testingWorkLogEntries.length">
                            <!-- Test Result Decision -->
                            <div
                                v-if="testingWorkLogMeta.testResult"
                                class="wl-field"
                            >
                                <span class="wl-field-label">Test Result:</span>
                                <span
                                    class="wl-field-value"
                                    :class="
                                        testingWorkLogMeta.testResult === 'pass'
                                            ? 'text-success'
                                            : 'text-danger'
                                    "
                                >
                                    {{
                                        testingWorkLogMeta.testResult === "pass"
                                            ? "PASS ✓"
                                            : "FAIL ✗"
                                    }}
                                </span>
                            </div>

                            <!-- Date Tested -->
                            <div
                                v-if="testingWorkLogMeta.dateTested"
                                class="wl-field"
                            >
                                <span class="wl-field-label">Date Tested:</span>
                                <span class="wl-field-value">{{
                                    testingWorkLogMeta.dateTested
                                }}</span>
                            </div>

                            <!-- Tester -->
                            <div
                                v-if="testingWorkLogMeta.tester"
                                class="wl-field"
                            >
                                <span class="wl-field-label">Tester:</span>
                                <span class="wl-field-value">{{
                                    testingWorkLogMeta.tester
                                }}</span>
                            </div>

                            <!-- Dynamic config fields with saved values -->
                            <div
                                v-for="(entry, i) in testingWorkLogEntries"
                                :key="'twl-' + i"
                                class="wl-field"
                            >
                                <span class="wl-field-label">
                                    {{ entry.label }}:
                                    <span
                                        v-if="entry._fromGlobal"
                                        class="wl-global-badge"
                                        >Global</span
                                    >
                                </span>
                                <span
                                    class="wl-field-value"
                                    :class="{
                                        'text-success': isPassValue(
                                            entry.value,
                                        ),
                                        'text-danger': isFailValue(entry.value),
                                    }"
                                >
                                    {{ entry.value || "—" }}
                                </span>
                            </div>
                        </template>

                        <!-- No work log saved yet -->
                        <div v-else class="wl-field">
                            <span class="wl-field-label">Work Log:</span>
                            <span
                                class="wl-field-value"
                                style="color: #aaa; font-style: italic"
                                >Not recorded yet</span
                            >
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

        /** Labeling config fields from localStorage */
        asinConfigFields() {
            if (!this.log?.asin) return [];
            const parse = (key) => {
                try {
                    const r = localStorage.getItem(key);
                    return r ? JSON.parse(r) : [];
                } catch {
                    return [];
                }
            };
            const globalFields = parse("asin_global_config_labeling");
            const asinFields = parse(`asin_config_labeling:${this.log.asin}`);
            const markedGlobals = globalFields.map((f) => ({
                ...f,
                _fromGlobal: true,
            }));
            const asinLabels = new Set(asinFields.map((f) => f.label));
            return [
                ...markedGlobals.filter((f) => !asinLabels.has(f.label)),
                ...asinFields,
            ];
        },

        /**
         * Load saved Testing Work Log values from localStorage.
         * Key: testing_worklog:{rtcounter}
         * Returns the saved { [label]: value } map.
         */
        savedTestingWorkLog() {
            if (!this.log?.rtcounter) return null;
            try {
                const raw = localStorage.getItem(
                    `testing_worklog:${this.log.rtcounter}`,
                );
                return raw ? JSON.parse(raw) : null;
            } catch {
                return null;
            }
        },

        /**
         * Meta fields saved alongside the work log:
         * testResult, dateTested, tester
         */
        testingWorkLogMeta() {
            if (!this.savedTestingWorkLog) return {};
            return {
                testResult: this.savedTestingWorkLog.__testResult || null,
                dateTested: this.savedTestingWorkLog.__dateTested || null,
                tester: this.savedTestingWorkLog.__tester || null,
            };
        },

        /**
         * Merge testing field definitions (localStorage) with saved values.
         * Returns array of { label, value, _fromGlobal }
         */
        testingWorkLogEntries() {
            if (!this.savedTestingWorkLog || !this.log?.asin) return [];

            const parse = (key) => {
                try {
                    const r = localStorage.getItem(key);
                    return r ? JSON.parse(r) : [];
                } catch {
                    return [];
                }
            };

            const globalFields = parse("asin_global_config_testing");
            const asinFields = parse(`asin_config_testing:${this.log.asin}`);
            const markedGlobals = globalFields.map((f) => ({
                ...f,
                _fromGlobal: true,
            }));
            const asinLabels = new Set(asinFields.map((f) => f.label));
            const definitions = [
                ...markedGlobals.filter((f) => !asinLabels.has(f.label)),
                ...asinFields,
            ];

            // Map definitions to saved values
            return definitions
                .map((def) => ({
                    label: def.label,
                    value: this.savedTestingWorkLog[def.label] ?? null,
                    _fromGlobal: def._fromGlobal ?? false,
                }))
                .filter((e) => e.value !== null); // only show fields that were filled
        },
    },

    methods: {
        /** Green text for pass-like values */
        isPassValue(val) {
            if (!val) return false;
            const v = String(val).toLowerCase();
            return (
                v.includes("ok") ||
                v.includes("pass") ||
                v.includes("good") ||
                v === "true"
            );
        },
        /** Red text for fail-like values */
        isFailValue(val) {
            if (!val) return false;
            const v = String(val).toLowerCase();
            return (
                v.includes("fail") ||
                v.includes("bad") ||
                v.includes("broken") ||
                v.includes("issue")
            );
        },
    },
};
</script>

<style scoped>
@import "../itemlog.css";
</style>
