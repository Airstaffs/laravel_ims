<script>
import axios from 'axios';

const API_URL = 'https://your-api-endpoint.com'; // Replace with your actual API URL

export default {
  async getShipments() {
    try {
      const response = await axios.get(`${API_URL}/shipments`);
      return response.data;
    } catch (error) {
      console.error('API Error:', error);
      throw error;
    }
  },

  async createShipment(shipmentData) {
    try {
      const response = await axios.post(`${API_URL}/shipments`, shipmentData);
      return response.data;
    } catch (error) {
      console.error('API Error:', error);
      throw error;
    }
  },

  async updateShipment(id, shipmentData) {
    try {
      const response = await axios.put(`${API_URL}/shipments/${id}`, shipmentData);
      return response.data;
    } catch (error) {
      console.error('API Error:', error);
      throw error;
    }
  },

  async deleteShipment(id) {
    try {
      const response = await axios.delete(`${API_URL}/shipments/${id}`);
      return response.data;
    } catch (error) {
      console.error('API Error:', error);
      throw error;
    }
  },
};
</script>
