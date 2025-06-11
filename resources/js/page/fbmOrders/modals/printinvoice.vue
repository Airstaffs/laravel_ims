<template>
  <div v-if="visible" class="modal-overlay">
    <div class="modal-content">
      <button class="close-btn" @click="closeModal">×</button>
      <h2>Print Invoice</h2>

      <button class="btn-edit">Edit Price</button>
      <p>
        Are you sure you want to print the invoice for Order ID: {{ order?.platform_order_id }}
        (Store: {{ order?.storename || 'N/A' }})?
      </p>

      <div class="toggle-container">
        <label>
          <input type="checkbox" v-model="displayPrice" />
          Display Price: {{ displayPrice ? 'ON' : 'OFF' }}
        </label>
        <label>
          <input type="checkbox" v-model="testPrint" />
          Test Print: {{ testPrint ? 'ON' : 'OFF' }}
        </label>
        <label>
          <input type="checkbox" v-model="signatureRequired" />
          Signature Required: {{ signatureRequired ? 'ON' : 'OFF' }}
        </label>
      </div>

      <div class="action-buttons">
        <button class="btn-raw">Generate Raw Invoice</button>
        <button class="btn-view">View PDF</button>
        <button class="btn-print">Yes, Print</button>
        <button class="btn-cancel" @click="closeModal">Cancel</button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    visible: Boolean,
    order: Object
  },
  data() {
    return {
      displayPrice: false,
      testPrint: false,
      signatureRequired: false
    };
  },
  methods: {
    closeModal() {
      this.$emit('close');
    }
  }
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  z-index: 1005;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.5);
  display: flex;
  justify-content: center;
  align-items: center;
}
.modal-content {
  background: white;
  padding: 25px;
  width: 500px;
  border-radius: 10px;
  position: relative;
}
.close-btn {
  position: absolute;
  right: 10px;
  top: 10px;
  font-size: 20px;
  background: none;
  border: none;
}
.toggle-container label {
  display: block;
  margin-top: 10px;
}
.action-buttons {
  display: flex;
  justify-content: space-between;
  margin-top: 20px;
}
button {
  padding: 8px 12px;
  font-weight: bold;
  border: none;
  cursor: pointer;
}
.btn-edit { background: #007bff; color: white; }
.btn-raw { background: orange; color: white; }
.btn-view { background: #17a2b8; color: white; }
.btn-print { background: #28a745; color: white; }
.btn-cancel { background: #dc3545; color: white; }
</style>
