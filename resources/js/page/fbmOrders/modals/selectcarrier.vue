<template>
  <div v-if="visible" class="carrier-modal-backdrop" @click.self="$emit('close')">
    <div class="carrier-modal" role="dialog" aria-modal="true">
      <!-- Header -->
      <div class="carrier-modal-header">
        <div class="carrier-modal-titlewrap">
          <h5 class="carrier-modal-title">Select Carrier Option</h5>
          <div class="carrier-modal-sub" v-if="order?.platform_order_id">
            {{ order.platform_order_id }}
          </div>
        </div>

        <button class="carrier-close" type="button" @click="$emit('close')">
          ✕
        </button>
      </div>

      <!-- Tabs (sticky for mobile) -->
      <div class="carrier-modal-tabs">
        <button type="button" class="carrier-tab" :class="{ active: tab === 'eligible' }" @click="tab = 'eligible'">
          Eligible
          <span class="chip chip-ok">{{ eligibleRates.length }}</span>
        </button>

        <button type="button" class="carrier-tab" :class="{ active: tab === 'rejected' }" @click="tab = 'rejected'">
          Rejected
          <span class="chip chip-bad">{{
            rejectedRates.length
          }}</span>
        </button>
      </div>

      <!-- Body -->
      <div class="carrier-modal-body">
        <!-- Eligible -->
        <div v-if="tab === 'eligible'">
          <div v-if="!eligibleRates.length" class="carrier-empty carrier-empty-warn">
            No eligible services found.
          </div>

          <div v-else class="carrier-list">
            <button v-for="(rate, idx) in sortedEligibleRates" :key="rate.ShippingServiceId || idx" type="button"
              class="carrier-card" :class="{ selected: isSelected(rate) }" @click="select(rate)">
              <div class="carrier-card-top">
                <div class="carrier-name">
                  {{ rate.ShippingServiceName || "Service" }}
                </div>
                <div class="carrier-price">
                  ${{ rate?.Rate?.Amount ?? "0.00" }}
                </div>
              </div>

              <div class="carrier-meta">
                <div class="carrier-eta">
                  ETA:
                  {{
                    format(
                      rate.EarliestEstimatedDeliveryDate,
                    )
                  }}
                  –
                  {{
                    format(rate.LatestEstimatedDeliveryDate)
                  }}
                </div>
                <div class="carrier-code">
                  {{ rate.CarrierName }} •
                  {{ rate.ShippingServiceId }}
                </div>
              </div>
            </button>
          </div>
        </div>

        <!-- Rejected -->
        <div v-else>
          <div v-if="!rejectedRates.length" class="carrier-empty">
            No rejected services.
          </div>

          <div v-else class="rejected-list">
            <div v-for="(rej, idx) in rejectedRates" :key="rej.ShippingServiceId || idx" class="rejected-card">
              <div class="rejected-top">
                <div class="rejected-left">
                  <div class="rejected-name">
                    {{
                      rej.ShippingServiceName || "Service"
                    }}
                  </div>
                  <div class="rejected-code">
                    {{ rej.CarrierName }} •
                    {{ rej.ShippingServiceId }}
                  </div>
                </div>
                <span class="rej-badge">
                  {{ rej.RejectionReasonCode || "REJECTED" }}
                </span>
              </div>

              <div class="rejected-msg">
                {{
                  rej.RejectionReasonMessage ||
                  "No rejection message provided."
                }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="carrier-modal-footer">
        <button class="footer-btn" type="button" @click="$emit('close')">
          Cancel
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import Swal from "sweetalert2";

export default {
  name: "SelectCarrier",
  props: {
    visible: { type: Boolean, default: false },
    order: { type: Object, default: null },

    eligibleRates: { type: Array, default: () => [] },
    rejectedRates: { type: Array, default: () => [] },

    // ✅ pass the currently selected rate for this order (from parent)
    // Example from parent:
    // :selectedRate="selectedCarrierRateByOrderId?.[order?.platform_order_id] || null"
    selectedRate: { type: Object, default: null },
  },
  data() {
    return { tab: "eligible" };
  },
  watch: {
    visible(val) {
      if (val) this.tab = "eligible";
    },
  },
  methods: {
    select(rate) {
      this.$emit("select", rate);
    },
    isSelected(rate) {
      const a = this.selectedRate;
      if (!a || !rate) return false;
      return (
        a.ShippingServiceId &&
        rate.ShippingServiceId &&
        a.ShippingServiceId === rate.ShippingServiceId
      );
    },
    format(date) {
      if (!date) return "N/A";
      try {
        return new Date(date).toLocaleDateString();
      } catch (e) {
        return "N/A";
      }
    },
  },
  computed: {
    sortedEligibleRates() {
      return [...this.eligibleRates].sort((a, b) => {
        const aPrice =
          a?.Rate?.Amount ??
          a?.ShippingServiceCost?.Amount ??
          a?.TotalCharge?.Amount ??
          0;

        const bPrice =
          b?.Rate?.Amount ??
          b?.ShippingServiceCost?.Amount ??
          b?.TotalCharge?.Amount ??
          0;

        return Number(aPrice) - Number(bPrice);
      });
    },
  },
};
</script>

<style scoped>
/* Backdrop */
.carrier-modal-backdrop {
  position: fixed;
  inset: 0;
  background: #66666671;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 16px;
}

/* Modal shell */
.carrier-modal {
  width: min(920px, 100%);
  max-height: 92vh;
  background: #fff;
  border-radius: 14px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 18px 55px rgba(0, 0, 0, 0.2);
}

/* Mobile fullscreen */
@media (max-width: 576px) {
  .carrier-modal-backdrop {
    padding: 0;
  }

  .carrier-modal {
    width: 100%;
    height: 100vh;
    max-height: 100vh;
    border-radius: 0;
  }
}

/* Header */
.carrier-modal-header {
  padding: 14px 16px;
  border-bottom: 1px solid #eef2f6;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.carrier-modal-titlewrap {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.carrier-modal-title {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  /* was 800 */
  color: #0f172a;
}

.carrier-modal-sub {
  font-size: 12px;
  color: #64748b;
}

.carrier-close {
  border: 1px solid #e2e8f0;
  background: #fff;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  font-size: 18px;
  line-height: 1;
  color: #0f172a;
  cursor: pointer;
}

.carrier-close:hover {
  background: #f8fafc;
}

/* Tabs (sticky) */
.carrier-modal-tabs {
  position: sticky;
  top: 0;
  z-index: 2;
  display: flex;
  gap: 10px;
  padding: 10px 12px;
  background: #fff;
  border-bottom: 1px solid #eef2f6;
}

.carrier-tab {
  flex: 1;
  border: 1px solid #e2e8f0;
  background: #fff;
  padding: 10px 12px;
  border-radius: 12px;
  font-weight: 600;
  /* was 700 */
  font-size: 13px;
  color: #0f172a;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
}

.carrier-tab:hover {
  background: #f8fafc;
}

.carrier-tab.active {
  background: #f8fbff;
  /* subtle, not solid blue */
  border-color: #c7dbff;
  color: #0b5ed7;
}

/* Chips */
.chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 24px;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  /* was 800 */
}

.chip-ok {
  background: #eefbf3;
  color: #167a3e;
  border: 1px solid #d7f3e2;
}

.chip-bad {
  background: #fff1f2;
  color: #b42318;
  border: 1px solid #ffd6db;
}

/* Body */
.carrier-modal-body {
  padding: 12px;
  overflow: auto;
  -webkit-overflow-scrolling: touch;
}

/* Empty states */
.carrier-empty {
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #334155;
  border-radius: 12px;
  padding: 12px;
  font-weight: 500;
  /* was 600 */
  font-size: 13px;
}

.carrier-empty-warn {
  background: #fff7ed;
  border-color: #fed7aa;
  color: #9a3412;
}

/* Eligible list + cards */
.carrier-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

/* Make card feel like a “row”, not a heavy CTA */
.carrier-card {
  width: 100%;
  text-align: left;
  border: 1px solid #e2e8f0;
  background: #fff;
  border-radius: 14px;
  padding: 12px;
  cursor: pointer;
  transition:
    background 0.15s ease,
    border-color 0.15s ease,
    box-shadow 0.15s ease;
  position: relative;
}

.carrier-card:hover {
  background: #fafafa;
  border-color: #d6dde8;
  box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
}

.carrier-card:active {
  transform: none;
}

/* remove “press” effect for minimalism */

.carrier-card.selected {
  border-color: #c7dbff;
  background: #f8fbff;
  box-shadow: 0 10px 22px rgba(11, 94, 215, 0.1);
}

/* subtle check mark (replaces the bold “Selected” pill) */
.carrier-card.selected::after {
  content: "✓";
  position: absolute;
  top: 10px;
  right: 12px;
  font-size: 13px;
  color: #0b5ed7;
  opacity: 0.9;
}

/* Typography (lighter) */
.carrier-card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
}

.carrier-name {
  font-size: 14px;
  font-weight: 600;
  /* was 900 */
  color: #0f172a;
}

.carrier-price {
  font-size: 14px;
  font-weight: 600;
  /* was 900 */
  color: #0b5ed7;
  white-space: nowrap;
}

.carrier-meta {
  margin-top: 6px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.carrier-eta {
  font-size: 12px;
  color: #334155;
  font-weight: 500;
  /* was 700 */
}

.carrier-code {
  font-size: 12px;
  color: #64748b;
}

/* Rejected */
.rejected-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.rejected-card {
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 12px;
  background: #fff;
}

.rejected-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
}

.rejected-name {
  font-size: 13px;
  font-weight: 600;
  /* was 900 */
  color: #0f172a;
}

.rejected-code {
  font-size: 12px;
  color: #64748b;
  margin-top: 2px;
}

.rej-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 4px 10px;
  border-radius: 999px;
  background: #fff1f2;
  color: #b42318;
  font-weight: 600;
  /* was 900 */
  font-size: 12px;
  border: 1px solid #ffd6db;
  white-space: nowrap;
}

.rejected-msg {
  margin-top: 8px;
  font-size: 12px;
  color: #334155;
  line-height: 1.35;
}

/* Footer */
.carrier-modal-footer {
  padding: 12px;
  border-top: 1px solid #eef2f6;
  background: #fff;
}

/* Make cancel button minimal instead of “big dark bar” */
.footer-btn {
  width: 100%;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #0f172a;
  border-radius: 12px;
  padding: 10px 12px;
  font-weight: 600;
  /* was 900 */
  cursor: pointer;
}

.footer-btn:hover {
  background: #f8fafc;
}

.footer-btn:active {
  transform: none;
}
</style>
