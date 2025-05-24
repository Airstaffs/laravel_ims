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
          <button class="btn btn-lightgreen" @click="sendToZebraPrinter">Send to Printer</button>
        </div>

        <div id="designPreview" class="mt-4 p-3 border bg-white">
          <h4><strong>Ship To:</strong></h4>
          <p><strong>{{ form.CustomerName }}</strong></p>
          <p>{{ form.AddressLine1 }}</p>
          <p>{{ form.City }}, {{ form.StateOrRegion }}, {{ form.PostalCode }}, {{ form.CountryCode }}</p>

          <p><strong>Order ID:</strong> {{ form.AmazonOrderId }}</p>
          <svg id="barcode_AmazonOrderId"></svg>

          <p class="mt-2">Thank you for buying from ... on Amazon Marketplace.</p>

          <table class="table table-sm table-borderless mt-3">
            <tbody>
              <tr>
                <td class="max-width-cell"><strong>Shipping Address:</strong></td>
                <td class="max-width-cell"><strong>Order Date:</strong></td>
                <td class="max-width-cell">{{ form.OrderDate }}</td>
              </tr>
              <tr>
                <td class="max-width-cell">{{ form.CustomerName }}</td>
                <td class="max-width-cell"><strong>Ship by Date:</strong></td>
                <td class="max-width-cell">{{ form.ShipByDateEarliest }} - {{ form.ShipByDateLatest }}</td>
              </tr>
              <tr>
                <td class="max-width-cell">{{ form.AddressLine1 }}</td>
                <td class="max-width-cell"><strong>Deliver by Date:</strong></td>
                <td class="max-width-cell">{{ form.DeliveryByDateEarliest }} - {{ form.DeliveryByDateLatest }}</td>
              </tr>
              <tr>
                <td class="max-width-cell">{{ form.City }}, {{ form.StateOrRegion }}</td>
                <td class="max-width-cell"><strong>Shipping Service:</strong></td>
                <td class="max-width-cell">{{ form.ShippingService }}</td>
              </tr>
              <tr>
                <td class="max-width-cell">{{ form.CountryCode }}, {{ form.PostalCode }}</td>
                <td class="max-width-cell"><strong>Buyer Name:</strong></td>
                <td class="max-width-cell">{{ form.CustomerName.split(' ')[0] }}</td>
              </tr>
              <tr>
                <td class="max-width-cell"></td>
                <td class="max-width-cell"><strong>Seller Name:</strong></td>
                <td class="max-width-cell">{{ form.SellerName }}</td>
              </tr>
            </tbody>
          </table>

          <div v-for="(item, index) in items" :key="index" class="mt-4">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th style="width: 20%;">Quantity</th>
                  <th>Product Details</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>{{ item.Quantity }}</td>
                  <td>
                    <strong>{{ item.Title }}</strong><br />
                    <strong>SKU:</strong> {{ item.MSKU }}<br />
                    <strong>ASIN:</strong> {{ item.ASIN }}<br />
                    <svg :id="`barcode_ASIN_${index}`"></svg><br />
                    <strong>Condition:</strong> {{ item.Condition || 'New - New' }}<br />
                    <strong>Order Item ID:</strong> {{ item.OrderItemId || 'N/A' }}<br />
                    <strong>P Code:</strong> $ {{ item.PCode || 'X.XX' }}<br />
                    <strong>S Code:</strong> $ {{ item.SCode || 'X.XX' }}<br />
                    <strong>L Code:</strong> $ {{ item.LCode || 'X.XX' }}
                  </td>
                </tr>
                <tr>
                  <td colspan="2"><strong>Note:</strong> {{ item.Note || '' }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="text-end mt-3">
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
      this.fieldVisibility[id] = !this.fieldVisibility[id];
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
    },
    generateZPL() {
      const { form, items } = this;

      let zpl = "^XA";
      zpl += `^FO50,50^ADN,36,20^FDShip To:^FS`;
      zpl += `^FO50,90^FD${form.CustomerName}^FS`;
      zpl += `^FO50,130^FD${form.AddressLine1}^FS`;
      zpl += `^FO50,170^FD${form.City}, ${form.StateOrRegion}^FS`;
      zpl += `^FO50,210^FD${form.PostalCode}, ${form.CountryCode}^FS`;

      zpl += `^FO50,260^FDOrder ID: ${form.AmazonOrderId}^FS`;
      zpl += `^FO50,300^FDOrder Date: ${form.OrderDate}^FS`;
      zpl += `^FO50,340^FDShip by: ${form.ShipByDateEarliest} - ${form.ShipByDateLatest}^FS`;
      zpl += `^FO50,380^FDDeliver by: ${form.DeliveryByDateEarliest} - ${form.DeliveryByDateLatest}^FS`;
      zpl += `^FO50,420^FDShipping Service: ${form.ShippingService}^FS`;

      let y = 470;
      items.forEach((item, index) => {
        zpl += `^FO50,${y}^FDQty: ${item.Quantity} - ${item.Title}^FS`;
        y += 40;
        zpl += `^FO50,${y}^FDSKU: ${item.MSKU} ASIN: ${item.ASIN}^FS`;
        y += 40;
        zpl += `^FO50,${y}^FDCondition: ${item.Condition || 'New - New'}^FS`;
        y += 40;
        zpl += `^FO50,${y}^FDOrderItemId: ${item.OrderItemId || 'N/A'}^FS`;
        y += 40;
        zpl += `^FO50,${y}^FDNote: ${item.Note || ''}^FS`;
        y += 60;
      });

      zpl += `^FO50,${y}^FDCreated by: ${form.labelcreator}^FS`;
      zpl += "^XZ";

      return zpl;
    },

    async sendToZebraPrinter() {
      const zplFullCommand = this.generateZPL();
      const pIp = this.form.printerIp || "192.168.1.240";

      try {
        const res = await axios.post("http://99.0.87.190:1450/ims/Admin/modules/PRD-RPN-PCN/print.php", {
          zpl: zplFullCommand,
          printerSelect: pIp,
        });
        alert("Sent to printer successfully");
      } catch (err) {
        console.error("Printer error", err);
        alert("Failed to send to printer");
      }
    }
  }
};
</script>

<style scoped>
#designPreview {
  background: #fff;
  font-size: 14px;
}

.max-width-cell {
  max-width: 200px;
  word-break: break-word;
  white-space: normal;
}

.btn-lightgreen {
  background-color: #90ee90; /* Light green */
  color: #fff;
  border: none;
}
</style>
