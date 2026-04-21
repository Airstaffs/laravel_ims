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

                <!-- 4. Repair Module -->
                <template v-if="showRepairModule">
                    <div
                        class="wl-section-header"
                        style="
                            background: #fff8f0;
                            border-left: 4px solid #e65100;
                        "
                    >
                        <span>🔧</span><span>4. REPAIR MODULE</span>
                    </div>
                    <div class="wl-section-body">
                        <div
                            v-if="repairWorkLogMeta.dateRepaired"
                            class="wl-field"
                        >
                            <span class="wl-field-label">Date Repaired:</span>
                            <span class="wl-field-value">{{
                                repairWorkLogMeta.dateRepaired
                            }}</span>
                        </div>
                        <div
                            v-if="repairWorkLogMeta.repairedBy"
                            class="wl-field"
                        >
                            <span class="wl-field-label">Repaired By:</span>
                            <span class="wl-field-value">{{
                                repairWorkLogMeta.repairedBy
                            }}</span>
                        </div>

                        <!-- Failed items from testing -->
                        <div v-if="repairFailedItems.length" class="wl-field">
                            <span class="wl-field-label"
                                >Failed Items from Testing:</span
                            >
                            <span class="wl-field-value">{{
                                repairFailedItems.join(", ")
                            }}</span>
                        </div>

                        <!-- Per-category repair actions + notes -->
                        <template
                            v-for="(entry, i) in repairCategoryEntries"
                            :key="'rwl-' + i"
                        >
                            <div class="wl-field">
                                <span class="wl-field-label">
                                    Repair Action - {{ entry.name }}:
                                </span>
                                <span class="wl-field-value">{{
                                    entry.status || "—"
                                }}</span>
                            </div>
                            <div v-if="entry.notes" class="wl-field">
                                <span class="wl-field-label">
                                    Repair Notes - {{ entry.name }}:
                                </span>
                                <span class="wl-field-value">{{
                                    entry.notes
                                }}</span>
                            </div>
                        </template>

                        <div
                            v-if="!repairCategoryEntries.length"
                            class="wl-field"
                        >
                            <span class="wl-field-label">Work Log:</span>
                            <span
                                class="wl-field-value"
                                style="color: #aaa; font-style: italic"
                                >Not recorded yet</span
                            >
                        </div>

                        <!-- Repair status / outcome -->
                        <div v-if="repairWorkLogMeta.markDone" class="wl-field">
                            <span class="wl-field-label">Repair Status:</span>
                            <span class="wl-field-value text-success">
                                Done — Returned to Testing
                            </span>
                        </div>
                    </div>
                </template>

                <!-- 5. Re-Testing Module -->
                <template v-if="showReTestingModule">
                    <div
                        class="wl-section-header"
                        style="
                            background: #f0fff4;
                            border-left: 4px solid #16a34a;
                        "
                    >
                        <span>🔁</span><span>5. RE-TESTING MODULE</span>
                    </div>
                    <div class="wl-section-body">
                        <div
                            v-if="reTestingWorkLogMeta.dateRetested"
                            class="wl-field"
                        >
                            <span class="wl-field-label">Date Re-tested:</span>
                            <span class="wl-field-value">{{
                                reTestingWorkLogMeta.dateRetested
                            }}</span>
                        </div>
                        <div
                            v-if="reTestingWorkLogMeta.tester"
                            class="wl-field"
                        >
                            <span class="wl-field-label">Tester:</span>
                            <span class="wl-field-value">{{
                                reTestingWorkLogMeta.tester
                            }}</span>
                        </div>

                        <!-- Re-test scope — which items were re-tested -->
                        <div v-if="reTestingScope.length" class="wl-field">
                            <span class="wl-field-label">Re-test Scope:</span>
                            <span class="wl-field-value">
                                {{ reTestingScope.join(", ") }} (repaired items
                                only)
                            </span>
                        </div>

                        <!-- Per-category re-test results -->
                        <div
                            v-for="(entry, i) in reTestingEntries"
                            :key="'rtl-' + i"
                            class="wl-field"
                        >
                            <span class="wl-field-label"
                                >{{ entry.label }}:</span
                            >
                            <span
                                class="wl-field-value"
                                :class="{
                                    'text-success': isPassValue(entry.value),
                                    'text-danger': isFailValue(entry.value),
                                }"
                            >
                                {{ entry.value || "—" }}
                                <span v-if="isPassValue(entry.value)"> ✓</span>
                            </span>
                        </div>

                        <!-- Re-test overall result -->
                        <div
                            v-if="reTestingWorkLogMeta.testResult"
                            class="wl-field"
                        >
                            <span class="wl-field-label">Re-test Result:</span>
                            <span
                                class="wl-field-value"
                                :class="
                                    reTestingWorkLogMeta.testResult === 'pass'
                                        ? 'text-success'
                                        : 'text-danger'
                                "
                                style="font-weight: 600"
                            >
                                {{
                                    reTestingWorkLogMeta.testResult === "pass"
                                        ? "PASS — All Repairs Successful"
                                        : "FAIL — Further Repair Needed"
                                }}
                            </span>
                        </div>

                        <div
                            v-if="
                                !reTestingEntries.length &&
                                !reTestingWorkLogMeta.testResult
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

                <!-- Cleaning Module (renumbered dynamically) -->
                <template v-if="showCleaningModule">
                    <div class="wl-section-header wl-section-header--cleaning">
                        <span>🧹</span>
                        <span>{{ cleaningModuleNumber }}. CLEANING MODULE</span>
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

                <!-- Packaging Module (renumbered dynamically) -->
                <template v-if="showPackagingModule">
                    <div
                        class="wl-section-header"
                        style="
                            background: #fdf2f8;
                            border-left: 4px solid #e91e8c;
                        "
                    >
                        <span>📦</span>
                        <span
                            >{{ packagingModuleNumber }}. PACKAGING MODULE</span
                        >
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
                    @click="printLog"
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
import Button from "primevue/button";
import Dialog from "primevue/dialog";

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

        // ── Repair Module ──────────────────────────────────────────────────
        showRepairModule() {
            return (
                !!this.log?.date_repaired ||
                !!this.log?.repair_category_values ||
                !!this.log?.repaired_by
            );
        },

        savedRepairWorkLog() {
            if (!this.log?.repair_category_values) return null;
            try {
                return typeof this.log.repair_category_values === "string"
                    ? JSON.parse(this.log.repair_category_values)
                    : this.log.repair_category_values;
            } catch {
                return null;
            }
        },

        repairWorkLogMeta() {
            return {
                dateRepaired: this.log?.date_repaired || null,
                repairedBy: this.log?.repaired_by || null,
                markDone: this.log?.repair_done ?? null,
            };
        },

        // Failed items list stored in repair work log payload
        repairFailedItems() {
            const raw = this.log?.repair_failed_items;
            if (!raw) return [];
            try {
                const parsed = typeof raw === "string" ? JSON.parse(raw) : raw;
                return Array.isArray(parsed) ? parsed : [];
            } catch {
                return [];
            }
        },

        // Per-category repair action + notes
        repairCategoryEntries() {
            if (!this.savedRepairWorkLog) return [];
            const data = this.savedRepairWorkLog;
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

        // ── Re-Testing Module ──────────────────────────────────────────────
        showReTestingModule() {
            return (
                !!this.log?.retest_date ||
                !!this.log?.retest_result ||
                !!this.log?.retest_field_values
            );
        },

        savedReTestingWorkLog() {
            if (!this.log?.retest_field_values) return null;
            try {
                return typeof this.log.retest_field_values === "string"
                    ? JSON.parse(this.log.retest_field_values)
                    : this.log.retest_field_values;
            } catch {
                return null;
            }
        },

        reTestingWorkLogMeta() {
            return {
                dateRetested: this.log?.retest_date || null,
                tester: this.log?.retest_by || null,
                testResult: this.log?.retest_result || null,
            };
        },

        // The categories that were re-tested (same as repairFailedItems scope)
        reTestingScope() {
            return this.repairFailedItems;
        },

        // Per-category re-test results using same ASIN config testing fields
        reTestingEntries() {
            if (!this.savedReTestingWorkLog) return [];
            const data = this.savedReTestingWorkLog;
            return Object.keys(data)
                .filter((k) => !k.startsWith("__") && !k.includes("__"))
                .map((k) => ({ label: k, value: data[k] }))
                .filter((e) => e.value !== null && e.value !== "");
        },

        // ── Dynamic module numbering ───────────────────────────────────────
        // Cleaning and Packaging shift their number when Repair/Re-Testing exist
        cleaningModuleNumber() {
            let num = 4;
            if (this.showRepairModule) num++;
            if (this.showReTestingModule) num++;
            return num;
        },

        packagingModuleNumber() {
            return this.cleaningModuleNumber + 1;
        },

        // ── Packaging Module ───────────────────────────────────────────────
        showPackagingModule() {
            return (
                !!this.log?.pkg_date_packed ||
                !!this.log?.pkg_category_values ||
                !!this.log?.pkg_packaging_done ||
                !!this.log?.date_packed ||
                !!this.log?.packaging_category_values ||
                !!this.log?.packaging_done
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
            return Object.keys(data)
                .filter(
                    (k) =>
                        !k.startsWith("__") &&
                        !k.includes("__") &&
                        data[k] === true,
                )
                .map((k) => {
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

        printLog() {
            if (!this.log) return;

            const pass = (v) => {
                if (!v) return false;
                const s = String(v).toLowerCase();
                return (
                    s.includes("ok") ||
                    s.includes("pass") ||
                    s.includes("good") ||
                    s === "true"
                );
            };
            const fail = (v) => {
                if (!v) return false;
                const s = String(v).toLowerCase();
                return (
                    s.includes("fail") ||
                    s.includes("bad") ||
                    s.includes("broken") ||
                    s.includes("issue")
                );
            };
            const color = (v) =>
                pass(v) ? "#16a34a" : fail(v) ? "#dc2626" : "#0f172a";

            const row = (label, value, valueColor) =>
                `<tr><td class="lbl">${label}</td><td class="val" style="color:${valueColor || "#0f172a"}">${value ?? "—"}</td></tr>`;

            const section = (icon, title, bgColor, borderColor, rows) =>
                `<div class="section">
            <div class="section-head" style="background:${bgColor};border-left:4px solid ${borderColor}">${icon} ${title}</div>
            <table>${rows}</table>
        </div>`;

            // 1. Received
            let receivedRows = "";
            receivedRows += row("Date Received", this.log.date_received);
            receivedRows += row("Tracking Number", this.log.trackingnumber);
            this.parsedSerials.forEach((sn, i) => {
                receivedRows += row(
                    `Serial Number${i > 0 ? " " + (i + 1) : ""}`,
                    sn,
                );
            });
            if (!this.parsedSerials.length)
                receivedRows += row("Serial Number", "—");
            receivedRows += row(
                "Working / Not Working",
                this.log.pass_fail_result === "pass"
                    ? "Working ✓"
                    : "Not Working ✗",
                this.log.pass_fail_result === "pass" ? "#16a34a" : "#dc2626",
            );
            receivedRows += row("Received By", this.log.received_by);
            receivedRows += row(
                "Item Correct on Order",
                this.log.correct_on_order === "yes" ? "Yes ✓" : "No ✗",
                this.log.correct_on_order === "yes" ? "#16a34a" : "#dc2626",
            );
            receivedRows += row(
                "Condition on Arrival",
                (this.log.condition_on_arrival || "—") +
                    (this.log.condition_on_arrival === "good" ? " ✓" : ""),
            );
            if (this.log.condition_notes)
                receivedRows += row(
                    "Condition Notes",
                    this.log.condition_notes,
                );
            receivedRows += row("PCN", this.log.pcn_number || this.log.PCN);
            receivedRows += row(
                "Basket",
                this.log.basket_number || this.log.basketnumber,
            );

            // 2. Labelling
            let labellingSection = "";
            if (this.log.passed_labeling || this.log.MSKUviewer) {
                let rows = "";
                rows += row(
                    "Date Labelled",
                    this.log.date_labelled || this.log.lastDateUpdate,
                );
                rows += row(
                    "Labelled By",
                    this.log.labelled_by || this.log.Username,
                );
                rows += row("FNSKU", this.log.fnsku || this.log.FNSKU);
                rows += row("ASIN", this.logAsin);
                rows += row("MSKU", this.log.msku || this.log.MSKU);
                rows += row("RPN", this.log.rpn || this.log.RPN);
                rows += row("PRD", this.log.prd || this.log.PRD);
                rows += row(
                    "Priority Rank",
                    this.log.priority_rank || this.log.priorityrank,
                );
                if (this.log.sticker_note || this.log.stickernote)
                    rows += row(
                        "Sticker Notes",
                        this.log.sticker_note || this.log.stickernote,
                    );
                if (this.log.employee_note || this.log.EmployeeNote)
                    rows += row(
                        "Employee Notes",
                        this.log.employee_note || this.log.EmployeeNote,
                    );
                rows += row(
                    "Current Location",
                    this.log.current_location || this.log.ProductModuleLoc,
                );
                if (this.log.last_edited_at) {
                    rows += row("Last Edited", this.log.last_edited_at);
                    rows += row("Edited By", this.log.last_edited_by);
                    if (this.log.edit_before)
                        rows += row("Before Edit", this.log.edit_before);
                    if (this.log.edit_after)
                        rows += row("After Edit", this.log.edit_after);
                }
                if (this.log.moved_to_validation_at) {
                    rows += row(
                        "Moved to Validation",
                        this.log.moved_to_validation_at,
                        "#16a34a",
                    );
                    rows += row("Moved by", this.log.moved_to_validation_by);
                }
                if (this.log.moved_to_stockroom_at) {
                    rows += row(
                        "Moved to Stockroom",
                        this.log.moved_to_stockroom_at,
                        "#16a34a",
                    );
                    rows += row("Moved by", this.log.moved_to_stockroom_by);
                }
                this.asinConfigFields.forEach((f) => {
                    rows += row(
                        f.label +
                            (f._fromGlobal
                                ? ' <span class="badge">Global</span>'
                                : ""),
                        f.defaultValue || "—",
                    );
                });
                labellingSection = section(
                    "🏷️",
                    "2. LABELLING MODULE",
                    "#f0fdf4",
                    "#16a34a",
                    rows,
                );
            }

            // 3. Testing
            let testingSection = "";
            if (this.showTestingModule) {
                let rows = "";
                rows += row("Status", "Completed ✓", "#16a34a");
                rows += row(
                    "Current Location",
                    this.log.current_location || this.log.ProductModuleLoc,
                );
                if (this.testingWorkLogMeta.testResult)
                    rows += row(
                        "Test Result",
                        this.testingWorkLogMeta.testResult === "pass"
                            ? "PASS ✓"
                            : "FAIL ✗",
                        this.testingWorkLogMeta.testResult === "pass"
                            ? "#16a34a"
                            : "#dc2626",
                    );
                if (this.testingWorkLogMeta.dateTested)
                    rows += row(
                        "Date Tested",
                        this.testingWorkLogMeta.dateTested,
                    );
                if (this.testingWorkLogMeta.tester)
                    rows += row("Tester", this.testingWorkLogMeta.tester);
                this.testingWorkLogEntries.forEach((e) => {
                    rows += row(
                        e.label +
                            (e._fromGlobal
                                ? ' <span class="badge">Global</span>'
                                : ""),
                        e.value || "—",
                        color(e.value),
                    );
                });
                testingSection = section(
                    "🔬",
                    "3. TESTING MODULE",
                    "#faf5ff",
                    "#7c3aed",
                    rows,
                );
            }

            // 4. Repair
            let repairSection = "";
            if (this.showRepairModule) {
                let rows = "";
                if (this.repairWorkLogMeta.dateRepaired)
                    rows += row(
                        "Date Repaired",
                        this.repairWorkLogMeta.dateRepaired,
                    );
                if (this.repairWorkLogMeta.repairedBy)
                    rows += row(
                        "Repaired By",
                        this.repairWorkLogMeta.repairedBy,
                    );
                if (this.repairFailedItems.length)
                    rows += row(
                        "Failed Items from Testing",
                        this.repairFailedItems.join(", "),
                    );
                this.repairCategoryEntries.forEach((e) => {
                    rows += row(`Repair Action — ${e.name}`, e.status || "—");
                    if (e.notes)
                        rows += row(`Repair Notes — ${e.name}`, e.notes);
                });
                if (this.repairWorkLogMeta.markDone)
                    rows += row(
                        "Repair Status",
                        "Done — Returned to Testing",
                        "#16a34a",
                    );
                repairSection = section(
                    "🔧",
                    "4. REPAIR MODULE",
                    "#fff8f0",
                    "#e65100",
                    rows,
                );
            }

            // 5. Re-Testing
            let retestSection = "";
            if (this.showReTestingModule) {
                let rows = "";
                if (this.reTestingWorkLogMeta.dateRetested)
                    rows += row(
                        "Date Re-tested",
                        this.reTestingWorkLogMeta.dateRetested,
                    );
                if (this.reTestingWorkLogMeta.tester)
                    rows += row("Tester", this.reTestingWorkLogMeta.tester);
                if (this.reTestingScope.length)
                    rows += row(
                        "Re-test Scope",
                        this.reTestingScope.join(", ") +
                            " (repaired items only)",
                    );
                this.reTestingEntries.forEach((e) => {
                    rows += row(
                        e.label,
                        (e.value || "—") + (pass(e.value) ? " ✓" : ""),
                        color(e.value),
                    );
                });
                if (this.reTestingWorkLogMeta.testResult)
                    rows += row(
                        "Re-test Result",
                        this.reTestingWorkLogMeta.testResult === "pass"
                            ? "PASS — All Repairs Successful"
                            : "FAIL — Further Repair Needed",
                        this.reTestingWorkLogMeta.testResult === "pass"
                            ? "#16a34a"
                            : "#dc2626",
                    );
                retestSection = section(
                    "🔁",
                    "5. RE-TESTING MODULE",
                    "#f0fff4",
                    "#16a34a",
                    rows,
                );
            }

            // Cleaning
            let cleaningSection = "";
            if (this.showCleaningModule) {
                let rows = "";
                if (this.cleaningWorkLogMeta.dateCleaned)
                    rows += row(
                        "Date Cleaned",
                        this.cleaningWorkLogMeta.dateCleaned,
                    );
                if (this.cleaningWorkLogMeta.cleanedBy)
                    rows += row(
                        "Cleaned By",
                        this.cleaningWorkLogMeta.cleanedBy,
                    );
                this.cleaningCategoryEntries.forEach((e) => {
                    rows += row(
                        e.name,
                        (e.status || "—") +
                            (e.notes
                                ? ` — <span style="color:#64748b;font-size:11px">${e.notes}</span>`
                                : ""),
                    );
                });
                cleaningSection = section(
                    "🧹",
                    `${this.cleaningModuleNumber}. CLEANING MODULE`,
                    "#f0f9ff",
                    "#0369a1",
                    rows,
                );
            }

            // Packaging
            let packagingSection = "";
            if (this.showPackagingModule) {
                let rows = "";
                if (this.packagingWorkLogMeta.datePacked)
                    rows += row(
                        "Date Packaged",
                        this.packagingWorkLogMeta.datePacked,
                    );
                if (this.packagingWorkLogMeta.packedBy)
                    rows += row(
                        "Packaged By",
                        this.packagingWorkLogMeta.packedBy,
                    );
                if (this.packagingIncludedComponents.length)
                    rows += row(
                        "Components Included",
                        this.packagingIncludedComponents.join(", "),
                    );
                if (this.packagingBoxSpecs.size)
                    rows += row("Box Size", this.packagingBoxSpecs.size);
                if (this.packagingBoxSpecs.type)
                    rows += row("Box Type", this.packagingBoxSpecs.type);
                if (this.packagingNotes)
                    rows += row("Notes", this.packagingNotes);
                packagingSection = section(
                    "📦",
                    `${this.packagingModuleNumber}. PACKAGING MODULE`,
                    "#fdf2f8",
                    "#e91e8c",
                    rows,
                );
            }

            const html = `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Workflow Log — ${this.log.serialnumber || ""}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; color: #1a1a2e; background: #fff; padding: 20px; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 12px; border-bottom: 2px solid #1a1a2e; margin-bottom: 12px; }
        .report-title { font-size: 18px; font-weight: 800; letter-spacing: 2px; }
        .report-sub   { font-size: 11px; color: #64748b; margin-top: 3px; }
        .serial-badge { background: #1a1a2e; color: #fff; padding: 8px 16px; border-radius: 6px; text-align: center; }
        .serial-label { font-size: 8px; text-transform: uppercase; letter-spacing: 1px; opacity: .65; }
        .serial-value { font-size: 14px; font-weight: 700; font-family: monospace; }
        .meta-strip   { display: flex; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; margin-bottom: 14px; }
        .meta-cell    { flex: 1; padding: 7px 10px; border-right: 1px solid #e2e8f0; }
        .meta-cell:last-child { border-right: none; }
        .meta-cell.wide { flex: 2; }
        .meta-lbl     { font-size: 8px; text-transform: uppercase; letter-spacing: .8px; color: #94a3b8; font-weight: 600; }
        .meta-val     { font-size: 11px; font-weight: 600; margin-top: 2px; }
        .section      { margin-bottom: 10px; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; page-break-inside: avoid; }
        .section-head { padding: 7px 12px; font-size: 11px; font-weight: 700; letter-spacing: .8px; }
        table         { width: 100%; border-collapse: collapse; }
        tr:nth-child(even) { background: #fafafa; }
        td            { padding: 4px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        td.lbl        { width: 200px; font-size: 10px; color: #64748b; font-weight: 500; white-space: nowrap; }
        td.val        { font-size: 11px; font-weight: 500; }
        .badge        { background: #e0f2fe; color: #0369a1; font-size: 8px; padding: 1px 5px; border-radius: 3px; }
        .print-footer { text-align: center; font-size: 9px; color: #94a3b8; margin-top: 16px; padding-top: 10px; border-top: 1px solid #e2e8f0; }
        @media print  { body { padding: 0; } @page { margin: 15mm; } }
    </style>
</head>
<body>
    <div class="page-header">
        <div>
            <div class="report-title">WORKFLOW LOG REPORT</div>
            <div class="report-sub">Complete Item Processing History</div>
        </div>
        <div class="serial-badge">
            <div class="serial-label">Serial Number</div>
            <div class="serial-value">${this.log.serialnumber || "—"}</div>
        </div>
    </div>
    <div class="meta-strip">
        <div class="meta-cell"><div class="meta-lbl">ASIN</div><div class="meta-val">${this.logAsin || "—"}</div></div>
        <div class="meta-cell"><div class="meta-lbl">FNSKU</div><div class="meta-val">${this.log.fnsku || this.log.FNSKU || "—"}</div></div>
        <div class="meta-cell wide"><div class="meta-lbl">Product</div><div class="meta-val">${this.log.product_name || this.log.ProductTitle || "—"}</div></div>
        <div class="meta-cell"><div class="meta-lbl">Date Received</div><div class="meta-val">${this.log.date_received || "—"}</div></div>
        <div class="meta-cell"><div class="meta-lbl">Date Labelled</div><div class="meta-val">${this.log.date_labelled || this.log.lastDateUpdate || "—"}</div></div>
        <div class="meta-cell"><div class="meta-lbl">RT#</div><div class="meta-val">${this.log.rtcounter || "—"}</div></div>
    </div>
    ${section("📦", "1. RECEIVED MODULE", "#eff6ff", "#1d4ed8", receivedRows)}
    ${labellingSection}
    ${testingSection}
    ${repairSection}
    ${retestSection}
    ${cleaningSection}
    ${packagingSection}
    <div class="print-footer">Printed ${new Date().toLocaleString()} · Workflow Log Report · ${this.log.serialnumber || ""}</div>
</body>
</html>`;

            const w = window.open("", "_blank", "width=900,height=700");
            w.document.write(html);
            w.document.close();
            w.focus();
            setTimeout(() => {
                w.print();
                w.close();
            }, 400);
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
