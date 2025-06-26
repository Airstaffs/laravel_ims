<template>
  <div class="ims-listing">
    <a href="../../index.php">Home</a>
    <h3><strong>MSKU Creation & Listing</strong></h3>

    <div v-if="isAllowedUser">
      <button id="adminButton">Admin Search</button>
      <button id="toggleAdminAsin" class="btn-off" @click="toggleBypass">
        Bypass ASIN restrict
      </button>
    </div>

    <div id="admin_input_pass"></div>
    <div id="searchProduct"></div>

    <!-- Modal -->
    <div v-if="showProductModal" class="modal_searchProduct">
      <div class="modal-content-search-product">
        <span class="close-search-product" @click="closeProductModal">&times;</span>
        <h2>Search Product</h2>
        <input v-model="productSearch" placeholder="Enter product name" />
        <button @click="searchProduct">Search Product</button>

        <div v-if="imageModalVisible" class="modal_image_pop_up">
          <span class="close_image_modal_pop_up" @click="closeImageModal">&times;</span>
          <img :src="modalImage" class="modal-image-content-pop-up" />
          <div id="caption">{{ modalCaption }}</div>
          <a class="prev" @click="prevImage">&#10094;</a>
          <a class="next" @click="nextImage">&#10095;</a>
        </div>

        <div id="productResult"></div>
      </div>
    </div>

    <h4><strong>Step 1: Select Store & ASIN</strong></h4>
    <h4 style="color: lightred;">If you change step 1 please repeat steps 2 & 3!</h4>

    <!-- Step 1 Form -->
    <form @submit.prevent="fetchDetails" id="searchForm">
      <select v-model="selectedStore">
        <option value="">Select</option>
        <option value="RT">Renovar Tech</option>
        <option value="AR">Allrenewed</option>
      </select>

      <div class="search-dropdown">
        <input
          v-model="searchInput"
          placeholder="Search ASIN OR Title"
          autocomplete="off"
        />
        <div class="dropdown-content" v-show="showDropdown">
          <div v-for="option in dropdownOptions" :key="option" @click="selectDropdown(option)">
            {{ option }}
          </div>
        </div>

        <div v-if="selectedValue" class="selected-value">
          <span>{{ selectedValue }}</span>
          <button type="button" class="clear-btn" @click="clearSelected">X</button>
        </div>
      </div>

      <select v-model="requirements">
        <option value="LISTING_OFFER_ONLY" selected>LISTING OFFER ONLY</option>
      </select>

      <input type="submit" value="Fetch Details" />
    </form>

    <div class="container" id="responseContainer">
      <!-- JSON response shown here -->
    </div>

    <!-- Offer Form -->
    <div class="offer-form" v-show="showOfferForm">
      <h2>Submit an Offer</h2>
      <form @submit.prevent="submitOffer">
        <h4>Step 4</h4>

        <div class="form-group">
          <label>List Price</label>
          <input v-model="offer.price" type="number" step="0.01" placeholder="Enter price" />
        </div>

        <div class="form-group">
          <label>Currency</label>
          <select v-model="offer.currency">
            <option value="USD">USD</option>
            <option value="EUR">EUR</option>
            <option value="GBP">GBP</option>
            <option value="JPY">JPY</option>
          </select>
        </div>

        <div class="form-group">
          <label>Fulfillment Channel</label>
          <select v-model="offer.fulfillment">
            <option value="FBA">FBA</option>
            <option value="FBM">FBM</option>
          </select>
        </div>

        <div class="form-group">
          <label>Other Marketplace</label>
          <input v-model="offer.marketplace" placeholder="Marketplace" />
        </div>

        <div class="form-submit">
          <button type="submit">Submit Offer</button>
        </div>
      </form>
    </div>

    <!-- Restriction Modal -->
    <div v-if="showRestrictionModal" class="modal-overlay-condition-error">
      <div class="modal-container-condition-error">
        <div class="modal-header-condition-error">
          <span class="close-button-condition-error" @click="closeRestrictionModal">&times;</span>
          <h2>Product Restrictions</h2>
        </div>
        <div class="modal-body-condition-error">
          <table>
            <thead>
              <tr>
                <th>Condition</th>
                <th>Operation</th>
                <th>Link & Message</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, index) in restrictionTable" :key="index">
                <td>{{ row.condition }}</td>
                <td>{{ row.operation }}</td>
                <td><a :href="row.link" target="_blank">{{ row.message }}</a></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "IMSListing",
  data() {
    return {
      allowedUserIds: ['Jundell', 'Admin', 'Julius', 'Fries'],
      selectedStore: '',
      searchInput: '',
      dropdownOptions: [],
      selectedValue: '',
      showDropdown: false,
      showProductModal: false,
      productSearch: '',
      imageModalVisible: false,
      modalImage: '',
      modalCaption: '',
      showOfferForm: false,
      offer: {
        price: 0,
        currency: 'USD',
        fulfillment: 'FBA',
        marketplace: 'ATVPDKIKX0DER',
      },
      requirements: 'LISTING_OFFER_ONLY',
      showRestrictionModal: false,
      restrictionTable: [],
    };
  },
  computed: {
    isAllowedUser() {
      return this.allowedUserIds.includes(this.$session?.id); // Simulate session
    },
  },
  methods: {
    fetchDetails() {
      console.log('Fetching details...');
    },
    searchProduct() {
      console.log('Searching product...');
    },
    toggleBypass() {
      alert('Bypass triggered');
    },
    selectDropdown(option) {
      this.selectedValue = option;
      this.searchInput = '';
      this.showDropdown = false;
    },
    clearSelected() {
      this.selectedValue = '';
    },
    closeProductModal() {
      this.showProductModal = false;
    },
    closeImageModal() {
      this.imageModalVisible = false;
    },
    prevImage() {},
    nextImage() {},
    submitOffer() {
      console.log('Submitting offer:', this.offer);
    },
    closeRestrictionModal() {
      this.showRestrictionModal = false;
    },
  },
};
</script>

<style scoped>
/* Paste your entire style block here and remove duplicated body rules */
</style>
