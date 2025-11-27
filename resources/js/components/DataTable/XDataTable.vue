<template>
    <DataTable :value="displayValue" :paginator="paginator" :rows="rows" :rowsPerPageOptions="rowsPerPageOptions"
        :showGridlines="showGridlines" :class="tableClass" :style="tableStyle" size="small" :dataKey="autoDataKey"
        :scrollable="hasFixedColumns">
        <!-- Empty -->
        <template #empty>
            <div class="p-datatable-empty-message">
                <span v-if="loading">
                    <i class="pi pi-spin pi-spinner mr-2">
                    </i>
                    Loading data...
                </span>
                <span v-else>
                    No data found
                </span>
            </div>
        </template>
        <!-- Index Column -->
        <Column v-if="showIndex" header="#" :style="{ width: '4rem' }" :frozen="indexFrozen">
            <template #body="{ index }">
                {{ index + 1 }}
            </template>
        </Column>
        <!-- Checkbox Column -->
        <Column v-if="selectionMode" headerStyle="width: 3rem">
            <!-- Header checkbox for multiple select -->
            <template #header>
                <div v-if="selectionMode === 'multiple' && displayValue.length" class="select-all-wrapper">
                    <input type="checkbox" :checked="allSelectableSelected"
                        :indeterminate.prop="someSelectableSelected && !allSelectableSelected"
                        :disabled="allCheckboxesDisabled" @change="toggleSelectAll($event.target.checked)"
                        aria-label="Select all rows" />
                </div>
            </template>
            <!-- Body checkbox per row -->
            <template #body="{ data }">
                <div class="row-checkbox-wrapper">
                    <input type="checkbox" :checked="isSelected(data)" :disabled="isDisabled(data) || loading"
                        @change="onCheckboxChange($event.target.checked, data)"
                        :aria-label="`Select row ${String(data[autoDataKey] ?? '')}`" />

                </div>
            </template>
        </Column>
        <!-- Dynamic Columns -->
        <Column v-for="col in visibleColumns" :key="col.field || col.header" v-bind="col" :frozen="col.frozen"
            :alignFrozen="col.alignFrozen || 'left'">
            <template v-if="col.headerSlot && $slots[col.headerSlot]" #header>
                <slot :name="col.headerSlot" />
            </template>
            <template v-if="col.slot && $slots[col.slot]" #body="{ data }">
                <slot :name="col.slot" :data="data" />
            </template>
            <template v-else #body="{ data }">
                {{ data[col.field] }}
            </template>
        </Column>
        <!-- Actions Column -->
        <Column v-if="$slots.actions" header="Actions" :style="{ minWidth: '10rem' }" :frozen="actionsFrozen"
            :alignFrozen="actionsFrozen ? 'right' : undefined">
            <template #body="{ data }">
                <slot name="actions" :data="data" />
            </template>
        </Column>
    </DataTable>
</template>
<script>
import {
    DataTable,
    Column
}
    from "primevue";
export default {
    name:
        "ReusableDataTable",
    components: {
        DataTable,
        Column
    },
    props: {
        value: {
            type: Array,
            required: true
        },
        columns: {
            type: Array,
            required: true
        },
        selectionMode: {
            type: String,
            default:
                null
        },
        // "single" | "multiple" 
        selection: {
            type: [Array, Object],
            default:
                null
        },
        disableRowCheckbox: {
            type: Function,
            default:
                null
        },
        // row => true = disabled 
        onSelectionChange: {
            type: Function,
            default:
                null
        },
        onAllSelectionChange: {
            type: Function,
            default:
                null
        },
        loading: {
            type: Boolean,
            default:
                false
        },
        paginator: {
            type: Boolean,
            default:
                false
        },
        rows: {
            type: Number,
            default:
                10
        },
        rowsPerPageOptions: {
            type: Array,
            default:
                () => [10, 20, 50]
        },
        tableStyle: {
            type: [String, Object],
            default:
                () => ({})
        },
        tableClass: {
            type: String,
            default:
                ""
        },
        showIndex: {
            type: Boolean,
            default:
                false
        },
        indexFrozen: {
            type: Boolean,
            default:
                false
        },
        actionsFrozen: {
            type: Boolean,
            default:
                false
        },
        dataKey: {
            type: String,
            default:
                "id"
        },
    },
    emits: ["update:selection"],
    data() {
        return {
            internalSelection: this.normalizeSelection(this.selection),
        };
    },
    computed: {
        visibleColumns() {
            return this.columns.filter(c => c.visible !== false);
        },
        displayValue() {
            return this.loading ? [] : this.value || [];
        },
        autoDataKey() {
            return this.dataKey || "id";
        },
        hasFixedColumns() {
            return this.indexFrozen || this.actionsFrozen || this.columns.some(c => c.frozen);
        },
        // Select-all states 
        allCheckboxesDisabled() {
            return this.displayValue.filter(r => !this.isDisabled(r)).length === 0;
        },
        allSelectableSelected() {
            const selectable = this.displayValue.filter(r => !this.isDisabled(r));
            if (!selectable.length) return false;
            return selectable.every(r => this.isSelected(r));
        },
        someSelectableSelected() {
            const selectable = this.displayValue.filter(r => !this.isDisabled(r));
            return selectable.some(r => this.isSelected(r)) && !this.allSelectableSelected;
        },
    },
    watch: {
        selection(newVal) {
            this.internalSelection = this.normalizeSelection(newVal);
        },
        internalSelection(newVal) {
            this.$emit("update:selection", newVal);
        }
    },
    methods: {
        normalizeSelection(sel) {
            if (this.selectionMode === "multiple") return Array.isArray(sel) ? [...sel] : [];
            return sel || null;
        },
        rowKey(row) {
            return row?.[this.autoDataKey];
        },
        isDisabled(row) {
            return typeof this.disableRowCheckbox === "function" && this.disableRowCheckbox(row);
        },
        isSelected(row) {
            const key = this.rowKey(row);
            if (this.selectionMode === "multiple") {
                return this.internalSelection.some(r => this.rowKey(r) === key);
            } else {
                return !!this.internalSelection && this.rowKey(this.internalSelection) === key;
            }
        },
        onCheckboxChange(checked, row) {
            if (this.selectionMode === "multiple") {
                if (checked) this.selectRow(row);
                else this.unselectRow(row);
                console.log("Final selected rows:", this.internalSelection);
            } else {
                this.internalSelection = checked ? row : null;
                console.log(checked ? "Row selected:" : "Row unselected:", row);
                console.log("Final selected row:", this.internalSelection);
                if (this.onSelectionChange) this.onSelectionChange(row, checked);
            }
        },
        selectRow(row) {
            if (this.isDisabled(row)) return;
            const key = this.rowKey(row);
            if (!this.internalSelection.some(r => this.rowKey(r) === key)) {
                this.internalSelection = [...this.internalSelection, row];
                console.log("Row selected:", row);
                if (this.onSelectionChange) this.onSelectionChange(row, true);
            }
        },
        unselectRow(row) {
            const key = this.rowKey(row);
            this.internalSelection = this.internalSelection.filter(r => this.rowKey(r) !== key);
            console.log("Row unselected:", row);
            if (this.onSelectionChange) this.onSelectionChange(row, false);
        },
        toggleSelectAll(checked) {
            if (this.selectionMode !== "multiple") return;
            if (checked) {
                const selectableRows = this.displayValue.filter(r => !this.isDisabled(r));
                this.internalSelection = [...selectableRows];
                console.log("All rows selected:", selectableRows);
            } else {
                this.internalSelection = [];
                console.log("All rows unselected");
            }
            console.log("Final selected rows:", this.internalSelection);
            if (this.onAllSelectionChange) {
                this.onAllSelectionChange(this.internalSelection, checked);
            }
        },
    },
}
</script>
<style scoped>
.p-datatable-empty-message {
    display: flex;
    justify-content: center;
    align-items:
        center;
    width: 100%;
    padding: 2rem 0;
}

.row-checkbox-wrapper,
.select-all-wrapper {
    display: flex;
    align-items: center;
    /* justify-content: center; */
}

:deep(.p-datatable-thead > tr > th) {
    vertical-align: top !important;
}

.row-checkbox-wrapper input[type="checkbox"],
.select-all-wrapper input[type="checkbox"] {
    width: 1rem;
    height: 1rem;
}
</style>