<template>
  <div class="modal fade" id="manualShipmentLabelModal" tabindex="-1" aria-labelledby="manualShipmentLabelModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <form @submit.prevent="submitLabel" enctype="multipart/form-data" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="manualShipmentLabelModalLabel">Manual Shipment Label</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Amazon Order ID</label>
            <input v-model="form.AmazonOrderId" type="text" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Order Item IDs</label>
            <div v-for="(item, index) in form.OrderItemIds" :key="index" class="input-group mb-2">
              <input v-model="form.OrderItemIds[index]"  type="text" class="form-control" placeholder="Enter OrderItemId"
                required style="width: 100%;" />
              <button type="button" class="btn btn-danger" @click="removeOrderItemId(index)"
                v-if="form.OrderItemIds.length > 1">×</button>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" @click="addOrderItemId">+ Add
              OrderItemId</button>
          </div>

          <div class="mb-3">
            <label class="form-label">LCode</label>
            <input v-model.number="form.LCode" type="number" class="form-control" step="0.01" min="00.00" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Ship Date</label>
            <input v-model="form.ShipDate" type="datetime-local" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Tracking Number</label>
            <input v-model="form.TrackingNumber" type="text" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Carrier</label>
            <select v-model="form.Carrier" class="form-select" required>
              <option value="Other">Other</option>
              <option value="USPS">USPS</option>
              <option value="UPS">UPS</option>
              <option value="FedEx">FedEx</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Shipping Delivery Experience</label>
            <select v-model="form.DeliveryExperience" class="form-select" required>
              <option value="DeliveryConfirmationWithoutSignature">DeliveryConfirmationWithoutSignature</option>
              <option value="DeliveryConfirmationWithSignature">DeliveryConfirmationWithSignature</option>
              <option value="DeliveryConfirmationWithAdultSignature">DeliveryConfirmationWithAdultSignature</option>
              <option value="NoTracking">NoTracking</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Shipping Label PDF</label>
            <input @change="handleFileUpload" type="file" accept="application/pdf" class="form-control" required>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" @click="resetForm">Cancel</button>
          <button type="submit" class="btn btn-primary">Submit</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
  name: 'ManualShipmentLabelModal',
  data() {
    return {
      bsModal: null,
      form: {
        AmazonOrderId: '',
        OrderItemIds: [''],
        LCode: 0.01,
        ShipDate: '',
        TrackingNumber: '',
        Carrier: 'USPS',
        DeliveryExperience: 'DeliveryConfirmationWithoutSignature',
        shippinglabelpdf: null,
      }
    };
  },
  mounted() {
    const modalEl = document.getElementById('manualShipmentLabelModal');
    if (modalEl) {
      this.bsModal = new bootstrap.Modal(modalEl, {
        backdrop: 'static',
        keyboard: false,
      });

      modalEl.addEventListener('hidden.bs.modal', () => {
        this.resetForm();
      });
    }
  },
  methods: {
    addOrderItemId() {
      this.form.OrderItemIds.push('');
    },
    removeOrderItemId(index) {
      this.form.OrderItemIds.splice(index, 1);
    },
    show() {
      if (this.bsModal) this.bsModal.show();
    },
    hide() {
      if (this.bsModal) this.bsModal.hide();
    },
    handleFileUpload(event) {
      this.form.shippinglabelpdf = event.target.files[0];
    },
    async submitLabel() {
      if (!this.form.shippinglabelpdf) {
        alert("Please upload a PDF file.");
        return;
      }

      const formData = new FormData();

      for (const key in this.form) {
        if (key === 'OrderItemIds') {
          this.form.OrderItemIds.forEach((itemId, i) => {
            formData.append(`OrderItemIds[${i}]`, itemId);
          });
        } else {
          formData.append(key, this.form[key]);
        }
      }

      try {
        const res = await axios.post(`${API_BASE_URL}/fbm-orders-manualshipmentlabel`, formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          }
        });

        if (res.data.success) {
          alert('Label submitted successfully!');
          this.resetForm();
          window.closeManualShipmentLabel();
        } else {
          alert('Submission failed: ' + res.data.error);
        }
      } catch (err) {
        console.error(err);
        alert('Error occurred during submission.');
      }
    },
    resetForm() {
      this.form = {
        AmazonOrderId: '',
        LCode: 0.00,
        ShipDate: '',
        TrackingNumber: '',
        Carrier: 'USPS',
        DeliveryExperience: 'DeliveryConfirmationWithoutSignature',
        shippinglabelpdf: null,
      };
    }
  }
}
</script>
