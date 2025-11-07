<template>
    <DataTable :value="value" v-model:selection="internalSelection" :loading="loading" :paginator="paginator"
        :rows="rows" :rowsPerPageOptions="rowsPerPageOptions" class="desktop-view" size="small" :style="tableStyle"
        dataKey="id" :selectionMode="selectionMode">

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
    },
    emits: ["update:selection"],
    data() {
        return { internalSelection: this.selection };
    },
    computed: {

        visibleColumns() {
            console.log(this.columns, "columnscolumnscolumns")
            return this.columns.filter((col) => col.visible !== false);
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
