<template>
    <div class="log-wrapper">
        <!-- Header -->
        <div class="log-header">
            <i class="fas fa-clipboard-list"></i>
            <span>Item Log Module — Search & Print System</span>
        </div>

        <!-- Search & Filter -->
        <div class="search-card">
            <div class="section-label-row">
                <span class="section-badge badge-blue">SEARCH & FILTER</span>
                <span class="section-title">Find Items in Stockroom</span>
            </div>

            <div class="search-grid">
                <div class="field-wrap">
                    <label>Serial Number</label>
                    <InputText
                        v-model="filters.serial"
                        placeholder="Search by serial..."
                    />
                </div>
                <div class="field-wrap">
                    <label>ASIN</label>
                    <InputText
                        v-model="filters.asin"
                        placeholder="Search by ASIN..."
                    />
                </div>
                <div class="field-wrap">
                    <label>Start Date</label>
                    <Calendar
                        v-model="filters.from"
                        dateFormat="mm/dd/yy"
                        placeholder="mm/dd/yyyy"
                        :showIcon="true"
                    />
                </div>
                <div class="field-wrap">
                    <label>End Date</label>
                    <Calendar
                        v-model="filters.to"
                        dateFormat="mm/dd/yy"
                        placeholder="mm/dd/yyyy"
                        :showIcon="true"
                    />
                </div>
            </div>

            <div class="search-actions">
                <Button
                    label="Search"
                    icon="pi pi-search"
                    class="p-button-primary"
                    @click="fetchLogs"
                />
                <Button
                    label="Clear Filters"
                    icon="pi pi-times"
                    class="p-button-secondary"
                    @click="clearFilters"
                />
            </div>
        </div>

        <!-- Results -->
        <div class="results-card">
            <div class="results-header">
                <div class="results-label-row">
                    <span class="section-badge badge-green"
                        >SEARCH RESULTS</span
                    >
                    <span class="section-title">
                        Items in Stockroom
                        <span class="subtitle"
                            >(Clickable to View Full Logs)</span
                        >
                    </span>
                </div>
                <div class="print-actions">
                    <Button
                        label="Print Selected"
                        icon="pi pi-print"
                        class="p-button-success p-button-sm"
                        :disabled="!selectedRows.length"
                        @click="printSelected"
                    />
                    <Button
                        label="Print All"
                        icon="pi pi-print"
                        class="p-button-success p-button-sm"
                        @click="printAll"
                    />
                </div>
            </div>

            <DataTable
                v-model:selection="selectedRows"
                :value="logs"
                :loading="loading"
                :paginator="true"
                :rows="10"
                :rowsPerPageOptions="[10, 25, 50]"
                dataKey="checklist_id"
                selectionMode="multiple"
                :selectAll="selectAll"
                @select-all-change="onSelectAllChange"
                stripedRows
                responsiveLayout="scroll"
                class="log-table"
            >
                <template #empty>
                    <div class="empty-state">No items found.</div>
                </template>
                <template #loading>
                    <div class="empty-state">Loading records...</div>
                </template>

                <Column selectionMode="multiple" style="width: 3rem" />

                <Column field="serialnumber" header="Serial Number" sortable>
                    <template #body="{ data }">
                        <span class="serial-link" @click="viewFullLog(data)">
                            {{ data.serialnumber || "—" }}
                        </span>
                    </template>
                </Column>

                <Column field="asin" header="ASIN" sortable>
                    <template #body="{ data }">{{ data.asin || "—" }}</template>
                </Column>

                <Column field="product_name" header="Product Name" sortable>
                    <template #body="{ data }">{{
                        data.product_name || "—"
                    }}</template>
                </Column>

                <Column field="date_received" header="Date Stored" sortable>
                    <template #body="{ data }">{{
                        data.date_received
                    }}</template>
                </Column>

                <Column field="pass_fail_result" header="Status" sortable>
                    <template #body="{ data }">
                        <span
                            :class="[
                                'status-badge',
                                data.pass_fail_result === 'pass'
                                    ? 'status-pass'
                                    : 'status-fail',
                            ]"
                        >
                            {{
                                data.pass_fail_result === "pass"
                                    ? "✓ Pass"
                                    : "✗ Fail"
                            }}
                        </span>
                    </template>
                </Column>

                <Column header="Action">
                    <template #body="{ data }">
                        <Button
                            label="View Full Log"
                            icon="pi pi-eye"
                            class="p-button-sm view-btn"
                            @click="viewFullLog(data)"
                        />
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- View Full Log Dialog -->
        <Dialog
            v-model:visible="showLogDialog"
            :showHeader="false"
            :modal="true"
            :style="{ width: '720px', padding: '0' }"
            :breakpoints="{ '768px': '95vw' }"
            :pt="{ content: { style: 'padding: 0;' } }"
        >
            <div v-if="selectedLog" class="wl-page">
                <!-- Page number -->
                <div class="wl-page-number">Page 1 of 1</div>

                <!-- Top Header -->
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
                            {{ selectedLog.serialnumber || "—" }}
                        </div>
                    </div>
                </div>

                <!-- Meta Row 1: ASIN, FNSKU, Product -->
                <div class="wl-meta-grid">
                    <div class="wl-meta-item">
                        <div class="wl-meta-label">ASIN</div>
                        <div class="wl-meta-value">
                            {{ selectedLog.asin || "—" }}
                        </div>
                    </div>
                    <div class="wl-meta-item">
                        <div class="wl-meta-label">FNSKU</div>
                        <div class="wl-meta-value">
                            {{
                                selectedLog.fnsku ||
                                selectedLog.fnsku_changed ||
                                "—"
                            }}
                        </div>
                    </div>
                    <div class="wl-meta-item">
                        <div class="wl-meta-label">Product</div>
                        <div class="wl-meta-value">
                            {{ selectedLog.product_name || "—" }}
                        </div>
                    </div>
                </div>

                <!-- Meta Row 2: Date Received, Date Labelled, RT# -->
                <div class="wl-meta-grid wl-meta-grid--second">
                    <div class="wl-meta-item">
                        <div class="wl-meta-label">Date Received</div>
                        <div class="wl-meta-value">
                            {{ selectedLog.date_received || "—" }}
                        </div>
                    </div>
                    <div class="wl-meta-item">
                        <div class="wl-meta-label">Date Labelled</div>
                        <div class="wl-meta-value">
                            {{ selectedLog.date_labelled || "—" }}
                        </div>
                    </div>
                    <div class="wl-meta-item">
                        <div class="wl-meta-label">RT#</div>
                        <div class="wl-meta-value">
                            {{ selectedLog.rtcounter || "—" }}
                        </div>
                    </div>
                </div>

                <hr class="wl-divider" />

                <!-- 1. Received Module -->
                <div class="wl-section-header wl-section-header--received">
                    <span>📦</span>
                    <span>1. RECEIVED MODULE</span>
                </div>
                <div class="wl-section-body">
                    <div class="wl-field">
                        <span class="wl-field-label">Date Received:</span>
                        <span class="wl-field-value">{{
                            selectedLog.date_received || "—"
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label">Tracking Number:</span>
                        <span class="wl-field-value">{{
                            selectedLog.trackingnumber || "—"
                        }}</span>
                    </div>
                    <template v-if="parsedSerials(selectedLog).length">
                        <div
                            class="wl-field"
                            v-for="(sn, i) in parsedSerials(selectedLog)"
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
                        <span class="wl-field-value">{{
                            selectedLog.pass_fail_result === "pass"
                                ? "Working"
                                : "Not Working"
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label">Received By:</span>
                        <span class="wl-field-value">{{
                            selectedLog.received_by || "—"
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label"
                            >Item received correct on order:</span
                        >
                        <span class="wl-field-value">{{
                            selectedLog.correct_on_order === "yes"
                                ? "Yes ✓"
                                : "No ✗"
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label"
                            >Condition on Arrival:</span
                        >
                        <span
                            class="wl-field-value"
                            style="text-transform: capitalize"
                        >
                            {{ selectedLog.condition_on_arrival || "—"
                            }}{{
                                selectedLog.condition_on_arrival === "good"
                                    ? " ✓"
                                    : ""
                            }}
                        </span>
                    </div>
                    <div class="wl-field" v-if="selectedLog.condition_notes">
                        <span class="wl-field-label">Condition Notes:</span>
                        <span class="wl-field-value">{{
                            selectedLog.condition_notes
                        }}</span>
                    </div>
                </div>

                <!-- 2. Labelling Module — only shown if item passed through Labeling -->
                <div
                    v-if="selectedLog.passed_labeling"
                    class="wl-section-header wl-section-header--labelling"
                >
                    <span>🏷️</span>
                    <span>2. LABELLING MODULE</span>
                </div>
                <div v-if="selectedLog.passed_labeling" class="wl-section-body">
                    <div>
                        <span class="wl-field-value">{{
                            selectedLog.labelled_by || "—"
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label">FNSKU:</span>
                        <span class="wl-field-value">{{
                            selectedLog.fnsku ||
                            selectedLog.fnsku_changed ||
                            "—"
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label">ASIN:</span>
                        <span class="wl-field-value">{{
                            selectedLog.asin || "—"
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label">RPN:</span>
                        <span class="wl-field-value">{{
                            selectedLog.rpn || "—"
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label">PRD:</span>
                        <span class="wl-field-value">{{
                            selectedLog.prd || "—"
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label">Grading:</span>
                        <span class="wl-field-value">{{
                            selectedLog.grading || "—"
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label">Priority Rank:</span>
                        <span class="wl-field-value">{{
                            selectedLog.priority_rank || "—"
                        }}</span>
                    </div>
                    <div class="wl-field" v-if="selectedLog.sticker_note">
                        <span class="wl-field-label">Sticker Notes:</span>
                        <span class="wl-field-value">{{
                            selectedLog.sticker_note
                        }}</span>
                    </div>
                    <div class="wl-field" v-if="selectedLog.employee_note">
                        <span class="wl-field-label">Employee Notes:</span>
                        <span class="wl-field-value">{{
                            selectedLog.employee_note
                        }}</span>
                    </div>
                    <div class="wl-field">
                        <span class="wl-field-label">Current Location:</span>
                        <span class="wl-field-value">{{
                            selectedLog.current_location || "—"
                        }}</span>
                    </div>
                </div>

                <!-- Footer actions -->
                <div class="wl-footer-actions">
                    <Button
                        label="Print"
                        icon="pi pi-print"
                        class="p-button-success"
                        @click="printLog(selectedLog)"
                    />
                    <Button
                        label="Close"
                        icon="pi pi-times"
                        class="p-button-secondary"
                        @click="showLogDialog = false"
                    />
                </div>
            </div>
        </Dialog>
    </div>
</template>

<script>
import ItemLogs from "./timelog.js";
export default ItemLogs;
</script>

<style scoped>
@import "./timelog.css";
</style>
