<template>
  <div class="msku-creator">
    <h2><strong>Create MSKU</strong></h2>

    <!-- Step 1: ASIN Selection -->
    <div class="step">
      <h4>Step 1: Search and Select ASIN</h4>
      <input v-model="asinSearch" @input="fetchAsins" placeholder="Search ASIN or Title" />
      <div class="dropdown" v-if="filteredAsins.length > 0">
        <div v-for="asin in filteredAsins" :key="asin" class="dropdown-item" @click="selectAsin(asin)">
          {{ asin.asin }} - {{ asin.title }}
        </div>
      </div>
    </div>

    <div v-if="selectedAsin" class="selected-asin">
      <p><strong>Selected ASIN:</strong> {{ selectedAsin.asin }}</p>
      <p><strong>Title:</strong> {{ selectedAsin.title }}</p>
    </div>

    <!-- Step 2: MSKU Generation -->
    <div v-if="selectedAsin" class="step">
      <h4>Step 2: Create MSKU(s)</h4>
      <label>Condition:</label>
      <select v-model="selectedCondition">
        <option v-for="(label, key) in conditionMap" :value="key" :key="key">{{ label }}</option>
      </select>

      <button @click="generateMSKU">Generate MSKU</button>

      <div v-if="generatedMsku">
        <p><strong>Generated MSKU:</strong> {{ generatedMsku }}</p>
        <button @click="saveMsku">Save MSKU</button>
      </div>

      <div v-if="mskuList.length > 0">
        <h4>Pending MSKUs for {{ selectedAsin.asin }}:</h4>
        <ul>
          <li v-for="(msku, index) in mskuList" :key="index">
            {{ msku.msku }} ({{ conditionMap[msku.condition] }})
            <button @click="removeMsku(index)">Remove</button>
          </li>
        </ul>
        <button @click="submitAllMskus">Submit All MSKUs</button>
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
      mskuList: [],
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
    fetchAsins() {
      // TODO: Replace with API call
      // Mocking for now
      this.filteredAsins = [
        { asin: 'B08XYZ1234', title: 'Sample Product A' },
        { asin: 'B01ABC5678', title: 'Sample Product B' },
      ].filter(item =>
        item.asin.includes(this.asinSearch) || item.title.toLowerCase().includes(this.asinSearch.toLowerCase())
      );
    },
    selectAsin(asin) {
      this.selectedAsin = asin;
      this.generatedMsku = '';
    },
    generateMSKU() {
      if (!this.selectedAsin || !this.selectedCondition) return;

      const map = {
        "new_new": "NN",
        "new_open_box": "NOB",
        "new_oem": "NOEM",
        "refurbished_refurbished": "RR",
        "used_like_new": "ULN",
        "used_very_good": "UVG",
        "used_good": "UG",
        "used_acceptable": "UA",
        "collectible_like_new": "CLN",
        "collectible_very_good": "CVG",
        "collectible_good": "CG",
        "collectible_acceptable": "CA",
        "club_club": "CLUB"
      };

      const rand = Math.floor(1000 + Math.random() * 9000);
      const conditionCode = map[this.selectedCondition] || 'UNK';
      const msku = `${this.selectedAsin.asin}-${conditionCode}-${rand}`;

      this.mskuList.push({
        asin: this.selectedAsin.asin,
        msku: msku,
        condition: this.selectedCondition
      });
      this.generatedMsku = ''; // clear display
    },
    removeMsku(index) {
      this.mskuList.splice(index, 1);
    },
    submitAllMskus() {
      // TODO: Replace with actual POST to API
      console.log("Submitting MSKUs:", this.mskuList);
      alert("Submitting MSKUs: " + this.mskuList.map(m => m.msku).join(', '));
    },
  },
};
</script>

<style scoped>
.msku-creator {
  padding: 20px;
}

.step {
  margin-bottom: 20px;
}

.dropdown {
  border: 1px solid #ccc;
  max-height: 200px;
  overflow-y: auto;
  margin-top: 5px;
}

.dropdown-item {
  padding: 5px;
  cursor: pointer;
}

.dropdown-item:hover {
  background-color: #eee;
}
</style>
