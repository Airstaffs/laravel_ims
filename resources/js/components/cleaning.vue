<template>
  <div class="vue-container">
    <h1 class="vue-title">Cleaning Module</h1>
    <!-- Pagination -->
    <div class="pagination">
      <button @click="prevPage" :disabled="currentPage === 1" class="pagination-button">Previous</button>
      <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
      <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button">Next</button>
      <select v-model="perPage" @change="changePerPage">
        <option v-for="option in [10, 15, 20, 50, 100]" :key="option" :value="option">
          {{ option }}
        </option>
      </select>
    </div>

    <div class="table-container">
      <table class="table table-bordered">
        <thead>
          <tr>
            <th>
              <input type="checkbox" @click="toggleAll" v-model="selectAll" />
              
              <a style="color:black" @click="sortBy('AStitle')" class="sortable">
                Product Name
                <span v-if="sortColumn === 'AStitle'">
                  {{ sortOrder === 'asc' ? '▲' : '▼' }}
                </span>
              </a>
              <span style="margin-right: 20px;"></span>
              <a style="color:black" @click="sortBy('rtcounter')" class="sortable">
                RT counter
                <span v-if="sortColumn === 'rtcounter'">
                  {{ sortOrder === 'asc' ? '▲' : '▼' }}
                </span>
              </a>
            </th>
            <th>Dates/Price</th>
            <th>Details</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(item, index) in sortedInventory" :key="item.id">
            <tr>
              <td class="vue-details">
  <div class="checkbox-container">
    <input type="checkbox" v-model="item.checked" />
    <span class="placeholder-date">{{ item.shipBy || '' }}</span>
  </div>
  
  <div class="product-container">
    <img src="" alt="Product Image" class="product-thumbnail" />
    <div class="product-info">
      <p class="product-name">RT# : {{ item.rtcounter }}</p>
      <p class="product-name">{{ item.AStitle }}</p>
    </div>
  </div>
</td>


              <td>
                <span><strong>ID:</strong> {{ item.ProductID }}</span>
              </td><td>
                <span><strong>ASIN:</strong> {{ item.ProductModuleLoc }}</span>
              </td><td>
                <span><strong>FNSKU:</strong> {{ item.serialnumber }}</span>
              </td><td>
                <span><strong>Condition:</strong> {{ item.gradingview }}</span>
              </td>
         
              
              <td>
                {{ item.totalquantity }}
                <button class="btn btn-primary btn-sm" @click="toggleDetails(index)">
                  {{ expandedRows[index] ? 'Less Details' : 'More Details' }}
                </button>
              </td>
            </tr>

            <tr v-if="expandedRows[index]">
              <td colspan="4">
                <div class="expanded-content p-3 border rounded">
                  <strong>Product Name:</strong> {{ item.AStitle }}
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>

      <!-- Pagination -->
      <div class="pagination">
        <button @click="prevPage" :disabled="currentPage === 1" class="pagination-button">Previous</button>
        <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
        <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button">Next</button>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { eventBus } from './eventBus'; // Using your event bus
import '../../css/modules.css';

export default {
  name: 'ProductList',
  data() {
    return {
      inventory: [],
      currentPage: 1,
      totalPages: 1,
      perPage: 10, // Default rows per page
      selectAll: false,
      expandedRows: {},
      sortColumn: '',
      sortOrder: 'asc',
    };
  },
  computed: {
    searchQuery() {
      return eventBus.searchQuery;
    },
    sortedInventory() {
      if (!this.sortColumn) return this.inventory;
      return [...this.inventory].sort((a, b) => {
        const valueA = a[this.sortColumn];
        const valueB = b[this.sortColumn];

        if (typeof valueA === 'number' && typeof valueB === 'number') {
          return this.sortOrder === 'asc' ? valueA - valueB : valueB - valueA;
        }

        return this.sortOrder === 'asc'
          ? String(valueA).localeCompare(String(valueB))
          : String(valueB).localeCompare(String(valueA));
      });
    },
  },
  methods: {
    async fetchInventory() {
      try {
        const response = await axios.get(`http://127.0.0.1:8000/products`, {
          params: { search: this.searchQuery, page: this.currentPage, per_page: this.perPage, location: 'stockroom' },
        });

        this.inventory = response.data.data;
        this.totalPages = response.data.last_page;
      } catch (error) {
        console.error('Error fetching inventory data:', error);
      }
    },
    changePerPage() {
      this.currentPage = 1;
      this.fetchInventory();
    },
    prevPage() {
      if (this.currentPage > 1) {
        this.currentPage--;
        this.fetchInventory();
      }
    },
    nextPage() {
      if (this.currentPage < this.totalPages) {
        this.currentPage++;
        this.fetchInventory();
      }
    },
    toggleAll() {
      this.inventory.forEach((item) => (item.checked = this.selectAll));
    },
    toggleDetails(index) {
      this.expandedRows = { ...this.expandedRows, [index]: !this.expandedRows[index] };
    },
    sortBy(column) {
      if (this.sortColumn === column) {
        this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
      } else {
        this.sortColumn = column;
        this.sortOrder = 'asc';
      }
    },
  },
  watch: {
    searchQuery() {
      this.currentPage = 1;
      this.fetchInventory();
    },
  },
  mounted() {
    this.fetchInventory();
  },
};
</script>

<style>
.pagination-controls {
  margin-bottom: 10px;
}
.product-container {
  display: flex;
  align-items: center; /* Aligns image and text vertically */
}

.product-thumbnail {
  width: 100px; /* Adjust the image size as needed */
  height: auto;
  margin-right: 10px; /* Adds spacing between image and text */
}

.product-info {
  display: flex;
  flex-direction: column; /* Ensures text is stacked vertically */
}

</style>
