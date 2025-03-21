<template>
    <div>
      <h1>FBA Inbound Shipment</h1>
      <button @click="fetchShipments">Load Shipments</button>
  
      <div v-if="shipments.length">
        <h2>Shipment List</h2>
        <ul>
          <li v-for="shipment in shipments" :key="shipment.id">
            {{ shipment.name }} - Status: {{ shipment.status }}
          </li>
        </ul>
      </div>
    </div>
  </template>
  
  <script>
  import shipmentService from '@/backend/fba_inbound_shipment_backend.vue';
  
  export default {
    data() {
      return {
        shipments: [],
      };
    },
    methods: {
      async fetchShipments() {
        try {
          this.shipments = await shipmentService.getShipments();
        } catch (error) {
          console.error('Error fetching shipments:', error);
        }
      },
    },
  };
  </script>
  
  <style scoped>
  h1 {
    color: #333;
  }
  button {
    padding: 10px;
    margin: 10px 0;
    cursor: pointer;
  }
  </style>