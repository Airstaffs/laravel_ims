<template>
  <div class="vue-container">
    <h1 class="vue-title">Stockroom Module</h1>
    <button class="pagination-button" @click="loadFBAInboundShipment">
      FBA Inbound Shipment
    </button>
    <!-- Pagination -->
    <div class="pagination">
      <button @click="prevPage" :disabled="currentPage === 1" class="pagination-button">
        Back
      </button>
      <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
      <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button">
        Next
      </button>

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
              <a style="color: black" @click="sortBy('AStitle')" class="sortable">
                Product Name
                <span v-if="sortColumn === 'AStitle'">
                  {{ sortOrder === "asc" ? "▲" : "▼" }}
                </span>
              </a>
              <span style="margin-right: 20px"></span>
              <a style="color: black" @click="sortBy('rtcounter')" class="sortable">
                RT counter
                <span v-if="sortColumn === 'rtcounter'">
                  {{ sortOrder === "asc" ? "▲" : "▼" }}
                </span>
              </a>

              <span style="margin-right: 20px"></span>

              <button class="Desktop" style="
                                    border: solid 1px black;
                                    background-color: aliceblue;
                                " @click="toggleDetailsVisibility">
                {{
                  showDetails
                    ? "Hide extra columns"
                    : "Show extra columns"
                }}
              </button>
            </th>
            <th class="Desktop">Location</th>
            <th class="Desktop">Added date</th>
            <th class="Desktop">Updated date</th>
            <th class="Desktop">Fnsku</th>
            <th class="Desktop">Msku</th>
            <th class="Desktop">Asin</th>
            <th class="Desktop" style="background-color: antiquewhite" v-if="showDetails">
              FBM
            </th>
            <th class="Desktop" style="background-color: antiquewhite" v-if="showDetails">
              FBA
            </th>
            <th class="Desktop" style="background-color: antiquewhite" v-if="showDetails">
              Outbound
            </th>
            <th class="Desktop" style="background-color: antiquewhite" v-if="showDetails">
              Inbound
            </th>
            <th class="Desktop" style="background-color: antiquewhite" v-if="showDetails">
              Unfulfillable
            </th>
            <th class="Desktop" style="background-color: antiquewhite" v-if="showDetails">
              Reserved
            </th>
            <th class="Desktop">Fulfillment</th>
            <th class="Desktop">Status</th>
            <th class="Desktop">Serialnumber</th>
            <th class="Desktop">Actions</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(item, index) in sortedInventory" :key="item.id">
            <tr>
              <td class="vue-details">
                <div class="checkbox-container">
                  <input type="checkbox" v-model="item.checked" />
                  <span class="placeholder-date">{{
                    item.shipBy || ""
                    }}</span>
                </div>
                <div class="product-container">
                  <img src="" alt="Product Image" class="product-thumbnail" />
                  <div class="product-info">
                    <p class="product-name">
                      RT# : {{ item.rtcounter }}
                    </p>
                    <p class="product-name">
                      {{ item.AStitle }}
                    </p>

                    <p class="Mobile">
                      Location :
                      {{ item.warehouselocation }}
                    </p>
                    <p class="Mobile">
                      Added date :
                      {{ item.datedelivered }}
                    </p>
                    <p class="Mobile">
                      Updated date :
                      {{ item.lastDateUpdate }}
                    </p>
                    <p class="Mobile">
                      Fnsku : {{ item.FNSKUviewer }}
                    </p>
                    <p class="Mobile">
                      Msku : {{ item.MSKUviewer }}
                    </p>
                    <p class="Mobile">
                      Asin : {{ item.ASINviewer }}
                    </p>
                  </div>
                </div>
              </td>
              <td class="Desktop">
                <span><strong></strong>
                  {{ item.warehouselocation }}</span>
              </td>

              <td class="Desktop">
                <span><strong></strong>
                  {{ item.datedelivered }}</span>
              </td>

              <td class="Desktop">
                <span><strong></strong>
                  {{ item.lastDateUpdate }}</span>
              </td>

              <td class="Desktop">
                <span><strong></strong>
                  {{ item.FNSKUviewer }}</span>
              </td>

              <td class="Desktop">
                <span><strong></strong>
                  {{ item.MSKUviewer }}</span>
              </td>

              <td class="Desktop">
                <span><strong></strong>
                  {{ item.ASINviewer }}</span>
              </td>

              <!-- Hidden -->
              <!-- Hidden -->
              <!-- Hidden -->
              <td v-if="showDetails">
                <span><strong></strong>
                  {{ item.FBMAvailable }}</span>
              </td>
              <td v-if="showDetails">
                <span><strong></strong>
                  {{ item.FbaAvailable }}</span>
              </td>
              <td v-if="showDetails">
                <span><strong></strong> {{ item.Outbound }}</span>
              </td>
              <td v-if="showDetails">
                <span><strong></strong> {{ item.Inbound }}</span>
              </td>
              <td v-if="showDetails">
                <span><strong></strong> {{ item.Reserved }}</span>
              </td>
              <td v-if="showDetails">
                <span><strong></strong>
                  {{ item.Unfulfillable }}</span>
              </td>
              <!-- Hidden -->
              <!-- Hidden -->
              <!-- Hidden -->

              <td class="Desktop">
                <span><strong></strong>
                  {{ item.Fulfilledby }}</span>
              </td>

              <td class="Desktop">
                <span><strong></strong> {{ item.Status }}</span>
              </td>

              <td class="Desktop">
                <span><strong></strong>
                  {{ item.serialnumber }}</span>
              </td>

              <!-- Button for more details -->
              <td class="Desktop">
                {{ item.totalquantity }}
                <button class="btn-moredetails" @click="toggleDetails(index)">
                  {{
                    expandedRows[index]
                      ? "Less Details"
                      : "More Details"
                  }}
                </button>
                <br />
                <button class="btn-moredetails">example</button><br />
                <button class="btn-moredetails">example</button><br />
                <button class="btn-moredetails">example</button><br />
              </td>
            </tr>
            <!-- More details results -->
            <tr v-if="expandedRows[index]">
              <td colspan="11">
                <div class="expanded-content p-3 border rounded">
                  <div class="Mobile">
                    <button class="btn-moredetails">
                      sample button
                    </button>
                    <button class="btn-moredetails">
                      sample button
                    </button>
                    <button class="btn-moredetails">
                      sample button
                    </button>
                  </div>
                  <strong>Product Name:</strong>
                  {{ item.AStitle }}
                </div>
              </td>
            </tr>

            <!-- Button for more details (Mobile) -->
            <td class="Mobile">
              {{ item.totalquantity }}
              <button style="
                                    width: 100%;
                                    border-bottom: 2px solid black;
                                    padding: 0px;
                                " @click="toggleDetails(index)">
                {{
                  expandedRows[index]
                    ? "Less Details ▲ "
                    : "More Details ▼ "
                }}
              </button>
            </td>
          </template>
        </tbody>
      </table>
      <!-- Pagination -->
      <div class="pagination">
        <button @click="prevPage" :disabled="currentPage === 1" class="pagination-button">
          Back
        </button>
        <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
        <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button">
          Next
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import axios from "axios";
import { eventBus } from "./eventBus"; // Using your event bus
import "../../css/modules.css";

export default {
  name: "ProductList",
  data() {
    return {
      inventory: [],
      currentPage: 1,
      totalPages: 1,
      perPage: 10, // Default rows per page
      selectAll: false,
      expandedRows: {},
      sortColumn: "",
      sortOrder: "asc",
      showDetails: false,
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

        if (typeof valueA === "number" && typeof valueB === "number") {
          return this.sortOrder === "asc"
            ? valueA - valueB
            : valueB - valueA;
        }

        return this.sortOrder === "asc"
          ? String(valueA).localeCompare(String(valueB))
          : String(valueB).localeCompare(String(valueA));
      });
    },
  },
  methods: {
    async fetchInventory() {
      try {
        const response = await axios.get(
          `http://127.0.0.1:8000/products`,
          {
            params: {
              search: this.searchQuery,
              page: this.currentPage,
              per_page: this.perPage,
              location: "stockroom",
            },
          }
        );

        this.inventory = response.data.data;
        this.totalPages = response.data.last_page;
      } catch (error) {
        console.error("Error fetching inventory data:", error);
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
      this.expandedRows = {
        ...this.expandedRows,
        [index]: !this.expandedRows[index],
      };
    },
    toggleDetailsVisibility() {
      this.showDetails = !this.showDetails;
    },
    sortBy(column) {
      if (this.sortColumn === column) {
        this.sortOrder = this.sortOrder === "asc" ? "desc" : "asc";
      } else {
        this.sortColumn = column;
        this.sortOrder = "asc";
      }
    },
    loadFBAInboundShipment() {
      if (window.loadContent) {
        window.loadContent("fbashipmentinbound");
      } else {
        console.error("loadContent not found on window");
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
.expanded-content {
  background-color: azure;
}
</style>
