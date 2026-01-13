<template>
  <div v-if="visible" class="carrier-modal-backdrop">
    <div class="carrier-modal">
      <div class="carrier-modal-header">
        <h5>Select Carrier Option</h5>
        <button class="btn btn-sm btn-light" @click="$emit('close')">✕</button>
      </div>

      <div class="carrier-modal-body">
        <p v-if="order">
          <strong>Amazon Order Id:</strong> {{ order.platform_order_id }}
        </p>

        <div v-if="!rates?.length" class="alert alert-danger">
          No rates available.
        </div>

        <ul v-else class="list-unstyled d-flex flex-column gap-2">
          <li
            v-for="(rate, idx) in rates"
            :key="rate.ShippingServiceId || idx"
          >
            <button
              class="btn btn-outline-primary w-100 text-start"
              @click="select(rate)"
            >
              <div class="d-flex justify-content-between">
                <strong>{{ rate.ShippingServiceName }}</strong>
                <strong>${{ rate.Rate.Amount }}</strong>
              </div>
              <div class="small">
                ETA:
                {{ format(rate.EarliestEstimatedDeliveryDate) }} –
                {{ format(rate.LatestEstimatedDeliveryDate) }}
              </div>
            </button>
          </li>
        </ul>
      </div>

      <div class="carrier-modal-footer">
        <button class="btn btn-secondary w-100" @click="$emit('close')">
          Cancel
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "CarrierModal",
  props: {
    visible: Boolean,
    order: Object,
    rates: {
      type: Array,
      default: () => [],
    },
  },
  methods: {
    select(rate) {
      this.$emit("select", rate);
    },
    format(date) {
      if (!date) return "N/A";
      return new Date(date).toLocaleDateString();
    },
  },
};
</script>

<style scoped>
/* (paste the mobile-friendly CSS here) */
</style>
