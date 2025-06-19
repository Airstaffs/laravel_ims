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
            <label class="form-label">LCode (Step 0.01, min 0)</label>
            <input v-model.number="form.LCode" type="number" class="form-control" step="0.01" min="0" required>
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
export default {
  name: 'ManualShipmentLabelModal',
  data() {
    return {
      bsModal: null,
      form: {
        AmazonOrderId: '',
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
        formData.append(key, this.form[key]);
      }

      try {
        const res = await axios.post('/your-api/manual-label-submit', formData, {
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
        LCode: 0.01,
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
