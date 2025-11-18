<template>
    <DataTable :value="displayValue" v-model:selection="internalSelection" :paginator="paginator"
        :showGridlines="showGridlines" :rows="rows" :rowsPerPageOptions="rowsPerPageOptions" :class="tableClass"
        size="small" :style="tableStyle" dataKey="id" :selectionMode="selectionMode">
        <!-- ✅ Custom Loading + Empty Row -->
        <template #empty>
            <div class="p-datatable-empty-message" style="width: 100%; text-align: center; padding: 2rem 0;">
                <span v-if="loading">
                    <i class="pi pi-spin pi-spinner mr-2"></i>
                    Loading data...
                </span>
                <span v-else>
                    No data found
                </span>
            </div>
        </template>


        <Column v-for="col in visibleColumns" :key="col.field || col.header || col.selectionMode" v-bind="col">
            <template v-if="!col.selectionMode && col.slot && $slots[col.slot]" #body="{ data }">
                <slot :name="col.slot" :data="data" />
            </template>

            <template v-else-if="!col.selectionMode && !col.slot" #body="{ data }">
                {{ data[col.field] }}
            </template>
        </Column>

        <Column v-if="$slots.actions" header="Actions" :style="{ minWidth: '10rem' }">
            <template #body="{ data }">
                <slot name="actions" :data="data" />
            </template>
        </Column>
    </DataTable>
</template>

<script>
import { DataTable, Column } from "primevue";

export default {
    name: "ReusableDataTable",
    components: { DataTable, Column },
    props: {
        value: { type: Array, required: true },
        columns: { type: Array, required: true },
        selection: { type: [Array, Object], default: null },
        selectionMode: { type: String, default: null },
        loading: { type: Boolean, default: false },
        paginator: { type: Boolean, default: false },
        rows: { type: Number, default: 10 },
        rowsPerPageOptions: { type: Array, default: () => [10, 20, 50] },
        tableStyle: { type: [String, Object], default: () => ({}) },
        showGridlines: { type: Boolean, default: false },
        tableClass: { type: String, default: "" }
    },
    emits: ["update:selection"],
    data() {
        return {
            internalSelection: this.selection,
        };
    },
    computed: {
        visibleColumns() {
            return this.columns.filter((col) => col.visible !== false);
        },
        displayValue() {
            // 👇 Important: When loading, return an empty array so #empty slot shows up
            return this.loading ? [] : this.value;
        },
    },
    watch: {
        internalSelection(newVal) {
            this.$emit("update:selection", newVal);
        },
        selection(newVal) {
            this.internalSelection = newVal;
        },
    },
};
</script>

<style scoped>
.p-datatable-empty-message {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
}
</style>
