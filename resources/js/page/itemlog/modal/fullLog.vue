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
                        <div class="wl-meta-value">{{ logAsin || "—" }}</div>
                    </div>
                    <div class="wl-meta-item">
                        <div class="wl-meta-label">FNSKU</div>
                        <div class="wl-meta-value">
                            {{ log.fnsku || log.FNSKU || "—" }}
                        </div>
                    </div>
                    <div class="wl-meta-item">
                        <div class="wl-meta-label">Product</div>
                        <div class="wl-meta-value">
                            {{ log.product_name || log.ProductTitle || "—" }}
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
                            {{ log.date_labelled || log.lastDateUpdate || "—" }}
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
                            log.pcn_number || log.PCN || "—"
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label">Basket:</span>
                        <span class="wl-field-value">{{
                            log.basket_number || log.basketnumber || "—"
                        }}</span>
                    </div>
                </div>

                <!-- 2. Labelling Module -->
                <template v-if="log.passed_labeling || log.MSKUviewer">
                    <div class="wl-section-header wl-section-header--labelling">
                        <span>🏷️</span><span>2. LABELLING MODULE</span>
                    </div>
                    <div class="wl-section-body">
                        <div class="wl-field">
                            <span class="wl-field-label">Date Labelled:</span>
                            <span class="wl-field-value">{{
                                log.date_labelled || log.lastDateUpdate || "—"
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">Labelled By:</span>
                            <span class="wl-field-value">{{
                                log.labelled_by || log.Username || "—"
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">FNSKU:</span>
                            <span class="wl-field-value">{{
                                log.fnsku || log.FNSKU || "—"
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">ASIN:</span>
                            <span class="wl-field-value">{{
                                logAsin || "—"
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">MSKU:</span>
                            <span class="wl-field-value">{{
                                log.msku || log.MSKU || "—"
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">RPN:</span>
                            <span class="wl-field-value">{{
                                log.rpn || log.RPN || "—"
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">PRD:</span>
                            <span class="wl-field-value">{{
                                log.prd || log.PRD || "—"
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label">Priority Rank:</span>
                            <span class="wl-field-value">{{
                                log.priority_rank || log.priorityrank || "—"
                            }}</span>
                        </div>
                        <div
                            class="wl-field"
                            v-if="log.sticker_note || log.stickernote"
                        >
                            <span class="wl-field-label">Sticker Notes:</span>
                            <span class="wl-field-value">{{
                                log.sticker_note || log.stickernote
                            }}</span>
                        </div>
                        <div
                            class="wl-field"
                            v-if="log.employee_note || log.EmployeeNote"
                        >
                            <span class="wl-field-label">Employee Notes:</span>
                            <span class="wl-field-value">{{
                                log.employee_note || log.EmployeeNote
                            }}</span>
                        </div>
                        <div class="wl-field">
                            <span class="wl-field-label"
                                >Current Location:</span
                            >
                            <span class="wl-field-value">{{
                                log.current_location ||
                                log.ProductModuleLoc ||
                                "—"
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
                <template v-if="showTestingModule">
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
                                log.current_location ||
                                log.ProductModuleLoc ||
                                "—"
                            }}</span>
                        </div>
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
                        <div
                            v-if="testingWorkLogMeta.dateTested"
                            class="wl-field"
                        >
                            <span class="wl-field-label">Date Tested:</span>
                            <span class="wl-field-value">{{
                                testingWorkLogMeta.dateTested
                            }}</span>
                        </div>
                        <div v-if="testingWorkLogMeta.tester" class="wl-field">
                            <span class="wl-field-label">Tester:</span>
                            <span class="wl-field-value">{{
                                testingWorkLogMeta.tester
                            }}</span>
                        </div>
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
                                    'text-success': isPassValue(entry.value),
                                    'text-danger': isFailValue(entry.value),
                                }"
                            >
                                {{ entry.value || "—" }}
                            </span>
                        </div>
                        <div
                            v-if="
                                !testingWorkLogEntries.length &&
                                !testingWorkLogMeta.testResult
                            "
                            class="wl-field"
                        >
                            <span class="wl-field-label">Work Log:</span>
                            <span
                                class="wl-field-value"
                                style="color: #aaa; font-style: italic"
                                >Not recorded yet</span
                            >
                        </div>
                    </div>
                </template>

                <!-- 4. Cleaning Module -->
                <template v-if="showCleaningModule">
                    <div class="wl-section-header wl-section-header--cleaning">
                        <span>🧹</span><span>4. CLEANING MODULE</span>
                    </div>
                    <div class="wl-section-body">
                        <div
                            v-if="cleaningWorkLogMeta.dateCleaned"
                            class="wl-field"
                        >
                            <span class="wl-field-label">Date Cleaned:</span>
                            <span class="wl-field-value">{{
                                cleaningWorkLogMeta.dateCleaned
                            }}</span>
                        </div>
                        <div
                            v-if="cleaningWorkLogMeta.cleanedBy"
                            class="wl-field"
                        >
                            <span class="wl-field-label">Cleaned By:</span>
                            <span class="wl-field-value">{{
                                cleaningWorkLogMeta.cleanedBy
                            }}</span>
                        </div>
                        <div
                            v-for="(entry, i) in cleaningCategoryEntries"
                            :key="'cwl-' + i"
                            class="wl-field"
                        >
                            <span class="wl-field-label"
                                >{{ entry.name }}:</span
                            >
                            <span class="wl-field-value">
                                {{ entry.status || "—" }}
                                <span
                                    v-if="entry.notes"
                                    style="
                                        color: #64748b;
                                        font-size: 12px;
                                        margin-left: 6px;
                                    "
                                >
                                    — {{ entry.notes }}
                                </span>
                            </span>
                        </div>
                        <div
                            v-if="!cleaningCategoryEntries.length"
                            class="wl-field"
                        >
                            <span class="wl-field-label">Work Log:</span>
                            <span
                                class="wl-field-value"
                                style="color: #aaa; font-style: italic"
                                >Not recorded yet</span
                            >
                        </div>
                    </div>
                </template>
                <!-- 5. Packaging Module -->
                <template v-if="showPackagingModule">
                    <div
                        class="wl-section-header"
                        style="
                            background: #fdf2f8;
                            border-left: 4px solid #e91e8c;
                        "
                    >
                        <span>📦</span><span>5. PACKAGING MODULE</span>
                    </div>
                    <div class="wl-section-body">
                        <div
                            v-if="packagingWorkLogMeta.datePacked"
                            class="wl-field"
                        >
                            <span class="wl-field-label">Date Packaged:</span>
                            <span class="wl-field-value">{{
                                packagingWorkLogMeta.datePacked
                            }}</span>
                        </div>
                        <div
                            v-if="packagingWorkLogMeta.packedBy"
                            class="wl-field"
                        >
                            <span class="wl-field-label">Packaged By:</span>
                            <span class="wl-field-value">{{
                                packagingWorkLogMeta.packedBy
                            }}</span>
                        </div>
                        <div
                            v-if="packagingIncludedComponents.length"
                            class="wl-field"
                        >
                            <span class="wl-field-label"
                                >Components Included:</span
                            >
                            <span class="wl-field-value">{{
                                packagingIncludedComponents.join(", ")
                            }}</span>
                        </div>
                        <template
                            v-if="
                                packagingBoxSpecs.size || packagingBoxSpecs.type
                            "
                        >
                            <div v-if="packagingBoxSpecs.size" class="wl-field">
                                <span class="wl-field-label">Box Size:</span>
                                <span class="wl-field-value">{{
                                    packagingBoxSpecs.size
                                }}</span>
                            </div>
                            <div v-if="packagingBoxSpecs.type" class="wl-field">
                                <span class="wl-field-label">Box Type:</span>
                                <span class="wl-field-value">{{
                                    packagingBoxSpecs.type
                                }}</span>
                            </div>
                        </template>
                        <div v-if="packagingNotes" class="wl-field">
                            <span class="wl-field-label">Notes:</span>
                            <span class="wl-field-value">{{
                                packagingNotes
                            }}</span>
                        </div>
                        <div
                            v-if="
                                !packagingWorkLogMeta.datePacked &&
                                !packagingIncludedComponents.length
                            "
                            class="wl-field"
                        >
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

        // Works for both checklistLogs (asin) and HouseageController (ASIN / ASINviewer)
        logAsin() {
            return (
                this.log?.asin || this.log?.ASIN || this.log?.ASINviewer || null
            );
        },

        // ── Labeling config fields ─────────────────────────────────────────
        asinConfigFields() {
            if (!this.logAsin) return [];
            const parse = (key) => {
                try {
                    const r = localStorage.getItem(key);
                    return r ? JSON.parse(r) : [];
                } catch {
                    return [];
                }
            };
            const globalFields = parse("asin_global_config_labeling");
            const asinFields = parse(`asin_config_labeling:${this.logAsin}`);
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

        // ── Testing Module ─────────────────────────────────────────────────
        showTestingModule() {
            return (
                !!this.log?.passed_testing ||
                !!this.log?.twl_test_result ||
                !!this.log?.twl_date_tested
            );
        },

        savedTestingWorkLog() {
            if (!this.log?.testing_field_values) return null;
            try {
                return typeof this.log.testing_field_values === "string"
                    ? JSON.parse(this.log.testing_field_values)
                    : this.log.testing_field_values;
            } catch {
                return null;
            }
        },

        testingWorkLogMeta() {
            return {
                testResult: this.log?.twl_test_result || null,
                dateTested: this.log?.twl_date_tested || null,
                tester: this.log?.twl_tested_by || null,
            };
        },

        testingWorkLogEntries() {
            if (!this.savedTestingWorkLog || !this.logAsin) return [];
            const parse = (key) => {
                try {
                    const r = localStorage.getItem(key);
                    return r ? JSON.parse(r) : [];
                } catch {
                    return [];
                }
            };
            const globalFields = parse("asin_global_config_testing");
            const asinFields = parse(`asin_config_testing:${this.logAsin}`);
            const markedGlobals = globalFields.map((f) => ({
                ...f,
                _fromGlobal: true,
            }));
            const savedLabels = new Set(asinFields.map((f) => f.label));
            const definitions = [
                ...markedGlobals.filter((f) => !savedLabels.has(f.label)),
                ...asinFields,
            ];
            return definitions
                .map((def) => ({
                    label: def.label,
                    value: this.savedTestingWorkLog[def.label] ?? null,
                    _fromGlobal: def._fromGlobal ?? false,
                }))
                .filter((e) => e.value !== null);
        },

        // ── Packaging Module ───────────────────────────────────────────────
        showPackagingModule() {
            return (
                !!this.log?.pkg_date_packed ||
                !!this.log?.pkg_category_values ||
                !!this.log?.pkg_packaging_done
            );
        },

        packagingWorkLogMeta() {
            return {
                datePacked: this.log?.pkg_date_packed || null,
                packedBy: this.log?.pkg_packed_by || null,
                done: this.log?.pkg_packaging_done ?? null,
            };
        },

        savedPackagingWorkLog() {
            if (!this.log?.pkg_category_values) return null;
            try {
                return typeof this.log.pkg_category_values === "string"
                    ? JSON.parse(this.log.pkg_category_values)
                    : this.log.pkg_category_values;
            } catch {
                return null;
            }
        },

        packagingIncludedComponents() {
            if (!this.savedPackagingWorkLog) return [];
            const data = this.savedPackagingWorkLog;
            // Components are stored as { "Component Name": true/false }
            // Keys with __ prefix are internal (notes, etc.)
            return Object.keys(data)
                .filter(
                    (k) =>
                        !k.startsWith("__") &&
                        !k.includes("__") &&
                        data[k] === true,
                )
                .map((k) => {
                    // Append SKU if available from ASIN config
                    const asin = this.logAsin;
                    if (!asin) return k;
                    try {
                        const cfg = localStorage.getItem(
                            `asin_config_packaging:${asin}`,
                        );
                        const pkg = cfg ? JSON.parse(cfg) : null;
                        const comp = pkg?.components?.find((c) => c.name === k);
                        return comp?.sku ? `${k} (${comp.sku})` : k;
                    } catch {
                        return k;
                    }
                });
        },

        packagingNotes() {
            return this.savedPackagingWorkLog?.["__notes"] || null;
        },

        packagingBoxSpecs() {
            if (!this.logAsin) return {};
            const parse = (key) => {
                try {
                    const r = localStorage.getItem(key);
                    return r ? JSON.parse(r) : null;
                } catch {
                    return null;
                }
            };
            const asinPkg = parse(`asin_config_packaging:${this.logAsin}`);
            const globalPkg = parse("asin_global_config_packaging");
            const a = asinPkg?.boxSpecs || {};
            const g = globalPkg?.boxSpecs || {};
            return {
                size: a.size || g.size || "",
                type: a.type || g.type || "",
                weight: a.weight || g.weight || "",
                materials: a.materials || g.materials || "",
            };
        },

        // ── Cleaning Module ────────────────────────────────────────────────
        showCleaningModule() {
            return (
                !!this.log?.date_cleaned || !!this.log?.cleaning_category_values
            );
        },

        savedCleaningWorkLog() {
            if (!this.log?.cleaning_category_values) return null;
            try {
                return typeof this.log.cleaning_category_values === "string"
                    ? JSON.parse(this.log.cleaning_category_values)
                    : this.log.cleaning_category_values;
            } catch {
                return null;
            }
        },

        cleaningWorkLogMeta() {
            return {
                dateCleaned: this.log?.date_cleaned || null,
                cleanedBy: this.log?.cleaned_by || null,
                markDone: this.log?.cleaning_done ?? undefined,
            };
        },

        cleaningCategoryEntries() {
            if (!this.savedCleaningWorkLog) return [];
            const data = this.savedCleaningWorkLog;
            const entries = [];
            const seen = new Set();
            Object.keys(data).forEach((key) => {
                if (key.startsWith("__")) return;
                if (key.includes("__action__")) return;
                if (key.endsWith("__status")) {
                    const name = key.replace("__status", "");
                    if (!seen.has(name)) {
                        seen.add(name);
                        entries.push({
                            name,
                            status: data[key] || null,
                            notes: data[name + "__notes"] || null,
                        });
                    }
                }
            });
            return entries;
        },
    },

    methods: {
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

    watch: {
        modelValue(val) {
            if (val) console.log("FullLog log prop:", this.log);
        },
    },
};
</script>

<style scoped>
@import "../itemlog.css";
</style>
