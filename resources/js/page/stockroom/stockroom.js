import { eventBus } from '../../components/eventBus';
import ScannerComponent from '../../components/Scanner.vue';
import { SoundService } from '../../components/Sound_service';
import '../../../css/modules.css';
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
  name: 'StockroomModule',
  components: {
    ScannerComponent
  },
  data() {
    return {
      inventory: [],
      currentPage: 1,
      totalPages: 1,
      perPage: 15, // Increased default for better UX
      selectAll: false,
      expandedRows: {},
      serialDropdowns: {},
      sortColumn: "",
      sortOrder: "asc",
      showDetails: false,
      
      // Loading states
      isLoading: false,
      isSearching: false,
      
      // Debouncing
      searchDebounceTimer: null,
      fetchDebounceTimer: null,
      
      // Caching
      inventoryCache: new Map(),
      cacheExpiry: 30000, // 30 seconds
      
      // Store filter
      stores: [],
      selectedStore: '',
      
      // Scanner data
      serialNumber: '',
      fnsku: '',
      locationInput: '',
      showManualInput: false,
      
      // For auto verification
      autoVerifyTimeout: null,
      
      // For FNSKU validation
      fnskuValid: false,
      fnskuChecking: false,
      fnskuStatus: '',
      
      // For process modal (replaces move modal)
      showProcessModal: false,
      processShipmentType: 'For Dispense',
      processTrackingNumber: '',
      processNotes: '',
      processLocation: '',    
      currentProcessItem: null,
      currentProductId: null,
      currentProductAsin: null,
      currentProductTitle: '',
      selectedItems: [],
      selectAllItems: false,
      isProcessing: false,
      
      // For product details modal
      showProductDetailsModal: false,
      selectedProduct: null,
      enlargeImage: false,
      
      // For image handling
      defaultImagePath: '/images/default-product.png'
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
    isMobile() {
      return window.innerWidth <= 768;
    },
    singleItemSelected() {
      return this.selectedItems.length === 1;
    },
    hasSelectedItems() {
      return this.selectedItems.length > 0;
    },
    isProcessFormValid() {
      return this.processShipmentType && 
             this.processTrackingNumber && 
             this.selectedItems.length > 0;
    }
  },
  methods: {
    // Function to get the image path based on ASIN
    getImagePath(asin) {
      return asin ? `/images/asinimg/${asin}_0.png` : this.defaultImagePath;
    },
    
    // Simplified image error handling that just swaps to default image
    handleImageError(event, item) {
      event.target.src = this.defaultImagePath;
      if (item) item.useDefaultImage = true;
    },
    
    // Add this method to create an SVG placeholder
    createDefaultImageSVG() {
      return `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100' width='100' height='100'%3E%3Crect width='100' height='100' fill='%23f0f0f0'/%3E%3Cpath d='M35,30L65,30L65,70L35,70Z' fill='%23e0e0e0' stroke='%23bbbbbb' stroke-width='2'/%3E%3Cpath d='M45,40L55,40L55,60L45,60Z' fill='%23d0d0d0' stroke='%23bbbbbb'/%3E%3Cpath d='M35,80L65,80L65,85L35,85Z' fill='%23e0e0e0'/%3E%3C/svg%3E`;
    },
    
    // Format the item count to show pack information
    formatItemCount(item) {
      if (!item) return '0';
      
      if (item.pack_size && item.pack_size > 1) {
        return `${item.box_count} boxes (${item.item_count} units)`;
      }
      
      return item.item_count.toString();
    },
    
    // Extract and display pack information
    getPackInfo(item) {
      if (!item || !item.AStitle) return '';
      
      const packMatch = item.AStitle.match(/(\d+)-Pack/i);
      if (packMatch && packMatch[1]) {
        return `${packMatch[1]}-Pack`;
      }
      
      return '';
    },
    
    // Add a separate method for viewing product image
    viewProductImage(item) {
      this.selectedProduct = item;
      this.showProductDetailsModal = true;
    },
    
    // Regular product details modal
    viewProductDetails(item) {
      this.selectedProduct = item;
      this.showProductDetailsModal = true;
    },
    
    // Close product details modal
    closeProductDetailsModal() {
      this.showProductDetailsModal = false;
      this.selectedProduct = null;
      this.enlargeImage = false;
    },
    
    // Open process modal from product details
    openProcessModalFromDetails(item) {
      this.closeProductDetailsModal();
      this.openProcessModal(item);
    },
    
    // Open scanner modal method
    openScannerModal() {
      this.$refs.scanner.openScannerModal();
    },
    
    loadFBAInboundShipment() {
      if (window.loadContent) {
        window.loadContent("fbashipmentinbound");
      } else {
        console.error("loadContent not found on window");
      }
    },
    
    // Format RT number based on store name
    formatRTNumber(rtCounter, storeName) {
      const paddedCounter = String(rtCounter).padStart(5, '0');
      
      if (storeName === 'RenovarTech') {
        return `RT ${paddedCounter}`;
      } else if (storeName === 'Allrenewed') {
        return `AR ${paddedCounter}`;
      } else {
        return `#${paddedCounter}`;
      }
    },
    
    // Store dropdown functions with caching
    async fetchStores() {
      try {
        // Check cache first using our inventory cache system
        const cacheKey = 'stockroom_stores';
        if (this.inventoryCache.has(cacheKey)) {
          const cached = this.inventoryCache.get(cacheKey);
          if (Date.now() - cached.timestamp < 3600000) { // 1 hour cache
            this.stores = cached.data;
            return;
          }
        }
        
        const response = await axios.get(`${API_BASE_URL}/api/stockroom/stores`, {
          withCredentials: true
        });
        this.stores = response.data;
        
        // Cache the stores for 1 hour using our existing cache system
        this.inventoryCache.set(cacheKey, {
          data: response.data,
          timestamp: Date.now()
        });
      } catch (error) {
        console.error("Error fetching stores:", error);
        console.error("Error details:", error.response?.data);
      }
    },
    
    changeStore() {
      this.currentPage = 1;
      this.inventoryCache.clear(); // Clear cache when store changes
      this.fetchInventory(false);
    },
    
    // Validate the item count against serials
    validateItemCount(item) {
      if (!item) return true;
      
      if (!item.serials || item.serials.length === 0) {
        return true;
      }
      
      if (item.pack_size && item.pack_size > 1) {
        const serialCount = item.serials.length;
        return serialCount === item.box_count;
      }
      
      const serialCount = item.serials.length;
      return serialCount === item.item_count;
    },
    
    // Optimized inventory fetching with caching and debouncing
    async fetchInventory(useCache = true) {
      if (this.isLoading) return;
      
      // Create cache key
      const cacheKey = `${this.searchQuery}_${this.currentPage}_${this.perPage}_${this.selectedStore}`;
      
      // Check cache first
      if (useCache && this.inventoryCache.has(cacheKey)) {
        const cached = this.inventoryCache.get(cacheKey);
        if (Date.now() - cached.timestamp < this.cacheExpiry) {
          this.applyInventoryData(cached.data);
          return;
        }
      }
      
      // Debounce rapid requests
      if (this.fetchDebounceTimer) {
        clearTimeout(this.fetchDebounceTimer);
      }
      
      this.fetchDebounceTimer = setTimeout(async () => {
        await this.performFetch(cacheKey);
      }, this.searchQuery ? 300 : 100);
    },
    
    async performFetch(cacheKey) {
      try {
        this.isLoading = true;
        this.isSearching = !!this.searchQuery;
        
        const startTime = performance.now();
        
        const response = await axios.get(`${API_BASE_URL}/api/stockroom/products`, {
          params: { 
            search: this.searchQuery, 
            page: this.currentPage, 
            per_page: this.perPage,
            store: this.selectedStore
          },
          withCredentials: true,
          timeout: 10000
        });
        
        console.log(`Fetch took ${performance.now() - startTime}ms`);
        
        // Cache the response
        this.inventoryCache.set(cacheKey, {
          data: response.data,
          timestamp: Date.now()
        });
        
        // Clean old cache entries
        this.cleanCache();
        
        this.applyInventoryData(response.data);
        
      } catch (error) {
        console.error("Error fetching inventory:", error);
        this.handleFetchError(error);
      } finally {
        this.isLoading = false;
        this.isSearching = false;
      }
    },
    
    // Apply inventory data with optimizations
    applyInventoryData(data) {
      const startTime = performance.now();
      
      // Use Object.freeze for immutable data to prevent unnecessary reactivity
      this.inventory = (data.data || []).map(item => {
        const optimizedItem = {
          ...item,
          checked: false,
          serials: Object.freeze(item.serials || []),
          fnskus: Object.freeze(item.fnskus || []),
          useDefaultImage: false,
          countValid: this.validateItemCount(item),
          pack_size: item.pack_size || 1,
          box_count: item.box_count || item.item_count
        };
        
        return Object.freeze(optimizedItem);
      });
      
      this.totalPages = data.last_page || 1;
      
      console.log(`Data processing took ${performance.now() - startTime}ms`);
    },
    
    // Clean expired cache entries
    cleanCache() {
      const now = Date.now();
      for (const [key, value] of this.inventoryCache.entries()) {
        if (now - value.timestamp > this.cacheExpiry) {
          this.inventoryCache.delete(key);
        }
      }
      
      // Limit cache size
      if (this.inventoryCache.size > 50) {
        const oldestKeys = Array.from(this.inventoryCache.keys()).slice(0, 10);
        oldestKeys.forEach(key => this.inventoryCache.delete(key));
      }
    },
    
    // Optimized search with debouncing
    handleSearchChange() {
      if (this.searchDebounceTimer) {
        clearTimeout(this.searchDebounceTimer);
      }
      
      this.searchDebounceTimer = setTimeout(() => {
        this.currentPage = 1;
        this.fetchInventory(false); // Don't use cache for new searches
      }, 300);
    },
    
    // Error handling
    handleFetchError(error) {
      if (error.code === 'ECONNABORTED') {
        console.error('Request timeout');
      } else if (error.response?.status === 500) {
        console.error('Server error');
      }
      
      if (SoundService?.error) {
        SoundService.error();
      }
    },

    // Pagination methods
    changePerPage() {
      this.currentPage = 1;
      this.inventoryCache.clear(); // Clear cache when per page changes
      this.fetchInventory(false);
    },
    
    prevPage() {
      if (this.currentPage > 1 && !this.isLoading) {
        this.currentPage--;
        this.fetchInventory();
      }
    },
    
    nextPage() {
      if (this.currentPage < this.totalPages && !this.isLoading) {
        this.currentPage++;
        this.fetchInventory();
      }
    },

    // Inventory selection methods
    toggleAll() {
      this.inventory.forEach((item) => (item.checked = this.selectAll));
    },
    
    toggleDetails(index) {
      const updatedExpandedRows = { ...this.expandedRows };
      updatedExpandedRows[index] = !updatedExpandedRows[index];
      this.expandedRows = updatedExpandedRows;
    },
    
    toggleDetailsVisibility() {
      this.showDetails = !this.showDetails;
    },

    // Sorting method
    sortBy(column) {
      if (this.sortColumn === column) {
        this.sortOrder = this.sortOrder === "asc" ? "desc" : "asc";
      } else {
        this.sortColumn = column;
        this.sortOrder = "asc";
      }
    },
    
    // Process modal functions
    openProcessModal(item) {
      this.currentProcessItem = item;
      this.showProcessModal = true;
      this.processShipmentType = 'For Dispense';
      this.processTrackingNumber = '';
      this.processNotes = '';
      this.processLocation = '';
      this.selectedItems = [];
      this.selectAllItems = false;
      
      this.currentProductId = item.ProductID || null;
      this.currentProductAsin = item.ASIN || null;
      this.currentProductTitle = item.AStitle || '';
      
      // If the item has just one serial number, pre-select it and show its location
      if (item.serials && item.serials.length === 1) {
        const singleSerial = item.serials[0];
        this.selectedItems = [singleSerial.ProductID];
        this.processLocation = singleSerial.warehouselocation || '';
        
        this.$nextTick(() => {
          const locationInput = document.querySelector('.process-modal .form-control[placeholder="e.g., L123A or Floor"]');
          if (locationInput) {
            locationInput.focus();
            locationInput.select();
          }
        });
      }
    },
    
    closeProcessModal() {
      this.showProcessModal = false;
      this.currentProcessItem = null;
      this.selectedItems = [];
    },
    
    // Toggle selection of all items
    toggleAllItems() {
      if (this.selectAllItems) {
        this.selectedItems = this.currentProcessItem.serials.map(serial => serial.ProductID);
        
        if (this.selectedItems.length > 1) {
          this.processLocation = '';
        }
      } else {
        this.selectedItems = [];
        this.processLocation = '';
      }
    },
    
    // Submit the process
    async submitProcess() {
      if (!this.isProcessFormValid) return;
      
      try {
        this.isProcessing = true;
        
        const processData = {
          shipmentType: this.processShipmentType,
          trackingNumber: this.processTrackingNumber,
          notes: this.processNotes,
          items: this.selectedItems
        };
        
        const response = await axios.post('/api/stockroom/process-items', processData, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
          }
        });
        
        if (response.data.success) {
          alert(`Successfully processed ${this.selectedItems.length} items`);
          this.closeProcessModal();
          this.inventoryCache.clear(); // Clear cache after processing
          this.fetchInventory(false);
        } else {
          alert(`Error: ${response.data.message || 'Failed to process items'}`);
        }
      } catch (error) {
        console.error('Error processing items:', error);
        alert('Failed to process items. Please try again.');
      } finally {
        this.isProcessing = false;
      }
    },
    
    // Update location for a single selected item
    async updateSelectedLocation() {
      if (!this.hasSelectedItems) {
        alert('Please select at least one item to update location.');
        return;
      }
      
      if (!this.processLocation) {
        alert('Please enter a new location.');
        return;
      }
      
      // Validate location format
      const locationRegex = /^L\d{3}[A-G]$/i;
      const isValid = locationRegex.test(this.processLocation.trim()) || 
                    this.processLocation.trim() === 'Floor' || 
                    this.processLocation.trim() === 'L800G';
                    
      if (!isValid) {
        alert('Invalid Location Format (use L###X, Floor, or L800G)');
        return;
      }
      
      try {
        this.isProcessing = true;
        
        const updateData = {
          itemId: this.singleItemSelected ? this.selectedItems[0] : null,
          itemIds: this.selectedItems,
          newLocation: this.processLocation
        };
        
        console.log('Sending update data:', updateData);
        
        const response = await axios.post(`${API_BASE_URL}/api/stockroom/update-location`, updateData, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
          }
        });
        
        if (response.data.success) {
          const itemCount = this.selectedItems.length;
          const itemText = itemCount === 1 ? 'item' : 'items';
          alert(`Location updated successfully for ${itemCount} ${itemText}`);
          
          this.closeProcessModal();
          this.inventoryCache.clear(); // Clear cache after update
          this.fetchInventory(false);
        } else {
          alert(`Error: ${response.data.message || 'Failed to update location'}`);
        }
      } catch (error) {
        console.error('Error updating location:', error);
        if (error.response && error.response.data) {
          console.error('Server response:', error.response.data);
          alert(`Failed to update location: ${error.response.data.message || 'Unknown error'}`);
        } else {
          alert('Failed to update location. Please try again.');
        }
      } finally {
        this.isProcessing = false;
      }
    },
    
    // Print selected items
    printSelectedItems() {
      if (!this.hasSelectedItems) {
        alert('Please select at least one item to print.');
        return;
      }
      
      this.selectedItems.forEach(itemId => {
        this.printLabel(itemId);
      });
      
      alert(`Printing ${this.selectedItems.length} labels...`);
    },
    
    // Validate serial number
    validateSerialNumber() {
      const serial = this.serialNumber.trim();
      
      if (!serial) return true;
      
      const validFormat = /^[a-zA-Z0-9]+$/.test(serial);
      const containsX00 = serial.includes('X00');
      
      return validFormat && !containsX00;
    },
    
    // Validate FNSKU and check if it's a location code
    validateFnsku() {
      const fnsku = this.fnsku.trim();
      
      if (!fnsku) return true;
      
      const isLocation = /^L\d{3}[A-G]$/i.test(fnsku);
      
      return !isLocation;
    },
    
    // Check FNSKU availability
    async checkFnskuAvailability() {
      const fnsku = this.fnsku.trim();
      
      if (!fnsku || /^L\d{3}[A-G]$/i.test(fnsku)) {
        this.fnskuValid = false;
        return false;
      }
      
      try {
        this.fnskuChecking = true;
        
        const response = await axios.get(`${API_BASE_URL}/api/stockroom/check-fnsku`, {
          params: { fnsku: fnsku }
        });
        
        this.fnskuChecking = false;
        
        if (response.data.exists && response.data.status === 'available') {
          this.fnskuValid = true;
          this.fnskuStatus = 'available';
          return true;
        } else {
          this.fnskuValid = false;
          this.fnskuStatus = response.data.exists ? response.data.status : 'not_found';
          return false;
        }
      } catch (error) {
        console.error('Error checking FNSKU:', error);
        this.fnskuChecking = false;
        this.fnskuValid = false;
        this.fnskuStatus = 'error';
        return false;
      }
    },
    
    // Input field handlers with sound
    async handleSerialInput() {
      const isValid = this.validateSerialNumber();
      
      if (!isValid) {
        this.$refs.scanner.showScanError("Invalid Serial Number - must be alphanumeric and not contain X00");
        this.$refs.serialNumberInput.select();
        SoundService.error();
        return;
      }
      
      if (!this.showManualInput && this.serialNumber.trim().length > 5) {
        if (this.autoVerifyTimeout) {
          clearTimeout(this.autoVerifyTimeout);
        }
        
        this.autoVerifyTimeout = setTimeout(() => {
          SoundService.success();
          this.focusNextField('fnskuInput');
        }, 500);
      }
    },
    
    async handleFnskuInput() {
      const isValid = this.validateFnsku();
      
      if (!isValid) {
        this.$refs.scanner.showScanError("This appears to be a location code. Please enter it in the Location field.");
        this.$refs.fnskuInput.select();
        SoundService.error();
        return;
      }
      
      if (!this.showManualInput && this.fnsku.trim().length > 5) {
        if (this.autoVerifyTimeout) {
          clearTimeout(this.autoVerifyTimeout);
        }
        
        this.autoVerifyTimeout = setTimeout(async () => {
          const isAvailable = await this.checkFnskuAvailability();
          
          if (isAvailable) {
            SoundService.success();
            this.focusNextField('locationInput');
          } else {
            let errorMessage = "Unknown FNSKU status";
            
            switch (this.fnskuStatus) {
              case 'not_found':
                errorMessage = "FNSKU not found in database";
                break;
              case 'unavailable':
                errorMessage = "FNSKU exists but is not available";
                break;
              case 'error':
                errorMessage = "Error checking FNSKU status";
                break;
            }
            
            this.$refs.scanner.showScanError(errorMessage);
            SoundService.error();
          }
        }, 500);
      }
    },
    
    // Fixed handleLocationInput method
    handleLocationInput() {
      if (!this.showManualInput) {
        const locationRegex = /^L\d{3}[A-G]$/i;
        const isValid = locationRegex.test(this.locationInput.trim()) || 
                      this.locationInput.trim() === 'Floor' || 
                      this.locationInput.trim() === 'L800G';
        
        if (!isValid && this.locationInput.trim() !== '') {
          this.$refs.scanner.showScanError("Invalid Location Format (use L###X, Floor, or L800G)");
          this.$refs.locationInput.select();
          SoundService.error();
          return;
        }
        
        if (isValid && this.locationInput.trim().length > 0) {
          if (this.autoVerifyTimeout) {
            clearTimeout(this.autoVerifyTimeout);
          }
          
          this.autoVerifyTimeout = setTimeout(() => {
            SoundService.success();
            this.processScan();
          }, 500);
        }
      }
    },
    
    // Focus the next input field
    focusNextField(fieldRef) {
      this.$nextTick(() => {
        const nextField = this.$refs[fieldRef];
        if (nextField) {
          nextField.focus();
        }
      });
    },
    
    // Process scan with validation
    async processScan(scannedCode = null) {
      try {
        let scanSerial, scanFnsku, scanLocation;
        
        if (scannedCode) {
          scanSerial = '';
          scanFnsku = scannedCode;
          scanLocation = this.locationInput || '';
        } else {
          scanSerial = this.serialNumber;
          scanFnsku = this.fnsku;
          scanLocation = this.locationInput;
          
          if (!scanFnsku && !scanSerial) {
            this.$refs.scanner.showScanError("Serial Number or FNSKU is required");
            SoundService.error();
            this.focusNextField('serialNumberInput');
            return;
          }
        }
        
        if (scanSerial && (!(/^[a-zA-Z0-9]+$/.test(scanSerial)) || scanSerial.includes('X00'))) {
          this.$refs.scanner.showScanError("Invalid Serial Number - must be alphanumeric and not contain X00");
          SoundService.error();
          return;
        }
        
        if (scanFnsku && /^L\d{3}[A-G]$/i.test(scanFnsku)) {
          this.$refs.scanner.showScanError("FNSKU appears to be a location. Please enter it in the Location field.");
          SoundService.error();
          return;
        }
        
        const locationRegex = /^L\d{3}[A-G]$/i;
        if (scanLocation && !locationRegex.test(scanLocation) && scanLocation !== 'Floor' && scanLocation !== 'L800G') {
          this.$refs.scanner.showScanError("Invalid Location Format (use L###X, Floor, or L800G)");
          SoundService.error();
          return;
        }
        
        const imageData = this.$refs.scanner.capturedImages.map(img => img.data);
        
        const scanData = {
          SerialNumber: scanSerial, 
          FNSKU: scanFnsku,
          Location: scanLocation,
          Images: imageData
        };
        
        this.$refs.scanner.startLoading('Processing Scan');
        
        const response = await axios.post('/api/stockroom/process-scan', scanData, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
          }
        });
        
        this.$refs.scanner.stopLoading();
        
        const data = response.data;
        
        if (data.success) {
          this.$refs.scanner.showScanSuccess(data.item || 'Item scanned successfully');
          SoundService.successScan(true);
          
          this.$refs.scanner.addSuccessScan({
            Serial: scanSerial,
            FNSKU: scanFnsku,
            Location: scanLocation
          });
          
          this.$refs.scanner.capturedImages = [];
          
          if (data.needReprint && data.productId) {
            if (confirm("Different FNSKU found in the database. Do you want to reprint the label?")) {
              this.printLabel(data.productId);
            }
          }
        } else {
          this.$refs.scanner.showScanError(data.message || 'Error processing scan');
          SoundService.scanRejected(true);
          
          this.$refs.scanner.addErrorScan({
            Serial: scanSerial,
            FNSKU: scanFnsku,
            Location: scanLocation
          }, data.reason || 'error');
          
          this.$refs.scanner.capturedImages = [];
        }
        
        this.serialNumber = '';
        this.fnsku = '';
        this.locationInput = '';
        this.focusNextField('serialNumberInput');
        
      } catch (error) {
        this.$refs.scanner.stopLoading();
        
        console.error('Error processing scan:', error);
        this.$refs.scanner.showScanError('Network or server error');
        SoundService.scanRejected(true);
        
        this.$refs.scanner.addErrorScan({
          Serial: this.serialNumber || '',
          FNSKU: this.fnsku || '',
          Location: this.locationInput || ''
        }, 'network_error');
      }
    },

    // Updated mergeSelectedItems function with correct API URL format
    async mergeSelectedItems() {
      if (this.selectedItems.length < 2) {
        alert('Please select at least two items to merge.');
        return;
      }
      
      let productTitle = '';
      let productAsin = '';
      let productStore = '';
      let selectedSerials = [];
      let selectedFnsku = '';
      
      if (this.currentProcessItem) {
        productTitle = this.currentProcessItem.AStitle || '';
        productAsin = this.currentProcessItem.ASIN || '';
        productStore = this.currentProcessItem.storename || '';
        
        console.log("Using process modal title:", productTitle);
        
        selectedSerials = this.currentProcessItem.serials
          .filter(serial => this.selectedItems.includes(serial.ProductID))
          .map(serial => serial.serialnumber);
          
        if (this.currentProcessItem.fnskus && this.currentProcessItem.fnskus.length > 0) {
          selectedFnsku = this.currentProcessItem.fnskus[0].FNSKU || this.currentProcessItem.fnskus[0];
          console.log("Using FNSKU from current process item:", selectedFnsku);
        }
      } else {
        const firstSelectedId = this.selectedItems[0];
        for (const item of this.inventory) {
          if (item.serials && item.serials.some(serial => serial.ProductID === firstSelectedId)) {
            productTitle = item.AStitle || '';
            productAsin = item.ASIN || '';
            productStore = item.storename || '';
            
            if (item.fnskus && item.fnskus.length > 0) {
              selectedFnsku = item.fnskus[0].FNSKU || item.fnskus[0];
              console.log("Found FNSKU from inventory:", selectedFnsku);
            }
            
            console.log("Found title from inventory:", productTitle);
            break;
          }
        }
        
        for (const id of this.selectedItems) {
          for (const item of this.inventory) {
            if (item.serials) {
              const serial = item.serials.find(s => s.ProductID === id);
              if (serial) {
                selectedSerials.push(serial.serialnumber);
              }
            }
          }
        }
      }
      
      if (!productTitle) {
        alert('Could not determine product title for merging.');
        return;
      }

      console.log("Final title being sent:", productTitle);
      console.log("Number of items being merged:", this.selectedItems.length);
      console.log("FNSKU being sent:", selectedFnsku);
      
      if (confirm(`Are you sure you want to merge ${this.selectedItems.length} items of "${productTitle}"?`)) {
        try {
          this.isProcessing = true;
          
          const mergeData = {
            items: this.selectedItems,
            title: productTitle,
            asin: productAsin,
            store: productStore,
            serialNumbers: selectedSerials,
            fnsku: selectedFnsku
          };
          
          console.log("Sending merge data:", mergeData);
          
          const response = await axios.post(`${API_BASE_URL}/api/stockroom/merge-items`, mergeData, {
            withCredentials: true,
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            }
          });
          
          if (response.data.success) {
            const newRtNumber = response.data.newrt;
            const productId = response.data.productid;
            const mergedTitle = response.data.title || productTitle;
            const mergedFnsku = response.data.fnsku || selectedFnsku;
            
            let storeNameForRt = response.data.store || productStore;
            const formattedRt = this.formatRTNumber(newRtNumber, storeNameForRt);
            
            alert(`Items successfully merged into new item ${formattedRt}: ${mergedTitle}${mergedFnsku ? ` (FNSKU: ${mergedFnsku})` : ''}`);
            
            if (confirm('Do you want to print a label for the newly created item?')) {
              await this.printLabel(productId);
            }
            
            this.closeProcessModal();
            this.inventoryCache.clear(); // Clear cache after merge
            this.fetchInventory(false);
          } else {
            alert(`Error: ${response.data.message || 'Failed to merge items'}`);
          }
        } catch (error) {
          console.error('Error merging items:', error);
          alert('Failed to merge items. Please try again.');
        } finally {
          this.isProcessing = false;
        }
      }
    },
    
    // Print label method
    async printLabel(productId) {
      try {
        const response = await axios.post('/api/stockroom/print-label', {
          productId: productId
        }, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
          }
        });
        
        if (response.data.status === 'success') {
          alert('Label printing started.');
        } else {
          alert('Error: ' + response.data.message);
        }
      } catch (error) {
        console.error('Error printing label:', error);
        alert('Failed to print label. Please try again.');
      }
    },
    
    // Scanner event handlers
    handleScanProcess() {
      this.processScan();
    },
    
    handleHardwareScan(scannedCode) {
      this.processScan(scannedCode);
    },
    
    handleModeChange(event) {
      this.showManualInput = event.manual;
    },
    
    handleScannerOpened() {
      this.showManualInput = this.$refs.scanner.showManualInput;
      
      this.serialNumber = '';
      this.fnsku = '';
      this.locationInput = '';
      this.fnskuValid = false;
      this.fnskuStatus = '';
      
      this.$nextTick(() => {
        if (this.$refs.serialNumberInput) {
          this.$refs.serialNumberInput.focus();
        }
      });
    },
    
    handleScannerClosed() {
      this.inventoryCache.clear(); // Clear cache when scanner closes
      this.fetchInventory(false);
    },
    
    handleScannerReset() {
      this.serialNumber = '';
      this.fnsku = '';
      this.locationInput = '';
      this.fnskuValid = false;
      this.fnskuStatus = '';
    },
    
    // Methods for handling responsiveness
    handleResize() {
      if (this.isMobile) {
        const hasOpenDropdowns = Object.values(this.serialDropdowns).some(isOpen => isOpen);
        if (hasOpenDropdowns) {
          this.serialDropdowns = {};
        }
      }
    },
    
    closeDropdownsOnClickOutside(event) {
      const isOutside = !event.target.closest('.serial-dropdown');
      if (isOutside) {
        this.serialDropdowns = {};
      }
    },
    
    // Preload next page for better UX
    preloadNextPage() {
      if (this.currentPage < this.totalPages && !this.isLoading) {
        const nextPageKey = `${this.searchQuery}_${this.currentPage + 1}_${this.perPage}_${this.selectedStore}`;
        
        if (!this.inventoryCache.has(nextPageKey)) {
          const originalPage = this.currentPage;
          this.currentPage = originalPage + 1;
          
          this.performFetch(nextPageKey).then(() => {
            this.currentPage = originalPage;
          });
        }
      }
    },
    
    // Setup lazy loading for images
    setupLazyLoading() {
      this.$nextTick(() => {
        const images = document.querySelectorAll('.product-thumbnail, .product-thumbnail-mobile');
        
        if ('IntersectionObserver' in window) {
          const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
              if (entry.isIntersecting) {
                const img = entry.target;
                if (img.dataset.src) {
                  img.src = img.dataset.src;
                  img.removeAttribute('data-src');
                  imageObserver.unobserve(img);
                }
              }
            });
          });
          
          images.forEach(img => {
            if (img.dataset.src) {
              imageObserver.observe(img);
            }
          });
        }
      });
    }
  },
  
  watch: {
    searchQuery() {
      this.handleSearchChange();
    },
    // Watch for changes to selectedItems to update location field
    selectedItems(newValue) {
      if (newValue.length === 1 && this.currentProcessItem && this.currentProcessItem.serials) {
        const selectedSerial = this.currentProcessItem.serials.find(serial => serial.ProductID === newValue[0]);
        if (selectedSerial) {
          this.processLocation = selectedSerial.warehouselocation || '';
        }
      } else if (newValue.length > 1) {
        this.processLocation = '';
      }
    }
  },
  
  mounted() {
    // Configure axios
    axios.defaults.baseURL = window.location.origin;
    axios.defaults.withCredentials = true;
    
    // Set CSRF token
    const token = document.querySelector('meta[name="csrf-token"]');
    if (token) {
      axios.defaults.headers.common['X-CSRF-TOKEN'] = token.getAttribute('content');
    }
    
    // Add Font Awesome if not already included
    if (!document.querySelector('link[href*="font-awesome"]')) {
      const fontAwesome = document.createElement('link');
      fontAwesome.rel = 'stylesheet';
      fontAwesome.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css';
      document.head.appendChild(fontAwesome);
    }
    
    // Set the default image to our SVG
    this.defaultImagePath = this.createDefaultImageSVG();
    
    // Setup performance monitoring
    if (window.performance && window.performance.mark) {
      window.performance.mark('stockroom-module-start');
    }
    
    // Fetch stores for dropdown
    this.fetchStores();
    
    // Fetch initial data
    this.fetchInventory();
    
    // Setup lazy loading
    this.setupLazyLoading();
    
    // Listen for window resize to update isMobile
    window.addEventListener('resize', this.handleResize);
    
    // Initialize serialDropdowns
    this.inventory.forEach((_, index) => {
      this.$set(this.serialDropdowns, index, false);
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', this.closeDropdownsOnClickOutside);
    
    // Preload next page after initial load
    setTimeout(() => {
      this.preloadNextPage();
    }, 2000);
  },
  
  beforeUnmount() {
    // Clean up any timeouts
    if (this.autoVerifyTimeout) {
      clearTimeout(this.autoVerifyTimeout);
    }
    if (this.searchDebounceTimer) {
      clearTimeout(this.searchDebounceTimer);
    }
    if (this.fetchDebounceTimer) {
      clearTimeout(this.fetchDebounceTimer);
    }
    
    window.removeEventListener('resize', this.handleResize);
    document.removeEventListener('click', this.closeDropdownsOnClickOutside);
    
    // Clear cache
    this.inventoryCache.clear();
  }
}