<template>
  <div class="msku-creator">
    <h2><strong>Create MSKU</strong></h2>

    <!-- Step 1: ASIN Selection -->
    <div class="step">
      <h4>Step 1: Search and Select ASIN</h4>
      <input v-model="asinSearch" @input="fetchAsins" placeholder="Search ASIN or Title" />
      <div class="dropdown" v-if="filteredAsins.length > 0">
        <div v-for="asin in filteredAsins" :key="asin" class="dropdown-item" @click="selectAsin(asin)">
          {{ asin.ASIN }} - {{ asin.title }}
        </div>
      </div>
    </div>

    <div v-if="selectedAsin" class="selected-asin">
      <p><strong>Selected ASIN:</strong> {{ selectedAsin.asin }}</p>
      <p><strong>Title:</strong> {{ selectedAsin.title }}</p>
    </div>

    <label>Store:</label>
    <select v-model="selectedStore">
      <option disabled value="">Select Store</option>
      <option v-for="store in storeOptions" :key="store" :value="store">{{ store }}</option>
    </select>

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
const API_BASE_URL = import.meta.env.VITE_API_URL;
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

export default {
  name: "IMSListing",
  data() {
    return {
      allowedUserIds: ['Jundell', 'Admin', 'Julius', 'Fries'],
      selectedStore: '',
      storeOptions: [],
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
      filteredAsins: [],
      selectedAsin: null,
      selectedCondition: '',
      conditionMap: {
        "new_new": "New",
        "new_open_box": "New - Open Box",
        "new_oem": "New - OEM",
        "refurbished_refurbished": "Refurbished",
        "used_like_new": "Used - Like New",
        "used_very_good": "Used - Very Good",
        "used_good": "Used - Good",
        "used_acceptable": "Used - Acceptable",
        "collectible_like_new": "Collectible - Like New",
        "collectible_very_good": "Collectible - Very Good",
        "collectible_good": "Collectible - Good",
        "collectible_acceptable": "Collectible - Acceptable",
        "club_club": "Club"
      },
      generatedMsku: '',

    };
  },
  computed: {
    isAllowedUser() {
      return this.allowedUserIds.includes(this.$session?.id); // Simulate session
    },
  },
  methods: {
    fetchStores() {
      fetch(`${API_BASE_URL}/api/asinlist/all/stores`)
        .then(res => res.json())
        .then(data => {
          if (Array.isArray(data)) {
            this.storeOptions = data;
          } else {
            console.error('Invalid store response:', data);
          }
        })
        .catch(err => {
          console.error('Failed to load stores:', err);
        });
    },
    fetchAsins() {
      if (!this.asinSearch.trim()) return;

      fetch(`${API_BASE_URL}/api/asinlist/asin/search?keyword=${encodeURIComponent(this.asinSearch)}`)
        .then(res => res.json())
        .then(data => {
          this.filteredAsins = Array.isArray(data) ? data : [];
        })
        .catch(err => {
          console.error("ASIN search failed:", err);
          this.filteredAsins = [];
        });
    },
    selectAsin(asin) {
      this.selectedAsin = asin;
      this.generatedMsku = '';
      this.asinSearch = '';         // ✅ clear input
      this.filteredAsins = [];      // ✅ close dropdown
    },
    generateMSKU() {
      if (!this.selectedAsin || !this.selectedCondition) return;

      fetch(`${API_BASE_URL}/api/asinlist/msku/generate`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
          asin: this.selectedAsin.ASIN,
          condition: this.selectedCondition
        })
      })
        .then(res => res.json())
        .then(data => {
          if (data.msku) {
            this.generatedMsku = data.msku;
          } else {
            alert(data.error || 'Failed to generate MSKU');
          }
        })
        .catch(err => {
          console.error('Generate MSKU error:', err);
          alert('Generate MSKU failed');
        });
    },
    removeMsku(index) {
      this.mskuList.splice(index, 1);
    },
    submitAllMskus() {
      if (this.mskuList.length === 0) return;

      fetch(`${API_BASE_URL}/api/asinlist/msku/save`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ mskus: this.mskuList })
      })
        .then(res => res.json())
        .then(data => {
          let message = '';
          if (data.success?.length) {
            message += `✅ Success: ${data.success.join(', ')}\n`;
          }
          if (data.duplicates?.length) {
            message += `⚠️ Duplicates: ${data.duplicates.join(', ')}\n`;
          }
          if (data.failed?.length) {
            message += `❌ Failed: ${data.failed.map(f => f.msku).join(', ')}\n`;
          }
          alert(message || 'Submitted!');
          this.mskuList = [];
        })
        .catch(err => {
          console.error('Submit error:', err);
          alert('Submit failed');
        });
    },
    saveMsku() {
      if (!this.generatedMsku) return;
      this.mskuList.push({
        asin: this.selectedAsin.ASIN,
        msku: this.generatedMsku,
        condition: this.selectedCondition,
        storename: this.selectedStore || 'Renovartech' // You can make this a real selector
      });
      this.generatedMsku = '';
    }

  },
  mounted() {

    this.fetchStores();

  }
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
