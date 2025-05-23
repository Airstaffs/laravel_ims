<template>
  <div class="container-fluid" id="print-invoice-page">
    <div class="row">
      <!-- Left Panel: Form Inputs -->
      <div class="col-md-4" id="leftPanel">
        <h3>Order Information</h3>

        <div v-for="field in formFields" :key="field.id" class="mb-3">
          <div class="d-flex align-items-center mb-1">
            <label :for="field.id" class="form-label me-2" style="min-width: 150px;">{{ field.label }}</label>
            <button type="button" class="btn btn-sm btn-outline-secondary"
              @click="toggleFieldVisibility(field.id)">👁️</button>
          </div>
          <input v-if="fieldVisibility[field.id]" :type="field.type" class="form-control" :id="field.id"
            v-model="form[field.id]" />
        </div>

        <h3>Items</h3>
        <button class="btn btn-secondary mb-2" @click="addItem">Add Item</button>

        <div v-for="(item, index) in items" :key="index" class="border p-2 mb-2">
          <h5>Item {{ index + 1 }}</h5>
          <div v-for="field in itemFields" :key="field.id">
            <label :for="`${field.id}_${index}`" class="form-label">{{ field.label }}</label>
            <input :id="`${field.id}_${index}`" class="form-control mb-2" :type="field.type" v-model="item[field.id]" />
          </div>
          <button class="btn btn-sm btn-danger" @click="removeItem(index)">Remove</button>
        </div>

        <h3>System Info</h3>
        <div class="mb-3">
          <label for="invoicenumberid" class="form-label">Invoice Number ID</label>
          <input id="invoicenumberid" class="form-control" v-model="form.invoicenumberid" />
        </div>
        <div class="mb-3">
          <label for="labelcreator" class="form-label">Label Creator</label>
          <input id="labelcreator" class="form-control" v-model="form.labelcreator" />
        </div>
      </div>

      <!-- Right Panel: Invoice Preview -->
      <div class="col-md-8">
        <div class="d-flex justify-content-between">
          <button class="btn btn-outline-primary" @click="downloadAsPDF">Export as PDF</button>
          <button class="btn btn-success" @click="submitInvoice">Submit Invoice</button>
        </div>

        <div id="designPreview" class="mt-4 p-3 border">
          <h4>Ship To:</h4>
          <p><strong>{{ form.CustomerName }}</strong></p>
          <p>{{ form.AddressLine1 }}</p>
          <p>{{ form.City }}, {{ form.StateOrRegion }}, {{ form.PostalCode }}, {{ form.CountryCode }}</p>

          <svg id="barcode_AmazonOrderId"></svg>
          <p>Order ID: {{ form.AmazonOrderId }}</p>

          <div class="mt-3">
            <h5>Items</h5>
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>Title</th>
                  <th>ASIN</th>
                  <th>MSKU</th>
                  <th>Qty</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in items" :key="index">
                  <td>{{ item.Title }}</td>
                  <td>{{ item.ASIN }}</td>
                  <td>{{ item.MSKU }}</td>
                  <td>{{ item.Quantity }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="mt-3 text-end">
            <p><strong>Created by:</strong> {{ form.labelcreator }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import JsBarcode from "jsbarcode";
import html2pdf from "html2pdf.js";

export default {
  name: "printcustominvoice",
  data() {
    return {
      form: {
        AmazonOrderId: "",
        CustomerName: "",
        AddressLine1: "",
        City: "",
        StateOrRegion: "",
        PostalCode: "",
        CountryCode: "",
        OrderDate: "",
        ShipByDateEarliest: "",
        ShipByDateLatest: "",
        DeliveryByDateEarliest: "",
        DeliveryByDateLatest: "",
        ShippingService: "",
        SellerName: "",
        invoicenumberid: "",
        labelcreator: ""
      },
      fieldVisibility: {
        AmazonOrderId: true,
        CustomerName: true,
        AddressLine1: true,
        City: true,
        StateOrRegion: true,
        PostalCode: true,
        CountryCode: true,
        OrderDate: true,
        ShipByDateEarliest: true,
        ShipByDateLatest: true,
        DeliveryByDateEarliest: true,
        DeliveryByDateLatest: true,
        ShippingService: true,
        SellerName: true,
      },
      items: [],
      formFields: [
        { id: "AmazonOrderId", label: "Amazon Order ID", type: "text" },
        { id: "CustomerName", label: "Customer Name", type: "text" },
        { id: "AddressLine1", label: "Address Line 1", type: "text" },
        { id: "City", label: "City", type: "text" },
        { id: "StateOrRegion", label: "State", type: "text" },
        { id: "PostalCode", label: "Postal Code", type: "text" },
        { id: "CountryCode", label: "Country", type: "text" },
        { id: "OrderDate", label: "Order Date", type: "text" },
        { id: "ShipByDateEarliest", label: "Ship By Date (Earliest)", type: "text" },
        { id: "ShipByDateLatest", label: "Ship By Date (Latest)", type: "text" },
        { id: "DeliveryByDateEarliest", label: "Delivery Date (Earliest)", type: "text" },
        { id: "DeliveryByDateLatest", label: "Delivery Date (Latest)", type: "text" },
        { id: "ShippingService", label: "Shipping Service", type: "text" },
        { id: "SellerName", label: "Seller Name", type: "text" }
      ],
      itemFields: [
        { id: "Title", label: "Title", type: "text" },
        { id: "ASIN", label: "ASIN", type: "text" },
        { id: "MSKU", label: "MSKU", type: "text" },
        { id: "Quantity", label: "Quantity", type: "number" }
      ]
    };
  },
  watch: {
    'form.AmazonOrderId': {
      handler(newVal) {
        this.generateBarcode(newVal);
      },
      immediate: true
    }
  },
  methods: {
    toggleFieldVisibility(id) {
      this.$set(this.fieldVisibility, id, !this.fieldVisibility[id]);
    },
    addItem() {
      this.items.push({ Title: "", ASIN: "", MSKU: "", Quantity: 1 });
    },
    removeItem(index) {
      this.items.splice(index, 1);
    },
    generateBarcode(value) {
      this.$nextTick(() => {
        try {
          JsBarcode("#barcode_AmazonOrderId", value || " ", {
            format: "CODE128",
            lineColor: "#000",
            width: 2,
            height: 40,
            displayValue: false
          });
        } catch (_) { }
      });
    },
    downloadAsPDF() {
      const element = document.getElementById("designPreview");
      html2pdf().from(element).save("invoice-4x6.pdf");
    },
    submitInvoice() {
      alert("Invoice submitted (mock).");
      // Submit logic goes here
    }
  }
};
</script>

<style scoped>
#designPreview {
  background: #fff;
  font-size: 14px;
}
</style>
