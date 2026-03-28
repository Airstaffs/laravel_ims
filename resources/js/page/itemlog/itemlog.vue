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
                        <span class="serial-link" @click="viewFullLog(data)">{{
                            data.serialnumber || "—"
                        }}</span>
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
        <FullLog v-model="showLogDialog" :log="selectedLog" @print="printLog" />
    </div>
</template>

<script>
import ItemLogs from "./itemlog.js";
import FullLog from "./modal/fullLog.vue";
export default {
    ...ItemLogs,
    components: {
        ...ItemLogs.components,
        FullLog,
    },
};
</script>

<style scoped>
@import "./itemlog.css";
</style>
