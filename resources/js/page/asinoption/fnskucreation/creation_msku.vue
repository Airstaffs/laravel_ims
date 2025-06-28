<template>
  <div class="ims-listing">
    
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
        .info-icon {
            font-size: 24px;
            display: inline-block;
            margin-left: 5px;
            color: #0294A5;
            /* Information icon color */
            cursor: help;
            /* Change cursor to the help icon on hover */
            position: relative;
            /* Positioning context for the tooltip */
        }

        .info-icon:hover::after {
            font-size: 25px;
            content: attr(title);
            /* Use the title attribute for the tooltip text */
            position: absolute;
            left: 20px;
            /* Tooltip position - adjust as needed */
            top: -5px;
            white-space: nowrap;
            /* Keep the tooltip text in a single line */
            z-index: 10;
            /* Ensure the tooltip is above other content */
            background-color: #333;
            /* Tooltip background color */
            color: #fff;
            /* Tooltip text color */
            padding: 5px 8px;
            /* Padding inside the tooltip */
            border-radius: 4px;
            /* Rounded corners for the tooltip */
            font-size: 0.8em;
            /* Smaller font size for the tooltip */
            box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.3);
            /* Slight shadow for depth */
            opacity: 0;
            /* Start with the tooltip invisible */
            transition: opacity 0.3s ease-in-out;
            /* Animated transition for tooltip */
        }

        .tab-links {
            margin-bottom: 10px;
            text-align: center;
        }

        .tab-link {
            padding: 10px 15px;
            margin-right: 5px;
            border: 1px solid #ddd;
            background-color: #41ad53;
            cursor: pointer;
        }

        .tab-link:hover {
            background-color: #e9e9e9;
        }

        .tab-link.active {
            background-color: #ddd;
        }

        .tab-content {
            display: none;
            /* Hidden by default */
        }

        .tab-content.active {
            display: block;
            /* The active tab content */
        }


        body {
            font-family: Arial, sans-serif;
        }

        #searchForm {
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            /* Allow form elements to wrap on small screens */
            align-items: center;
            gap: 10px;
            /* Adds spacing between elements */
        }

        #searchForm select,
        #searchForm input[type="text"] {
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            width: 250px;
            max-width: 100%;
            /* Ensure it doesn't overflow on smaller screens */
        }

        #searchForm input[type="submit"] {
            padding: 10px 20px;
            font-size: 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        #searchForm input[type="submit"]:hover {
            background-color: #45a049;
        }

        /* Mobile Styles */
        @media screen and (max-width: 768px) {
            #searchForm {
                flex-direction: column;
                /* Stack elements vertically */
                align-items: stretch;
                /* Align inputs to fill width */
            }

            #searchForm select,
            #searchForm input[type="text"],
            #searchForm input[type="submit"] {
                width: 100%;
                /* Make inputs take up full width */
                margin-left: 0;
                /* Remove extra margin on submit button */
            }
        }

        /* Table Styles */
        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f2f2f2;
        }

        select {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            width: 100%;
        }

        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #45a049;
        }

        .container {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        /* Styles for the marketplace ID */
        .restriction {
            background-color: #f9f9f9;
            border: 1px solid #ccc;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
        }

        .restriction p {
            margin: 5px 0;
        }

        .restriction a {
            color: blue;
            text-decoration: none;
        }

        .restriction a:hover {
            text-decoration: underline;
        }

        .help-block {
            color: #666;
            font-size: small;
            margin-bottom: 5px;
            font-style: italic;
        }

        fieldset {
            max-width: 500px;
            margin: 0 auto;
        }

        fieldset input {
            height: 25px;
            width: 300px;
        }

        fieldset label {
            font-weight: bold;
        }

        .modal-overlay-condition-error {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-container-condition-error {
            background-color: #fff;
            border-radius: 8px;
            width: 80%;
            max-width: 1000px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
            animation: fadeIn 0.3s ease-in-out;
        }

        .modal-header-condition-error {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }

        .modal-header-condition-error h2 {
            margin: 0;
        }

        .close-button-condition-error {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 24px;
            font-weight: bold;
            color: #000;
            cursor: pointer;
        }

        .close-button-condition-error:hover {
            color: #ff0000;
        }

        .modal-body-condition-error {
            margin-top: 10px;
            max-height: 700px;
            overflow-y: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table,
        th,
        td {
            border: 1px solid #ddd;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            padding: 20px;
        }

        .offer-form {
            width: 400px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }

        .offer-form h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group select {
            width: 95%;
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .form-group select {
            height: 45px;
        }

        .form-submit {
            display: flex;
            justify-content: center;
        }

        .form-submit button {
            padding: 10px 20px;
            font-size: 16px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .form-submit button:hover {
            background-color: #218838;
        }

        /* Modal styling */
        .modal_searchProduct {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
            /* Black w/ opacity */
        }

        .modal-content-search-product {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            /* Could be adjusted */
        }

        /* Close button styling */
        .close-search-product {
            color: #aaa;
            float: right;
            font-size: 40px;
            font-weight: bold;
            margin-right: 15px;
        }

        .close-search-product:hover,
        .close-search-product:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        /* Default styles for desktop */
        #result table {
            width: 100%;
            border-collapse: collapse;
        }

        #result th,
        #result td {
            padding: 8px;
            text-align: left;
        }

        #result #asin_cell {
            /* Default styles for ASIN cell */
            font-weight: bold;
        }

        /* Mobile styles */
        @media only screen and (max-width: 600px) {
            #result table {
                width: 100%;
                border-collapse: collapse;
                display: block;
                overflow-x: auto;
            }

            #result th,
            #result td {
                display: block;
                width: 100%;
                box-sizing: border-box;
            }

            #result tr {
                /* display: block; */
                margin-bottom: 1em;
            }

            #result td {
                text-align: right;
                position: relative;
                padding-left: 50%;
                border: none;
                border-bottom: 1px solid #ddd;
            }

            #result td::before {
                content: attr(data-label);
                position: absolute;
                left: 0;
                width: 50%;
                padding-left: 10px;
                font-weight: bold;
                text-align: left;
            }

            #result td#asin_cell::before {
                content: "ASIN";
            }

            .modal-content-search-product {
                width: 95%;
                font-size: 14px;
                /* Could be adjusted */
            }

            .buttonselectasin {
                padding: 5px;
                font-size: 12px;
                width: 100%;
                /* Make the button full width on mobile */
                box-sizing: border-box;
                /* Ensure padding doesn't affect the button's width */
            }
        }

        .item-name {
            cursor: pointer;
            color: blue;
            text-decoration: underline;
        }

        .full-item-name {
            margin-top: 5px;
        }

        /* Modal Container */
        .modal_image_pop_up {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
        }

        /* Modal Image */
        .modal-image-content-pop-up {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 80%;
        }

        .close_image_modal_pop_up {
            position: absolute;
            top: 20px;
            right: 35px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }

        /* Previous and Next buttons */
        .prev,
        .next {
            cursor: pointer;
            position: absolute;
            top: 50%;
            padding: 16px;
            margin-top: -50px;
            color: white;
            font-weight: bold;
            font-size: 30px;
            user-select: none;
        }

        .prev {
            left: 10px;
        }

        .next {
            right: 10px;
        }

        .hide-in-mobile {
            display: none;
        }

        .search-dropdown {
            position: relative;
            display: inline-block;
        }

        #searchInput {
            width: 200px;
            padding: 8px;
            box-sizing: border-box;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            border: 1px solid #ddd;
            background-color: #fff;
            max-height: 150px;
            overflow-y: auto;
            width: 100%;
            z-index: 1;
        }

        .dropdown-content div {
            padding: 8px;
            cursor: pointer;
        }

        .dropdown-content div:hover {
            background-color: #f1f1f1;
        }

        .selected-value {
            display: flex;
            align-items: center;
            margin-top: 8px;
        }

        .selected-value #selectedText {
            margin-right: 8px;
        }

        .clear-btn {
            background: red;
            color: white;
            border: none;
            padding: 4px 8px;
            cursor: pointer;
            border-radius: 4px;
        }

        @media only screen and (max-width: 600px) {

            /* Modal restrict*/
            #restrictionModal table {
                width: 100%;
                border-collapse: collapse;
            }

            #restrictionModal th,
            #restrictionModal td {
                border: 1px solid #ddd;
                padding: 10px;
                text-align: left;
            }

            #restrictionModal th {
                background-color: #f2f2f2;
            }

            #restrictionModal tbody {
                max-height: 700px;
                overflow-y: auto;
            }

            /* END */

            .modal-image-content-pop-up {
                margin: auto;
                display: block;
                max-width: 90%;
                max-height: 80%;
                position: absolute;
                top: 190px;
                right: 19px;
            }

            .prev,
            .next {
                top: 90%;
            }

            .hide-in-mobile {
                display: none;
            }
        }

        #toggleAdminAsin {
            padding: 7px 10px;
            border: none;
            border-radius: 5px;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        #toggleAdminAsin {
            padding: 7px 10px;
            border: none;
            border-radius: 5px;
            color: white;
            font-size: 16px;
            cursor: pointer;
            background-color: red;
        }

</style>
