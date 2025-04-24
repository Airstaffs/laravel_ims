<template>
  <div class="vue-container">
    <div class="header-actions">
      <h1 class="vue-title">FNSKU LIST</h1>
      <button @click="showInsertFnskuModal" class="insertfnsku-btn">
        <i class="bi bi-plus"></i> ADD FNSKU
      </button>
    </div>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>
              <input type="checkbox" @click="toggleAll" v-model="selectAll" />
              <span class="header-date"></span>
            </th>
            <th>Details</th>
            <th>Order Details</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, index) in inventory" :key="item.FNSKUID">
            <td>
              <div class="checkbox-container">
                <input type="checkbox" v-model="item.checked" />
              </div>
              <img :src="item.imageUrl" alt="Product Image" class="product-thumbnail" />
            </td>
            <td class="vue-details">
              <span class="product-name">{{ item.astitle }}</span>
            </td>
            <td class="vue-details">
              <span><strong>ID:</strong> {{ item.FNSKUID }}</span><br />
              <span><strong>ASIN:</strong> {{ item.ASIN }}</span><br />
              <span><strong>FNSKU:</strong> {{ item.FNSKU }}</span><br />
              <span><strong>Condition:</strong> {{ item.grading }}</span><br />
              <span><strong>Status:</strong> {{ item.fnsku_status }}</span><br />
              <span><strong>Store:</strong> {{ item.storename }}</span><br />
              <span><strong>Units:</strong> {{ item.Units }}</span><br />
            </td>
            <td>
              {{ item.totalquantity }}
              <button @click="toggleDetails(index)" class="more-details-btn">
                {{ expandedRows[index] ? 'Less Details' : 'More Details' }}
              </button>
            </td>
          </tr>
          <tr v-if="expandedRows[index]" class="expanded-row">
            <td colspan="4">
              <div class="expanded-content">
                <strong>Product Name:</strong> {{ item.astitle }}
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <!-- Pagination -->
      <div class="pagination">
        <button @click="prevPage" :disabled="currentPage === 1" class="pagination-button">Previous</button>
        <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
        <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button">Next</button>
      </div>
    </div>

     <!-- Insert FNSKU Modal -->
    <div class="modal" v-if="isInsertFnskuModalVisible">
      <div class="modal-content">
        <div class="modal-header">
          <h2>Add New FNSKU</h2>
          <span class="close" @click="hideInsertFnskuModal">&times;</span>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="newFnsku">FNSKU:</label>
            <input
              type="text"
              id="newFnsku"
              v-model="newFnskuData.fnsku"
              placeholder="Enter FNSKU"
              class="form-control"
              ref="newFnskuInput"
              @input="focusNext('newMskuInput')"
            />
          </div>
          
          <div class="form-group">
            <label for="newMsku">MSKU:</label>
            <input
              type="text"
              id="newMsku"
              v-model="newFnskuData.msku"
              placeholder="Enter MSKU"
              class="form-control"
              ref="newMskuInput"
              @input="focusNext('newAsinInput')"
            />
          </div>
          
          <div class="form-group">
            <label for="newAsin">ASIN:</label>
            <input
              type="text"
              id="newAsin"
              v-model="newFnskuData.asin"
              placeholder="Enter ASIN"
              class="form-control"
              ref="newAsinInput"
              @input="focusNext('newTitleInput')"
            />
          </div>
          
          <div class="form-group">
            <label for="newTitle">Title:</label>
            <input
              type="text"
              id="newTitle"
              v-model="newFnskuData.astitle"
              placeholder="Enter Product Title"
              class="form-control"
              ref="newTitleInput"
              @input="focusNext('newGradingInput')"
            />
          </div>
          
            <div class="form-group">
            <label for="newGrading">Grading:</label>
            <select
              id="newGrading"
              v-model="newFnskuData.grading"
              class="form-control"
              ref="newGradingInput"
            >
              <option value="New">New</option>
              <option value="Like New">Like New</option>
              <option value="Very Good">Very Good</option>
              <option value="Good">Good</option>
              <option value="Acceptable">Acceptable</option>
            </select>
            </div>

            <div class="form-group">
            <label for="newStoreName">Store Name:</label>
            <select
              id="newStoreName"
              v-model="newFnskuData.storeName"
              class="form-control"
              ref="newStoreNameInput"
            >
              <option value="Allrenewed">Allrenewed</option>
              <option value="Renovartech">Renovartech</option>
            </select>
            </div>
          
          <div class="form-actions">
            <button @click="saveNewFnsku" class="btn-save">Save FNSKU</button>
            <button @click="hideInsertFnskuModal" class="btn-cancel">Cancel</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import axios from 'axios';
import { eventBus } from './eventBus'; // Using your event bus
import '../../css/modules.css';
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
  name: 'ProductList',
  data() {
    return {
      inventory: [],
      currentPage: 1,
      totalPages: 1,
      selectAll: false,
      fnsku: '',
      msku: '',
      asin: '',
      grading: 'New',
      astitle: '',
      expandedRows: {},
      isInsertFnskuModalVisible: false,
      storeName: 'Allrenewed',
      newFnskuData: {
        fnsku: '',
        msku: '',
        asin: '',
        grading: 'New',
        astitle: '',
        storeName: 'Allrenewed'
      }
    };
  },
  computed: {
    searchQuery() {
      return eventBus.searchQuery; // Making search reactive
    },
  },
  methods: {
    async fetchInventory() {
      try {
        const response = await axios.get(`${API_BASE_URL}/fnsku`, {
          params: { search: this.searchQuery, page: this.currentPage, },
        });

        this.inventory = response.data.data;
        this.totalPages = response.data.last_page;
      } catch (error) {
        console.error('Error fetching inventory data:', error);
      }
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
      this.$set(this.expandedRows, index, !this.expandedRows[index]);
    },

    showInsertFnskuModal() {
      this.isInsertFnskuModalVisible = true;
      this.newFnskuData = {
        fnsku: '',
        msku: '',
        asin: '',
        grading: 'New',
        astitle: '',
        storeName: 'Allrenewed',
      };
      
      // Set focus on FNSKU input
      this.$nextTick(() => {
        if (this.$refs.newFnskuInput) {
          this.$refs.newFnskuInput.focus();
        }
      });
    },
    
    hideInsertFnskuModal() {
      this.isInsertFnskuModalVisible = false;
    },
    
    async saveNewFnsku() {
      // Validate required fields
      if (!this.newFnskuData.fnsku || !this.newFnskuData.asin || !this.newFnskuData.astitle) {
        alert('FNSKU, ASIN, and Title are required fields.');
        return;
      }
      
      try {
        const response = await axios.get(`${API_BASE_URL}/insert-fnsku`, {
          fnsku: this.newFnskuData.fnsku,
          msku: this.newFnskuData.msku || null,
          asin: this.newFnskuData.asin,
          grading: this.newFnskuData.grading,
          astitle: this.newFnskuData.astitle,
          storename: this.newFnskuData.storeName,
          _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        });
        
        if (response.data.success) {
          alert('FNSKU added successfully!');
          this.hideInsertFnskuModal();
          
          // Refresh FNSKU list if currently viewing the FNSKU modal
          if (this.isFnskuModalVisible) {
            this.fetchFnskuList();
          }
        } else {
          alert(response.data.message || 'Failed to add FNSKU');
        }
      } catch (error) {
        console.error('Error adding FNSKU:', error);
        alert('Failed to add FNSKU. Please try again.');
      }
    },
    
    focusNext(refName) {
      this.$nextTick(() => {
        if (this.$refs[refName]) {
          this.$refs[refName].focus();
        }
      });
    }

  },
  watch: {
    searchQuery() {
      this.currentPage = 1; // Reset to first page on search
      this.fetchInventory();
    },
  },
  mounted() {
    this.fetchInventory();
  },
};
</script>
<style scoped>
.vue-container {
  padding: 20px;
}

.header-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.vue-title {
  font-size: 24px;
  font-weight: bold;
}

.insertfnsku-btn {
  background-color: #007bff;
  color: white;
  border: none;
  padding: 10px 20px;
  cursor: pointer;
  border-radius: 5px;
}

.insertfnsku-btn:hover {
  background-color: #0056b3;
}

.table-container {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th, td {
  padding: 10px;
  text-align: left;
  border-bottom: 1px solid #ddd;
}

.checkbox-container {
  display: flex;
  align-items: center;
}

.product-thumbnail {
  width: 50px;
  height: 50px;
  object-fit: cover;
  margin-left: 10px;
}

.vue-details {
  white-space: nowrap;
}

.more-details-btn {
  background-color: transparent;
  border: none;
  color: #007bff;
  cursor: pointer;
}

.more-details-btn:hover {
  text-decoration: underline;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  margin-top: 20px;
}

.pagination-button {
  background-color: #007bff;
  color: white;
  border: none;
  padding: 10px 20px;
  cursor: pointer;
  border-radius: 5px;
  margin: 0 5px;
}

.pagination-button:disabled {
  background-color: #cccccc;
  cursor: not-allowed;
}

.pagination-info {
  margin: 0 10px;
}

.modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
}

.modal-content {
  background-color: white;
  padding: 20px;
  border-radius: 5px;
  width: 500px;
  max-width: 100%;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.modal-header h2 {
  margin: 0;
}

.close {
  cursor: pointer;
  font-size: 24px;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
}

.form-control {
  width: 100%;
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 5px;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.btn-save {
  background-color: #28a745;
  color: white;
  border: none;
  padding: 10px 20px;
  cursor: pointer;
  border-radius: 5px;
}

.btn-save:hover {
  background-color: #218838;
}

.btn-cancel {
  background-color: #dc3545;
  color: white;
  border: none;
  padding: 10px 20px;
  cursor: pointer;
  border-radius: 5px;
}

.btn-cancel:hover {
  background-color: #c82333;
}
</style>
