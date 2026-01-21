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

    <!-- Actions Row -->
    <div class="d-flex flex-wrap align-items-center gap-4 mb-2">
      <div class="d-flex align-items-center gap-2">
        <strong>Shipping Labels:</strong>
        <label class="d-flex align-items-center gap-2">
          <input type="radio" v-model="labelAction" value="PrintShipmentLabel" :disabled="isProcessing" />
          <span>Print</span>
        </label>
        <label class="d-flex align-items-center gap-2">
          <input type="radio" v-model="labelAction" value="ViewShipmentLabel" :disabled="isProcessing" />
          <span>View</span>
        </label>
      </div>

      <div class="d-flex align-items-center gap-2">
        <strong>Invoices:</strong>
        <label class="d-flex align-items-center gap-2">
          <input type="radio" v-model="invoiceAction" value="PrintInvoice" :disabled="isProcessing" />
          <span>Print</span>
        </label>
        <label class="d-flex align-items-center gap-2">
          <input type="radio" v-model="invoiceAction" value="ViewInvoice" :disabled="isProcessing" />
          <span>View</span>
        </label>
      </div>
    </div>

    <!-- Toggleable Invoice Settings (default hidden) -->
    <div class="mb-3">
      <Button
        size="small"
        severity="secondary"
        outlined
        :label="showInvoiceSettings ? 'Hide Order Invoice Settings' : 'Show Order Invoice Settings'"
        icon="pi pi-cog"
        @click="showInvoiceSettings = !showInvoiceSettings"
        :disabled="isProcessing"
      />

      <div v-if="showInvoiceSettings" class="mt-2 p-2 border rounded">
        <div class="d-flex flex-wrap gap-4">
          <label class="d-flex align-items-center gap-2">
            <input type="checkbox" v-model="invoiceSettings.displayPrice" :disabled="isProcessing" />
            <span>Show Prices</span>
          </label>
          <label class="d-flex align-items-center gap-2">
            <input type="checkbox" v-model="invoiceSettings.signatureRequired" :disabled="isProcessing" />
            <span>Require Signature</span>
          </label>
          <label class="d-flex align-items-center gap-2">
            <input type="checkbox" v-model="invoiceSettings.testPrint" :disabled="isProcessing" />
            <span>Test Print</span>
          </label>
        </div>
      </div>
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
          <div class="d-flex align-items-center gap-2">
            <Checkbox v-model="selections[data.orderId].label" :binary="true" :disabled="isProcessing" />
            <small v-if="getStatus(data.orderId, 'label')" :class="statusClass(getStatus(data.orderId, 'label'))">
              <a
                v-if="isClickableStatus(data.orderId, 'label')"
                href="#"
                @click.prevent="openOrderDocs(data.orderId)"
              >
                {{ getStatus(data.orderId, 'label') }}
              </a>
              <span v-else>
                {{ getStatus(data.orderId, 'label') }}
              </span>
            </small>
          </div>
        </template>
      </Column>

      <Column header="Order Invoice" style="width: 160px">
        <template #body="{ data }">
          <div class="d-flex align-items-center gap-2">
            <Checkbox v-model="selections[data.orderId].invoice" :binary="true" :disabled="isProcessing" />
            <small v-if="getStatus(data.orderId, 'invoice')" :class="statusClass(getStatus(data.orderId, 'invoice'))">
              <a
                v-if="isClickableStatus(data.orderId, 'invoice')"
                href="#"
                @click.prevent="openOrderDocs(data.orderId)"
              >
                {{ getStatus(data.orderId, 'invoice') }}
              </a>
              <span v-else>
                {{ getStatus(data.orderId, 'invoice') }}
              </span>
            </small>
          </div>
        </template>
      </Column>
    </DataTable>

    <!-- Footer -->
    <template #footer>
      <div class="d-flex justify-content-end gap-2">
        <Button severity="secondary" outlined label="Cancel" @click="onClose(false)" :disabled="isProcessing" />
        <Button
          severity="success"
          :label="isProcessing ? 'Processing…' : 'Run Selected'"
          icon="pi pi-play"
          @click="printSelected"
          :disabled="isProcessing"
        />
      </div>
    </template>
  </Dialog>
</template>

<script>
import Swal from "sweetalert2";

import Dialog from "primevue/dialog";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Checkbox from "primevue/checkbox";
import Button from "primevue/button";

export default {
  name: "PrintDocumentsModal",
  components: { Dialog, DataTable, Column, Checkbox, Button },

  props: {
    visible: { type: Boolean, default: false },
    orderIds: { type: Array, default: () => [] },
    defaultLabelChecked: { type: Boolean, default: true },
    defaultInvoiceChecked: { type: Boolean, default: false },
  },

  // parent will handle API calls, modal provides callback
  emits: ["update:visible", "print"],

  data() {
    return {
      selections: {},

      // actions
      labelAction: "ViewShipmentLabel",
      invoiceAction: "ViewInvoice",

      // invoice settings (toggleable UI)
      showInvoiceSettings: false,
      invoiceSettings: {
        displayPrice: false,
        signatureRequired: false,
        testPrint: false,
      },

      // processing + per-order status
      isProcessing: false,

      // shape:
      // orderMeta[orderId] = {
      //   label: { status: "Ready to view"|"Printed"|"Failed", pdfUrl: "" },
      //   invoice:{ status: "...", pdfUrl: "" }
      // }
      orderMeta: {},

      // to auto-clear statuses (5s)
      statusTimers: {},
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
      if (!newVal) this.resetTransientState();
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

      // reset meta for current orders (keeps it clean)
      const meta = {};
      (this.orderIds || []).forEach((id) => {
        meta[id] = {
          label: { status: "", pdfUrl: "" },
          invoice: { status: "", pdfUrl: "" },
        };
      });
      this.orderMeta = meta;
    },

    resetTransientState() {
      this.isProcessing = false;
      this.clearAllTimers();
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

    getStatus(orderId, kind) {
      return this.orderMeta?.[orderId]?.[kind]?.status || "";
    },

    statusClass(status) {
      if (!status) return "";
      const s = String(status).toLowerCase();
      if (s.includes("failed")) return "text-danger";
      if (s.includes("ready")) return "text-primary";
      if (s.includes("printed") || s.includes("sent")) return "text-success";
      return "text-muted";
    },

    isClickableStatus(orderId, kind) {
      const url = this.orderMeta?.[orderId]?.[kind]?.pdfUrl;
      const status = this.getStatus(orderId, kind);
      return !!url && String(status).toLowerCase().includes("ready");
    },

    openOrderDocs(orderId) {
      // open whatever exists for this order (label + invoice)
      const labelUrl = this.orderMeta?.[orderId]?.label?.pdfUrl;
      const invoiceUrl = this.orderMeta?.[orderId]?.invoice?.pdfUrl;

      if (labelUrl) window.open(labelUrl, "_blank");
      if (invoiceUrl) window.open(invoiceUrl, "_blank");
    },

    clearAllTimers() {
      Object.values(this.statusTimers || {}).forEach((t) => clearTimeout(t));
      this.statusTimers = {};
    },

    // Vue 3: no this.$set — use object replacement (reactive-safe)
    setOrderMeta(orderId, kind, patch) {
      const current = this.orderMeta?.[orderId]?.[kind] || { status: "", pdfUrl: "" };

      this.orderMeta = {
        ...this.orderMeta,
        [orderId]: {
          ...(this.orderMeta?.[orderId] || {}),
          [kind]: {
            ...current,
            ...patch,
          },
        },
      };
    },

    setTimedStatus(orderId, kind, status, pdfUrl = "") {
      // set now
      this.setOrderMeta(orderId, kind, { status, pdfUrl });

      // clear timer for this order+kind
      const key = `${orderId}:${kind}`;
      if (this.statusTimers[key]) clearTimeout(this.statusTimers[key]);

      // auto-clear after 5 seconds (status + url)
      /*this.statusTimers[key] = setTimeout(() => {
        this.setOrderMeta(orderId, kind, { status: "", pdfUrl: "" });
        const nextTimers = { ...this.statusTimers };
        delete nextTimers[key];
        this.statusTimers = nextTimers;
      }, 5000);*/
    },

    printSelected() {
      const labelOrders = [];
      const invoiceOrders = [];

      Object.entries(this.selections).forEach(([orderId, sel]) => {
        if (sel.label) labelOrders.push(orderId);
        if (sel.invoice) invoiceOrders.push(orderId);
      });

      if (!labelOrders.length && !invoiceOrders.length) {
        Swal.fire("Nothing selected", "Please select at least one document to run.", "warning");
        return;
      }

      // ✅ do NOT close modal
      this.isProcessing = true;

      // set "Processing…" statuses immediately
      labelOrders.forEach((oid) => this.setOrderMeta(oid, "label", { status: "Processing…", pdfUrl: "" }));
      invoiceOrders.forEach((oid) => this.setOrderMeta(oid, "invoice", { status: "Processing…", pdfUrl: "" }));

      const payload = {
        labelOrders,
        invoiceOrders,
        labelAction: this.labelAction,
        invoiceAction: this.invoiceAction,
        invoiceSettings: this.invoiceSettings,
      };

      // ✅ callback parent will call when done
      const done = (result) => {
        // result shape expected:
        // {
        //   label: { [orderId]: { ok: true/false, status: "Ready to view"/"Printed"/"Failed", pdfUrl?: "" } },
        //   invoice: { [orderId]: { ok: true/false, status: "...", pdfUrl?: "" } }
        // }
        const labelMap = result?.label || {};
        const invoiceMap = result?.invoice || {};

        Object.entries(labelMap).forEach(([oid, r]) => {
          this.setTimedStatus(oid, "label", r?.status || (r?.ok ? "Done" : "Failed"), r?.pdfUrl || "");
        });

        Object.entries(invoiceMap).forEach(([oid, r]) => {
          this.setTimedStatus(oid, "invoice", r?.status || (r?.ok ? "Done" : "Failed"), r?.pdfUrl || "");
        });

        this.isProcessing = false;
      };

      this.$emit("print", payload, done);
    },
  },
};
</script>

<style scoped>
/* optional tiny polish */
a {
  text-decoration: underline;
}
</style>
