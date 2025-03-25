<template>
    <div class="container">
        <h1>📦 FBA Inbound Shipment</h1>

        <!-- View 1: List of Existing Shipments -->
        <div v-if="!selectedShipment">
            <h2>Select a Shipment</h2>

            <div v-for="shipment in shipments" :key="shipment.shipmentID" class="shipment-block">
                <h3>{{ shipment.shipmentID }} - {{ shipment.store }} ({{ shipment.item_count }} items)</h3>

                <table class="shipment-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Details</th>
                            <th>Qty</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in shipment.items" :key="item.FNSKU">
                            <td><img :src="'https://via.placeholder.com/50'" width="50" /></td>
                            <td>
                                <div><strong>Title:</strong> {{ item.ProductName }}</div>
                                <div><strong>ASIN:</strong> {{ item.ASIN }}</div>
                                <div><strong>MSKU:</strong> {{ item.MSKU }}</div>
                                <div><strong>FNSKU:</strong> {{ item.FNSKU }}</div>
                                <div><strong>Serial#:</strong> {{ item.Serialnumber }}</div>
                            </td>
                            <td>1</td>
                            <td><button @click="deleteItem(shipment.shipmentID, item)">🗑️ Delete</button></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" style="text-align: right;">
                                <button @click="addItem(shipment.shipmentID)">➕ Add Item</button>
                                <button @click="confirmShipment(shipment.shipmentID)">✅ Confirm Shipment</button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- View 2: Create Inbound Plan (Step 1) -->
        <div v-else>
            <h2>Step 1: Create Inbound Plan for Shipment</h2>
            <form @submit.prevent="createShipment" class="shipment-form">
                <div class="form-group">
                    <label>Store:</label>
                    <input v-model="form.store" />
                </div>

                <div class="form-group">
                    <label>Destination Marketplace:</label>
                    <input v-model="form.destinationMarketplace" />
                </div>

                <div class="form-group">
                    <label>Shipment ID:</label>
                    <input v-model="form.shipmentID" disabled />
                </div>

                <button type="submit">🚀 Create Inbound Plan</button>
            </form>

            <!-- API Response -->
            <div v-if="response" class="response-box">
                <h3>API Response:</h3>
                <pre>{{ response }}</pre>
            </div>

            <button class="back-btn" @click="selectedShipment = null">🔙 Back to Shipments</button>
        </div>

        <!-- Step 2A: Generate Packing Options -->
        <div v-if="response && !packingResponse">
            <h2>Step 2A: Generate Packing</h2>
            <form @submit.prevent="generatePacking">
                <div class="form-group">
                    <label>Inbound Plan ID:</label>
                    <input v-model="form.inboundplanid" placeholder="Enter Inbound Plan ID" />
                </div>
                <button type="submit">📦 Generate Packing</button>
            </form>
        </div>

        <div v-if="packingResponse">
            <h3>Packing Response:</h3>
            <pre>{{ packingResponse }}</pre>
        </div>
    </div>
</template>

<script>
import shipmentService from '@/components/Stockroom/backend/fba_inbound_shipment_backend.js';

export default {
    data() {
        return {
            shipments: [],
            selectedShipment: null,
            form: {
                store: '',
                destinationMarketplace: '',
                shipmentID: '',
                inboundplanid: '' // include this so it's bound in Step 2A
            },
            response: null,
            packingResponse: null
        };
    },
    created() {
        this.fetchShipments();
    },
    methods: {
        async fetchShipments() {
            try {
                const res = await shipmentService.getShipments();
                this.shipments = res;
            } catch (error) {
                console.error("Error fetching shipments:", error);
            }
        },
        selectShipment(shipment) {
            this.selectedShipment = shipment;
            this.form = {
                store: shipment.store || 'Renovar Tech',
                destinationMarketplace: 'ATVPDKIKX0DER',
                shipmentID: shipment.shipmentID,
                inboundplanid: '' // default empty
            };
        },
        async createShipment() {
            try {
                const res = await shipmentService.createShipment(this.form);
                this.response = res;
            } catch (error) {
                console.error("Error creating shipment:", error);
                this.response = { error: error.message || "Failed to create shipment" };
            }
        },

        async generatePacking() {
            try {
                const res = await shipmentService.generatePacking(this.form);
                this.packingResponse = res;
            } catch (error) {
                console.error("Error generating packing:", error);
                this.packingResponse = { error: error.message || "Failed to generate packing" };
            }
        },
        addItem(shipmentID) {
            console.log('Add item to', shipmentID);
            // Modal or form logic here
        },
        deleteItem(shipmentID, item) {
            console.log('Delete', shipmentID, item);
            // API call or local remove
        },
        confirmShipment(shipmentID) {
            console.log('Confirm shipment:', shipmentID);
            // Final API step
        }
    }
};
</script>

<style scoped>
.container {
    padding: 20px;
    font-family: Arial, sans-serif;
}

.shipment-list {
    list-style: none;
    padding-left: 0;
}

.shipment-list li {
    margin: 10px 0;
}

.shipment-list button {
    padding: 10px 16px;
    background-color: #f0f0f0;
    border: 1px solid #ccc;
    cursor: pointer;
}

.shipment-form {
    margin-top: 20px;
    max-width: 400px;
}

.form-group {
    margin-bottom: 12px;
}

input {
    padding: 8px;
    width: 100%;
    box-sizing: border-box;
}

button {
    padding: 10px 18px;
    margin-top: 10px;
    cursor: pointer;
}

.response-box {
    background: #f9f9f9;
    border-left: 4px solid #4caf50;
    padding: 10px;
    margin-top: 20px;
}

.back-btn {
    margin-top: 20px;
    background: #ddd;
}
</style>