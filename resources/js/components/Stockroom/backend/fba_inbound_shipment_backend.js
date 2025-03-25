import axios from 'axios';

const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
  async getShipments() {
    const res = await axios.get(`${API_BASE_URL}/amzn/fba-shipment/fetch-shipments`);
    return res.data;
  },
  async createShipment(payload) {
    const res = await axios.get(`${API_BASE_URL}/amzn/fba-shipment/step1/create-shipment`, {
      params: payload
    });
    return res.data;
  },
  async generatePacking(payload) {
    const res = await axios.get(`${API_BASE_URL}/amzn/fba-shipment/step2/generate-packing`, {
      params: payload
    });
    return res.data;
  }
};
