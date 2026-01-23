<template>
    <Dialog header="Shipment Label History" :visible="visible" :modal="true" :style="dialogStyle"
        :contentStyle="dialogContentStyle" @update:visible="onClose">
        <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
            <span class="p-input-icon-left flex-grow-1" style="min-width: 220px">
                <i class="pi pi-search" />
                <InputText v-model="filters.keyword" placeholder="Search AmazonOrderId / Tracking / User"
                    @keyup.enter="fetchHistory(1)" class="w-100" />
            </span>

            <Button severity="secondary" outlined icon="pi pi-refresh" label="Refresh" @click="fetchHistory(1)"
                :disabled="loading" />

            <Button severity="secondary" outlined
                :icon="sortDirection === 'asc' ? 'pi pi-sort-amount-up' : 'pi pi-sort-amount-down'"
                :label="sortDirection === 'asc' ? 'ASC' : 'DESC'" @click="toggleSort" :disabled="loading" />

            <div class="ms-auto d-flex align-items-center gap-2">
                <small class="text-muted" v-if="meta.total">
                    Total: {{ meta.total }} • Page {{ meta.current_page }} / {{ meta.last_page }}
                </small>
            </div>
        </div>

        <DataTable :value="rows" :loading="loading" dataKey="id" v-model:expandedRows="expandedRows"
            class="p-datatable-sm">
            <Column expander style="width: 3rem" />

            <!-- ✅ Always visible -->
            <Column field="AmazonOrderId" header="Amazon Order Id" :sortable="true">
                <template #body="{ data }">
                    <span class="wrap-anywhere">{{ data.AmazonOrderId }}</span>
                </template>
            </Column>
            <Column field="status" header="Status" :sortable="true">
                <template #body="{ data }">
                    <span :class="data.status === 'Cancelled' ? 'text-danger fw-bold' : 'fw-semibold'">
                        {{ data.status }}
                    </span>
                </template>
            </Column>

            <!-- ✅ Desktop-only columns -->
            <Column v-if="!isMobile" field="createdDate" header="Created" :sortable="true">
                <template #body="{ data }">
                    {{ formatDateTime(data.createdDate) }}
                </template>
            </Column>

            <Column v-if="!isMobile" field="trackingid" header="Tracking" />

            <Column v-if="!isMobile" field="ShippingServiceId" header="Service" />

            <Column v-if="!isMobile" field="labelprice" header="Price" style="width: 110px">
                <template #body="{ data }">
                    {{ formatMoney(data.labelprice) }}
                </template>
            </Column>

            <Column v-if="!isMobile" field="user" header="User" style="width: 140px" />

            <Column v-if="!isMobile" field="ShipDate" header="Ship Date" style="width: 160px">
                <template #body="{ data }">
                    {{ formatDateTime(data.ShipDate) }}
                </template>
            </Column>

            <!-- ✅ Mobile-only: compact stacked info instead of many columns -->
            <Column v-if="isMobile" header="Details">
                <template #body="{ data }">
                    <div class="mobile-details">
                        <div><strong>Tracking:</strong> {{ data.trackingid || "-" }}</div>
                        <div><strong>Service:</strong> {{ data.ShippingServiceId || "-" }}</div>
                        <div><strong>Price:</strong> {{ formatMoney(data.labelprice) }}</div>
                        <div><strong>User:</strong> {{ data.user || "-" }}</div>
                        <div><strong>Ship:</strong> {{ formatDateTime(data.ShipDate) || "-" }}</div>
                        <div><strong>Created:</strong> {{ formatDateTime(data.createdDate) || "-" }}</div>
                    </div>
                </template>
            </Column>

            <Column header="Actions" :style="isMobile ? 'width: 160px' : 'width: 220px'">
                <template #body="{ data }">
                    <Button size="small" severity="danger" outlined
                        :label="isMobile ? 'Cancel Amazon Shipment Label' : 'Cancel Amazon Shipment Label'"
                        :disabled="loading || data.status === 'Cancelled'" @click="cancelLabel(data)" />
                </template>
            </Column>

            <!-- Expanded: Items table -->
            <template #expansion="{ data }">
                <div class="p-2">
                    <h6 class="mb-2">Label Items</h6>

                    <DataTable :value="data.items || []" class="p-datatable-sm">
                        <Column field="orderitemid" header="OrderItemId" />
                        <Column field="trackingid" header="Tracking" />
                        <Column field="shipDate" header="Ship Date">
                            <template #body="{ data: item }">
                                {{ formatDateTime(item.shipDate) }}
                            </template>
                        </Column>

                        <Column v-if="!isMobile" field="EarliestEstimatedDeliveryDate" header="ETA (Earliest)">
                            <template #body="{ data: item }">
                                {{ formatDateTime(item.EarliestEstimatedDeliveryDate) }}
                            </template>
                        </Column>

                        <Column v-if="!isMobile" field="LatestEstimatedDeliveryDate" header="ETA (Latest)">
                            <template #body="{ data: item }">
                                {{ formatDateTime(item.LatestEstimatedDeliveryDate) }}
                            </template>
                        </Column>

                        <Column v-if="isMobile" header="ETA">
                            <template #body="{ data: item }">
                                <div class="mobile-details">
                                    <div><strong>Earliest:</strong> {{
                                        formatDateTime(item.EarliestEstimatedDeliveryDate) || "-" }}</div>
                                    <div><strong>Latest:</strong> {{ formatDateTime(item.LatestEstimatedDeliveryDate) ||
                                        "-" }}</div>
                                </div>
                            </template>
                        </Column>

                        <Column field="DeliveryExperience" header="Delivery Exp" />

                        <Column field="labelprice" header="Price" style="width: 110px">
                            <template #body="{ data: item }">
                                {{ formatMoney(item.labelprice) }}
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </template>
        </DataTable>

        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
            <Button severity="secondary" outlined label="Close" icon="pi pi-times" @click="onClose" />

            <div class="d-flex gap-2">
                <Button severity="secondary" outlined icon="pi pi-angle-left" label="Prev"
                    @click="fetchHistory(meta.current_page - 1)" :disabled="loading || meta.current_page <= 1" />
                <Button severity="secondary" outlined label="Next" icon="pi pi-angle-right" iconPos="right"
                    @click="fetchHistory(meta.current_page + 1)"
                    :disabled="loading || meta.current_page >= meta.last_page" />
            </div>
        </div>
    </Dialog>
</template>

<script>
import axios from "axios";

import Dialog from "primevue/dialog";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import Select from "primevue/select";

import Swal from "sweetalert2";

export default {
    name: "ShipmentLabelHistory",
    components: { Dialog, DataTable, Column, Button, InputText, Select },
    props: {
        visible: { type: Boolean, default: false },
    },
    data() {
        return {
            loading: false,
            rows: [],
            expandedRows: [],
            sortDirection: "asc",
            filters: { keyword: "" },
            meta: { current_page: 1, last_page: 1, total: 0, per_page: 20 },

            // ✅ simple responsive state
            isMobile: window.innerWidth < 768,
        };
    },
    computed: {
        dialogStyle() {
            // full screen on mobile, wide on desktop
            return this.isMobile
                ? { width: "100vw", maxWidth: "100vw", height: "100vh" }
                : { width: "95vw", maxWidth: "90%" };
        },
        dialogContentStyle() {
            // allow scrolling inside modal on mobile
            return this.isMobile ? { height: "calc(100vh - 120px)", overflow: "auto" } : {};
        },
    },
    watch: {
        visible(isOpen) {
            if (isOpen) this.fetchHistory(1);
        },
    },
    mounted() {
        this._onResize = () => (this.isMobile = window.innerWidth < 768);
        window.addEventListener("resize", this._onResize);
    },
    beforeUnmount() {
        window.removeEventListener("resize", this._onResize);
    },
    methods: {
        onClose() {
            this.$emit("close");
        },

        toggleSort() {
            this.sortDirection = this.sortDirection === "asc" ? "desc" : "asc";
            this.fetchHistory(1);
        },

        async fetchHistory(page = 1) {
            if (page < 1) return;
            this.loading = true;

            try {
                const res = await axios.post("/amzn/fbm-orders/shippinghistory/shipmentlabel", {
                    page,
                    per_page: this.meta.per_page,
                    keyword: this.filters.keyword,
                    sort: this.sortDirection,
                });

                this.rows = res.data?.data || [];
                this.meta = res.data?.meta || this.meta;
            } catch (err) {
                console.error("ShipmentLabelHistory fetch error:", err);
                this.rows = [];
            } finally {
                this.loading = false;
            }
        },

        escapeHtml(str) {
  return String(str ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
},

async cancelLabel(row) {
  // Guard
  if (!row?.id) return;

  const shipmentId = row.shipmentid || "(no shipmentid)";
  const amazonOrderId = row.AmazonOrderId || "(no AmazonOrderId)";

  const result = await Swal.fire({
    title: "Cancel shipment label?",
    html: `
      <div style="text-align:left">
        <div><strong>AmazonOrderId:</strong> ${amazonOrderId}</div>
        <div><strong>ShipmentId:</strong> ${shipmentId}</div>
      </div>
      <br/>
      <small>This will attempt to cancel the Amazon MFN shipment and mark the record as Cancelled.</small>
    `,
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Yes, cancel it",
    cancelButtonText: "No",
    reverseButtons: true,
  });

  if (!result.isConfirmed) return;

  // Loading modal
  Swal.fire({
    title: "Cancelling...",
    text: "Please wait while we cancel the shipment label.",
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: () => Swal.showLoading(),
  });

  try {
    const res = await axios.post(
      "/amzn/fbm-orders/shippinghistory/shipmentlabel/cancel",
      { id: row.id }
    );

    // Refresh list
    await this.fetchHistory(this.meta.current_page);

    Swal.fire({
      title: "Cancelled",
      text: res.data?.message || "Shipment label cancelled successfully.",
      icon: "success",
    });
  } catch (e) {
    // Best-effort: show server reason
    const server = e?.response?.data;

    const message =
      server?.message ||
      server?.error?.message ||
      (typeof server === "string" ? server : null) ||
      e?.message ||
      "Cancel failed.";

    const details =
      server?.details ||
      server?.error ||
      server ||
      null;

    Swal.fire({
      title: "Cancel failed",
      html: `
        <div style="text-align:left">
          <div>${this.escapeHtml(message)}</div>
          ${
            details
              ? `<pre style="margin-top:10px; max-height:220px; overflow:auto; background:#111; color:#eee; padding:10px; border-radius:8px;">
${this.escapeHtml(JSON.stringify(details, null, 2))}
</pre>`
              : ""
          }
        </div>
      `,
      icon: "error",
    });
  }
},

        formatDateTime(v) {
            if (!v) return "";
            return String(v).replace("T", " ").replace("Z", "");
        },

        formatMoney(v) {
            const n = Number(v || 0);
            return isNaN(n) ? "$0.00" : `$${n.toFixed(2)}`;
        },
    },
};
</script>

<style scoped>
/* ✅ compact stacked details on mobile */
.mobile-details {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 0.9rem;
    line-height: 1.2rem;
}

/* optional: tighten the table a bit */
:deep(.p-datatable .p-datatable-thead > tr > th),
:deep(.p-datatable .p-datatable-tbody > tr > td) {
    padding: 0.5rem;
}

.wrap-anywhere {
    overflow-wrap: anywhere;
    word-break: break-word;
}

@media (max-width: 768px) {

    /* Hide column headers on mobile */
    :deep(.p-datatable-thead) {
        display: none !important;
    }

    /* Make each row look like a card */
    :deep(.p-datatable-tbody > tr) {
        display: block;
        border-bottom: 1px solid #e5e7eb;
        padding: 0.75rem 0;
    }

    /* Stack cells vertically */
    :deep(.p-datatable-tbody > tr > td) {
        display: block;
        width: 100% !important;
        border: none !important;
        padding: 0.25rem 0 !important;
    }
}
</style>
