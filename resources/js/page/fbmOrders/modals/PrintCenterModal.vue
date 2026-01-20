<template>
  <Dialog
    :visible="visible"
    modal
    header="Print Documents"
    :style="{ width: '900px' }"
    @update:visible="onClose"
  >
    <!-- Top Controls -->
    <div class="d-flex flex-wrap gap-2 mb-3">
      <Button size="small" severity="secondary" outlined label="Select All" @click="selectAll(true)" />
      <Button size="small" severity="secondary" outlined label="Deselect All" @click="selectAll(false)" />

      <span class="mx-2"></span>

      <Button size="small" severity="info" outlined label="All Labels" @click="toggleColumn('label', true)" />
      <Button size="small" severity="secondary" outlined label="No Labels" @click="toggleColumn('label', false)" />

      <Button size="small" severity="info" outlined label="All Invoices" @click="toggleColumn('invoice', true)" />
      <Button size="small" severity="secondary" outlined label="No Invoices" @click="toggleColumn('invoice', false)" />
    </div>

    <!-- Table -->
    <DataTable :value="rows" responsiveLayout="scroll" class="p-datatable-sm">
      <Column header="Order ID">
        <template #body="{ data }">
          <code>{{ data.orderId }}</code>
        </template>
      </Column>

      <Column header="Shipping Label" style="width: 160px">
        <template #body="{ data }">
          <Checkbox v-model="selections[data.orderId].label" :binary="true" />
        </template>
      </Column>

      <Column header="Order Invoice" style="width: 160px">
        <template #body="{ data }">
          <Checkbox v-model="selections[data.orderId].invoice" :binary="true" />
        </template>
      </Column>
    </DataTable>

    <!-- Footer -->
    <template #footer>
      <div class="d-flex justify-content-end gap-2">
        <Button severity="secondary" outlined label="Cancel" @click="onClose(false)" />
        <Button severity="success" label="Print Selected" icon="pi pi-print" @click="printSelected" />
      </div>
    </template>
  </Dialog>
</template>

<script>
import Swal from "sweetalert2";

// ✅ PrimeVue components (required inside this file)
import Dialog from "primevue/dialog";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Checkbox from "primevue/checkbox";
import Button from "primevue/button";

export default {
  name: "PrintDocumentsModal",

  // ✅ register them so Vue can resolve <Dialog>, <DataTable>, etc.
  components: {
    Dialog,
    DataTable,
    Column,
    Checkbox,
    Button,
  },

  props: {
    visible: { type: Boolean, default: false },
    orderIds: { type: Array, default: () => [] },
    defaultLabelChecked: { type: Boolean, default: true },
    defaultInvoiceChecked: { type: Boolean, default: false },
  },
  emits: ["update:visible", "print"],
  data() {
    return {
      selections: {},
    };
  },
  computed: {
    rows() {
      return (this.orderIds || []).map((id) => ({ orderId: id }));
    },
  },
  watch: {
    visible(newVal) {
      if (newVal) this.initSelections();
    },
    orderIds: {
      handler() {
        if (this.visible) this.initSelections();
      },
      deep: true,
    },
  },
  methods: {
    initSelections() {
      const next = {};
      (this.orderIds || []).forEach((id) => {
        next[id] = {
          label: this.defaultLabelChecked,
          invoice: this.defaultInvoiceChecked,
        };
      });
      this.selections = next;
    },

    onClose() {
      this.$emit("update:visible", false);
    },

    selectAll(flag) {
      const next = { ...this.selections };
      Object.keys(next).forEach((id) => {
        next[id].label = flag;
        next[id].invoice = flag;
      });
      this.selections = next;
    },

    toggleColumn(col, flag) {
      const next = { ...this.selections };
      Object.keys(next).forEach((id) => {
        next[id][col] = flag;
      });
      this.selections = next;
    },

    printSelected() {
      const labelOrders = [];
      const invoiceOrders = [];

      Object.entries(this.selections).forEach(([orderId, sel]) => {
        if (sel.label) labelOrders.push(orderId);
        if (sel.invoice) invoiceOrders.push(orderId);
      });

      if (!labelOrders.length && !invoiceOrders.length) {
        Swal.fire(
          "Nothing selected",
          "Please select at least one document to print.",
          "warning"
        );
        return;
      }

      this.$emit("print", { labelOrders, invoiceOrders });
      this.$emit("update:visible", false);
    },
  },
};
</script>

