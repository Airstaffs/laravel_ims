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
      perPage: 10, // Default rows per page
      selectAll: false,
      expandedRows: {},
      serialDropdowns: {}, // Added for serial number dropdowns
      sortColumn: "",
      sortOrder: "asc",
      showDetails: false,
      
      // Store filter
      stores: [],
      selectedStore: '',
      
      // Scanner data
      serialNumber: '',
      fnsku: '',
      locationInput: '',
      showManualInput: false, // Will be set from scanner component
      
      // For auto verification
      autoVerifyTimeout: null,
      
      // For FNSKU validation
        fnskuValid: false,
        fnskuChecking: false,
        fnskuStatus: '',
        fnskuNormalized: false,
      
      // For process modal (replaces move modal)
      showProcessModal: false,
      processShipmentType: 'For Dispense',
      processTrackingNumber: '',
      processNotes: '',
      processLocation: '',    
      currentProcessItem: null,
      currentProductId: null, // Added to store the product ID
      currentProductAsin: null, // Added to store the ASIN
      currentProductTitle: '', // Added to store the product title
      selectedItems: [],
      selectAllItems: false,
      isProcessing: false,
      
      // For product details modal
      showProductDetailsModal: false,
      selectedProduct: null,
      enlargeImage: false, // For toggling enlarged image view
      
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
    // Add a computed property to detect mobile
    isMobile() {
      return window.innerWidth <= 768;
    },
    // Check if only one item is selected
    singleItemSelected() {
      return this.selectedItems.length === 1;
    },
    // Check if any items are selected
    hasSelectedItems() {
      return this.selectedItems.length > 0;
    },
    // Check if process form is valid for submission
    isProcessFormValid() {
      // Basic validation for processing - require shipment type and tracking number
      return this.processShipmentType && 
             this.processTrackingNumber && 
             this.selectedItems.length > 0;
    }
  },
  methods: {
    // Function to get the image path based on ASIN
    getImagePath(asin) {
      // Direct path return without checks to prevent blinking
      return asin ? `/images/asinimg/${asin}_0.png` : this.defaultImagePath;
    },
    
    // Simplified image error handling that just swaps to default image
    handleImageError(event, item) {
      // Immediately set the source to default image
      event.target.src = this.defaultImagePath;
      
      // Mark this item to use default image from now on
      if (item) item.useDefaultImage = true;
    },
    
    // Add this method to create an SVG placeholder
    createDefaultImageSVG() {
      return `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100' width='100' height='100'%3E%3Crect width='100' height='100' fill='%23f0f0f0'/%3E%3Cpath d='M35,30L65,30L65,70L35,70Z' fill='%23e0e0e0' stroke='%23bbbbbb' stroke-width='2'/%3E%3Cpath d='M45,40L55,40L55,60L45,60Z' fill='%23d0d0d0' stroke='%23bbbbbb'/%3E%3Cpath d='M35,80L65,80L65,85L35,85Z' fill='%23e0e0e0'/%3E%3C/svg%3E`;
    },
    
    // Format the item count to show pack information
    formatItemCount(item) {
      if (!item) return '0';
      
      // Check if this is a pack item
      if (item.pack_size && item.pack_size > 1) {
        // For pack items, show both the box count and the total units
        return `${item.box_count} boxes (${item.item_count} units)`;
      }
      
      // For regular items, just show the count
      return item.item_count.toString();
    },
    
    // Extract and display pack information
    getPackInfo(item) {
      if (!item || !item.AStitle) return '';
      
      // Check for pack information in the title
      const packMatch = item.AStitle.match(/(\d+)-Pack/i);
      if (packMatch && packMatch[1]) {
        return `${packMatch[1]}-Pack`;
      }
      
      return '';
    },
    
    // Add a separate method for viewing product image
    viewProductImage(item) {
      // Set the selected product and open the modal directly
      this.selectedProduct = item;
      this.showProductDetailsModal = true;
    },
    
    // Regular product details modal
    viewProductDetails(item) {
      const processedItem = this.applyGradeConversion([item])[0];
      this.selectedProduct = processedItem;
      this.showProductDetailsModal = true;
    },
    
    // Close product details modal
    closeProductDetailsModal() {
      this.showProductDetailsModal = false;
      this.selectedProduct = null;
      this.enlargeImage = false; // Reset enlarged state
    },
    
    // Open process modal from product details
    openProcessModalFromDetails(item) {
      this.closeProductDetailsModal();
      this.openProcessModal(item);
    },
    
    // Open scanner modal method - this will call the scanner component's method
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
        // Default format if store doesn't match known patterns
        return `#${paddedCounter}`;
      }
    },

normalizeFnsku(fnsku) {
    if (!fnsku) return fnsku;
    
    const trimmed = fnsku.trim();
    console.log('Normalizing FNSKU:', { original: trimmed, length: trimmed.length });
    
    // If FNSKU is longer than 10 characters, check if it starts with 2 characters (letters or numbers)
    // More flexible pattern to catch cases like "B3X0049KMM09"
    if (trimmed.length > 10 && /^[A-Z0-9]{2}[X0-9]/i.test(trimmed)) {
      const normalized = trimmed.substring(2);
      console.log('FNSKU normalized:', { 
        original: trimmed, 
        normalized: normalized,
        originalLength: trimmed.length,
        normalizedLength: normalized.length
      });
      return normalized;
    }
    
    console.log('FNSKU not normalized - pattern did not match');
    return trimmed;
  },

    
    // Store dropdown functions
    async fetchStores() {
      try {
        const response = await axios.get(`${API_BASE_URL}/api/stockroom/stores`, {
          withCredentials: true
        });
        this.stores = response.data;
      } catch (error) {
        console.error("Error fetching stores:", error);
      }
    },
    
    changeStore() {
      this.currentPage = 1;
      this.fetchInventory();
    },
    
    // Validate the item count against serials
    validateItemCount(item) {
      if (!item) return true;
      
      // If no serials, just return true
      if (!item.serials || item.serials.length === 0) {
        return true;
      }
      
      // For pack items, we need to check if the actual serial count matches the box count
      // rather than the total item count (which includes the multiplication by pack size)
      if (item.pack_size && item.pack_size > 1) {
        const serialCount = item.serials.length;
        return serialCount === item.box_count;
      }
      
      // For regular items, compare serials count with item_count directly
      const serialCount = item.serials.length;
      return serialCount === item.item_count;
    },


    convertItemCondition(itemCondition, storeName, asin = null, originalGrading = null, asinStatus = null) {
  // Normalize store name check
  const isAllrenewed = ['allrenewed', 'all renewed'].includes(storeName?.toLowerCase());
  
  console.log('Converting grade:', { 
    itemCondition, 
    storeName, 
    isAllrenewed, 
    asin, 
    originalGrading,
    asinStatus 
  });
  
  let convertedGrade;
  
  switch (itemCondition) {
    case 'UsedLikeNew':
      convertedGrade = 'Used - Like New';
      break;
      
    case 'UsedVeryGood':
      convertedGrade = isAllrenewed ? 'Refurbished - Excellent' : 'Used - Very Good';
      break;
      
    case 'UsedGood':
      convertedGrade = isAllrenewed ? 'Refurbished - Good' : 'Used - Good';
      break;
      
    case 'UsedAcceptable':
      convertedGrade = isAllrenewed ? 'Refurbished - Acceptable' : 'Used - Acceptable';
      break;
      
    case 'New':
      if (isAllrenewed && asinStatus) {
        // Check ASIN status for Allrenewed store
        if (asinStatus.toLowerCase() === 'renewed') {
          convertedGrade = 'Refurbished - Excellent';
        } else {
          // If ASIN status is not 'renewed', return original grading
          convertedGrade = originalGrading || 'New';
        }
      } else {
        // For non-Allrenewed stores, return original grading
        convertedGrade = originalGrading || 'New';
      }
      break;
      
    default:
      // Handle unexpected condition values
      convertedGrade = originalGrading || itemCondition;
  }
  
  console.log('Grade converted from', itemCondition, 'to', convertedGrade);
  return convertedGrade;
},


  // Enhanced helper method to get display grading for any item
 getDisplayGrading(item, storeName = null, productData = null) {
  if (!item) return '';
  
  const grading = item.grading || item.condition;
  const store = storeName || item.storename || this.selectedStore;
  const asin = item.ASIN || item.asin;
  
  // Get ASIN status from item or product data
  const asinStatus = item.asinStatus || productData?.asinStatus || null;
  
  // If display_grading already exists, use it
  if (item.display_grading) {
    return item.display_grading;
  }
  
  // Otherwise, convert on the fly
  return this.convertItemCondition(grading, store, asin, grading, asinStatus);
},
  getDisplayGrading(item, storeName = null) {
    if (!item) return '';
    
    const grading = item.grading || item.condition;
    const store = storeName || item.storename || this.selectedStore;
    const asin = item.ASIN || item.asin;
    
    return this.convertItemCondition(grading, store, asin, grading);
  },

  // Apply grade conversion to inventory items (call this after fetching data)
  applyGradeConversion(items) {
  console.log('Applying grade conversion to', items.length, 'items');
  
  return items.map(item => {
    console.log('Processing item:', item.ASIN || item.asin, 'asinStatus:', item.asinStatus);
    
    // Convert FNSKUs grades
    if (item.fnskus && item.fnskus.length > 0) {
      item.fnskus = item.fnskus.map(fnsku => {
        const convertedGrade = this.convertItemCondition(
          fnsku.grading, 
          fnsku.storename || item.storename, 
          item.ASIN, 
          fnsku.grading,
          item.asinStatus // Pass asinStatus to conversion
        );
        
        console.log('FNSKU grade converted:', fnsku.grading, '->', convertedGrade);
        
        return {
          ...fnsku,
          display_grading: convertedGrade
        };
      });
    }
    
    // Convert serials grades
    if (item.serials && item.serials.length > 0) {
      item.serials = item.serials.map(serial => {
        const convertedGrade = this.convertItemCondition(
          serial.grading, 
          serial.storename || item.storename, 
          item.ASIN, 
          serial.grading,
          item.asinStatus // Pass asinStatus to conversion
        );
        
        console.log('Serial grade converted:', serial.grading, '->', convertedGrade);
        
        return {
          ...serial,
          display_grading: convertedGrade
        };
      });
    }
    
    return item;
  });
},

    
    // Modified fetchInventory with count validation
    async fetchInventory() {
      try {
        const response = await axios.get(`${API_BASE_URL}/api/stockroom/products`, {
          params: { 
            search: this.searchQuery, 
            page: this.currentPage, 
            per_page: this.perPage,
            store: this.selectedStore
          },
          withCredentials: true
        });

        // Initialize items with checked property and useDefaultImage flag
           let inventoryItems = (response.data.data || []).map(item => {
          const itemWithFlags = {
            ...item,
            checked: false,
            serials: item.serials || [],
            fnskus: item.fnskus || [],
            useDefaultImage: false, // Add this flag
            countValid: true, // Add a flag for item count validation
            
            // Ensure pack_size is set
            pack_size: item.pack_size || 1,
            
            // Ensure box_count is set (for non-pack items, this equals item_count)
            box_count: item.box_count || item.item_count
          };
          
          // Validate the item count
          itemWithFlags.countValid = this.validateItemCount(itemWithFlags);
          
          return itemWithFlags;
        });

        // Apply grade conversion to all items
      this.inventory = this.applyGradeConversion(inventoryItems)
        
        this.totalPages = response.data.last_page || 1;
      } catch (error) {
        console.error("Error fetching inventory data:", error);
        if (SoundService && SoundService.error) {
          SoundService.error();
        }
      }
    },

    // Pagination methods
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

    // Inventory selection methods
    toggleAll() {
      this.inventory.forEach((item) => (item.checked = this.selectAll));
    },
    
    toggleDetails(index) {
      // Create a new object for reactivity
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
    const processedItem = this.applyGradeConversion([item])[0];
    this.currentProcessItem = processedItem;

    this.showProcessModal = true;
    this.processShipmentType = 'For Dispense';
    this.processTrackingNumber = '';
    this.processNotes = '';
    this.processLocation = '';
    this.selectedItems = [];
    this.selectAllItems = false;
    
    // Store the parent product ID (ASIN level) - hidden from UI
    this.currentProductId = item.ProductID || null;
    this.currentProductAsin = item.ASIN || null;
    this.currentProductTitle = item.AStitle || '';
    
    // If the item has just one serial number, pre-select it and show its location
    if (item.serials && item.serials.length === 1) {
      const singleSerial = item.serials[0];
      this.selectedItems = [singleSerial.ProductID];
      this.processLocation = singleSerial.warehouselocation || '';
      
      // Use nextTick to ensure the input is rendered before focusing
      this.$nextTick(() => {
        // Focus and select all text in the location field for easy editing
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
        // Select all items
        this.selectedItems = this.currentProcessItem.serials.map(serial => serial.ProductID);
        
        // Clear location field when multiple items are selected
        if (this.selectedItems.length > 1) {
          this.processLocation = '';
        }
      } else {
        // Deselect all items
        this.selectedItems = [];
        this.processLocation = '';
      }
    },
    
    // Submit the process
    async submitProcess() {
      if (!this.isProcessFormValid) return;
      
      try {
        // Start loading state
        this.isProcessing = true;
        
        // Prepare data for API
        const processData = {
          shipmentType: this.processShipmentType,
          trackingNumber: this.processTrackingNumber,
          notes: this.processNotes,
          items: this.selectedItems
        };
        
        // Send to API
        const response = await axios.post('/api/stockroom/process-items', processData, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
          }
        });
        
        if (response.data.success) {
          // Show success message
          alert(`Successfully processed ${this.selectedItems.length} items`);
          this.closeProcessModal();
          // Refresh inventory
          this.fetchInventory();
        } else {
          // Show error message
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
        // Show loading state
        this.isProcessing = true;
        
        // Prepare update data
        const updateData = {
          itemId: this.singleItemSelected ? this.selectedItems[0] : null, // For backward compatibility
          itemIds: this.selectedItems,
          newLocation: this.processLocation
        };
        
        console.log('Sending update data:', updateData); // Add this for debugging
        
        // Send to API
        const response = await axios.post(`${API_BASE_URL}/api/stockroom/update-location`, updateData, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
          }
        });
        
        if (response.data.success) {
          // Show success message with item count
          const itemCount = this.selectedItems.length;
          const itemText = itemCount === 1 ? 'item' : 'items';
          alert(`Location updated successfully for ${itemCount} ${itemText}`);
          
          this.closeProcessModal();
          // Refresh inventory
          this.fetchInventory();
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
      
      // Skip validation if empty (it's optional)
      if (!serial) return true;
      
      // Check for valid serial format using regex
      const validFormat = /^[a-zA-Z0-9]+$/.test(serial);
      
      // Check if it contains X00
      const containsX00 = serial.includes('X00');
      
      return validFormat && !containsX00;
    },
    
    // Validate FNSKU and check if it's a location code
    validateFnsku() {
      const fnsku = this.fnsku.trim();
      
      // Skip validation if empty (when serial is provided)
      if (!fnsku) return true;
      
      // Check if it matches a location pattern
      const isLocation = /^L\d{3}[A-G]$/i.test(fnsku);
      
      // If it looks like a location code, mark it as invalid for FNSKU field
      return !isLocation;
    },
    
    // Check FNSKU availability
    async checkFnskuAvailability() {
    const fnsku = this.fnsku.trim();
    console.log('checkFnskuAvailability called with:', fnsku);
    
    // Skip check if empty or appears to be a location
    if (!fnsku || /^L\d{3}[A-G]$/i.test(fnsku)) {
      this.fnskuValid = false;
      console.log('FNSKU check skipped - empty or location pattern');
      return false;
    }
    
    try {
      this.fnskuChecking = true;
      
      console.log('Sending API request to check FNSKU:', fnsku);
      
      // Call API to check FNSKU status
      const response = await axios.get(`${API_BASE_URL}/api/stockroom/check-fnsku`, {
        params: { fnsku: fnsku }
      });
      
      this.fnskuChecking = false;
      
      // Log the response for debugging
      console.log('FNSKU check API response:', response.data);
      
      // Update validity based on response
      if (response.data.exists && response.data.status === 'available') {
        this.fnskuValid = true;
        this.fnskuStatus = 'available';
        
        // If FNSKU was normalized, show the normalized version in the input
        if (response.data.normalized_fnsku && response.data.normalized_fnsku !== response.data.original_fnsku) {
          console.log('Server returned different normalized FNSKU:', response.data.normalized_fnsku);
          this.fnsku = response.data.normalized_fnsku;
        }
        
        return true;
      } else {
        this.fnskuValid = false;
        this.fnskuStatus = response.data.exists ? response.data.status : 'not_found';
        
        console.log('FNSKU not available:', {
          exists: response.data.exists,
          status: response.data.status,
          normalized: response.data.normalized_fnsku
        });
        
        // Still update the FNSKU field with normalized version for consistency
        if (response.data.normalized_fnsku && response.data.normalized_fnsku !== response.data.original_fnsku) {
          this.fnsku = response.data.normalized_fnsku;
        }
        
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
      // First validate serial number
      const isValid = this.validateSerialNumber();
      
      if (!isValid) {
        // Show error for invalid serial
        this.$refs.scanner.showScanError("Invalid Serial Number - must be alphanumeric and not contain X00");
        this.$refs.serialNumberInput.select();
        SoundService.error();
        return;
      }
      
      // In auto mode with valid input, play sound and proceed
      if (!this.showManualInput && this.serialNumber.trim().length > 5) {
        if (this.autoVerifyTimeout) {
          clearTimeout(this.autoVerifyTimeout);
        }
        
        this.autoVerifyTimeout = setTimeout(() => {
          // Play success sound
          SoundService.success();
          
          // Focus on next field
          this.focusNextField('fnskuInput');
        }, 500);
      }
    },
    
     async handleFnskuInput() {
    console.log('handleFnskuInput called with:', this.fnsku);
    
    // Normalize the FNSKU and update the input field automatically
    const originalFnsku = this.fnsku;
    const normalizedFnsku = this.normalizeFnsku(originalFnsku);
    
    console.log('Input normalization result:', {
      original: originalFnsku,
      normalized: normalizedFnsku,
      changed: originalFnsku !== normalizedFnsku
    });
    
    // Update the input field to show the normalized FNSKU
    this.fnsku = normalizedFnsku;
    
    // First validate FNSKU
    const isValid = this.validateFnsku();
    
    if (!isValid) {
      // If it looks like a location, show a specific message
      this.$refs.scanner.showScanError("This appears to be a location code. Please enter it in the Location field.");
      this.$refs.fnskuInput.select();
      SoundService.error();
      return;
    }
    
    // In auto mode with valid input, check availability and proceed
    if (!this.showManualInput && this.fnsku.trim().length > 5) {
      if (this.autoVerifyTimeout) {
        clearTimeout(this.autoVerifyTimeout);
      }
      
      this.autoVerifyTimeout = setTimeout(async () => {
        console.log('About to check FNSKU availability for:', this.fnsku);
        
        // Check FNSKU availability
        const isAvailable = await this.checkFnskuAvailability();
        
        if (isAvailable) {
          // Play success sound if FNSKU is valid and available
          SoundService.success();
          
          // Focus on location field
          this.focusNextField('locationInput');
        } else {
          // Show appropriate error message based on status
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
          
          console.error('FNSKU check failed:', { fnsku: this.fnsku, status: this.fnskuStatus });
          this.$refs.scanner.showScanError(errorMessage);
          SoundService.error();
        }
      }, 500);
    }
  },

    
    // Fixed handleLocationInput method
    handleLocationInput() {
      // Only perform validation in auto mode
      if (!this.showManualInput) {
        // Validate location format
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
        
        // Only in auto mode, process scan after valid location input
        if (isValid && this.locationInput.trim().length > 0) {
          if (this.autoVerifyTimeout) {
            clearTimeout(this.autoVerifyTimeout);
          }
          
          this.autoVerifyTimeout = setTimeout(() => {
            // Play success sound for valid location
            SoundService.success();
            
            // Process the scan
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
        // Use either the scanned code or input fields
        let scanSerial, scanFnsku, scanLocation;
        
        if (scannedCode) {
          // External code passed (from hardware scanner)
          scanSerial = '';
          scanFnsku = this.normalizeFnsku(scannedCode);
          this.fnsku = scanFnsku;
          scanLocation = this.locationInput || '';
        } else {
          // Use the input fields
          scanSerial = this.serialNumber;
          scanFnsku = this.fnsku;
          scanLocation = this.locationInput;
          console.log('Scanned code normalized to:', scanFnsku);
          
          // Basic validation - need at least one of serial or FNSKU
          if (!scanFnsku && !scanSerial) {
            this.$refs.scanner.showScanError("Serial Number or FNSKU is required");
            SoundService.error();
            this.focusNextField('serialNumberInput');
            return;
          }
        }
        
        // Validate serial number if provided
        if (scanSerial && (!(/^[a-zA-Z0-9]+$/.test(scanSerial)) || scanSerial.includes('X00'))) {
          this.$refs.scanner.showScanError("Invalid Serial Number - must be alphanumeric and not contain X00");
          SoundService.error();
          return;
        }
        
        // Check if FNSKU is actually a location
        if (scanFnsku && /^L\d{3}[A-G]$/i.test(scanFnsku)) {
          this.$refs.scanner.showScanError("FNSKU appears to be a location. Please enter it in the Location field.");
          SoundService.error();
          return;
        }
        
        // Validate location format - this should happen for both auto and manual mode at submission time
        const locationRegex = /^L\d{3}[A-G]$/i;
        if (scanLocation && !locationRegex.test(scanLocation) && scanLocation !== 'Floor' && scanLocation !== 'L800G') {
          this.$refs.scanner.showScanError("Invalid Location Format (use L###X, Floor, or L800G)");
          SoundService.error();
          return;
        }
        
        // Get images from scanner
        const imageData = this.$refs.scanner.capturedImages.map(img => img.data);
        
        // Send data to server
        const scanData = {
          SerialNumber: scanSerial, 
          FNSKU: scanFnsku,
          Location: scanLocation,
          Images: imageData
        };
        
        // Show loading state
        this.$refs.scanner.startLoading('Processing Scan');
        
        // Send to API
        const response = await axios.post('/api/stockroom/process-scan', scanData, {
          withCredentials: true,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
          }
        });
        
        // Hide loading
        this.$refs.scanner.stopLoading();
        
        const data = response.data;
        
        if (data.success) {
          // Success case
          this.$refs.scanner.showScanSuccess(data.item || 'Item scanned successfully');
          SoundService.successScan(true);
          
          // Add to scan history
          this.$refs.scanner.addSuccessScan({
            Serial: scanSerial,
            FNSKU: scanFnsku,
            Location: scanLocation
          });
          
          // Clear images
          this.$refs.scanner.capturedImages = [];
          
          // Check if we need to handle reprint
          if (data.needReprint && data.productId) {
            // Confirm reprint label
            if (confirm("Different FNSKU found in the database. Do you want to reprint the label?")) {
              this.printLabel(data.productId);
            }
          }
        } else {
          // Error case
          this.$refs.scanner.showScanError(data.message || 'Error processing scan');
          SoundService.scanRejected(true);
          
          // Add to error scan history
          this.$refs.scanner.addErrorScan({
            Serial: scanSerial,
            FNSKU: scanFnsku,
            Location: scanLocation
          }, data.reason || 'error');
          
          // Clear images
          this.$refs.scanner.capturedImages = [];
        }
        
        // Clear input fields and focus first field
        this.serialNumber = '';
        this.fnsku = '';
        this.locationInput = '';
        this.focusNextField('serialNumberInput');
        
      } catch (error) {
        // Hide loading
        this.$refs.scanner.stopLoading();
        
        console.error('Error processing scan:', error);
        this.$refs.scanner.showScanError('Network or server error');
        SoundService.scanRejected(true);
        
        // Add failed scan to history
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
  
  // When merging from process modal, we know all items belong to the same product
  // Make sure to get the title directly from the modal item
  let productTitle = '';
  let productAsin = '';
  let productStore = '';
  let selectedSerials = [];
  let selectedFnsku = ''; // Add variable for FNSKU
  
  if (this.currentProcessItem) {
    // We're in the process modal, so use the title from the current process item
    productTitle = this.currentProcessItem.AStitle || '';
    productAsin = this.currentProcessItem.ASIN || '';
    productStore = this.currentProcessItem.storename || '';
    
    console.log("Using process modal title:", productTitle);
    
    // Get just the serial numbers of selected items
    selectedSerials = this.currentProcessItem.serials
      .filter(serial => this.selectedItems.includes(serial.ProductID))
      .map(serial => serial.serialnumber);
      
    // Get the first available FNSKU for this product if any
   if (this.currentProcessItem.fnskus && this.currentProcessItem.fnskus.length > 0) {
        let rawFnsku = this.currentProcessItem.fnskus[0].FNSKU || this.currentProcessItem.fnskus[0];
        selectedFnsku = this.normalizeFnsku(rawFnsku);
        console.log("Using normalized FNSKU from current process item:", selectedFnsku);
      }
  } else {
    // If not in process modal, find the product information
    const firstSelectedId = this.selectedItems[0];
    for (const item of this.inventory) {
      if (item.serials && item.serials.some(serial => serial.ProductID === firstSelectedId)) {
        productTitle = item.AStitle || '';
        productAsin = item.ASIN || '';
        productStore = item.storename || '';
        
        // Get the first available FNSKU for this product if any
       if (item.fnskus && item.fnskus.length > 0) {
        let rawFnsku = item.fnskus[0].FNSKU || item.fnskus[0];
        selectedFnsku = this.normalizeFnsku(rawFnsku);
        console.log("Found normalized FNSKU from inventory:", selectedFnsku);
      }
        
        console.log("Found title from inventory:", productTitle);
        break;
      }
    }
    
    // Get serial numbers from the inventory
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
      // Start loading state
      this.isProcessing = true;
      
      // Prepare merge data with all required information
      const mergeData = {
        items: this.selectedItems,
        title: productTitle,
        asin: productAsin,
        store: productStore,
        serialNumbers: selectedSerials,
        fnsku: selectedFnsku // Add FNSKU to merge data
      };
      
      console.log("Sending merge data:", mergeData);
      
      // Send to API using the correct API_BASE_URL format
      const response = await axios.post(`${API_BASE_URL}/api/stockroom/merge-items`, mergeData, {
        withCredentials: true,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
        }
      });
      
      if (response.data.success) {
        // Show success message with new RT number
        const newRtNumber = response.data.newrt;
        const productId = response.data.productid;
        const mergedTitle = response.data.title || productTitle;
        const mergedFnsku = response.data.fnsku || selectedFnsku;
        
        // Format the RT number based on the store
        let storeNameForRt = response.data.store || productStore;
        const formattedRt = this.formatRTNumber(newRtNumber, storeNameForRt);
        
        // Show success alert with details
        alert(`Items successfully merged into new item ${formattedRt}: ${mergedTitle}${mergedFnsku ? ` (FNSKU: ${mergedFnsku})` : ''}`);
        
        // Ask if user wants to print the new label
        if (confirm('Do you want to print a label for the newly created item?')) {
          await this.printLabel(productId);
        }
        
        this.closeProcessModal();
        // Refresh inventory
        this.fetchInventory();
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
    // Check if the scanned code looks like an FNSKU
    if (scannedCode && /^[A-Z0-9]{10,}$/i.test(scannedCode)) {
      // If it's an FNSKU, normalize it and put it in the FNSKU field
      const normalizedFnsku = this.normalizeFnsku(scannedCode);
      this.fnsku = normalizedFnsku;
      
      // Focus on the location field next
      this.$nextTick(() => {
        this.focusNextField('locationInput');
      });
    } else {
      // For other codes, process the scan normally
      this.processScan(scannedCode);
    }
  },
    
    handleModeChange(event) {
      this.showManualInput = event.manual;
    },
    
    handleScannerOpened() {
      // Get current mode from scanner component
      this.showManualInput = this.$refs.scanner.showManualInput;
      
      // Reset fields
      this.serialNumber = '';
      this.fnsku = '';
      this.locationInput = '';
      this.fnskuValid = false;
      this.fnskuStatus = '';
      
      // Focus on first field
      this.$nextTick(() => {
        if (this.$refs.serialNumberInput) {
          this.$refs.serialNumberInput.focus();
        }
      });
    },
    
    handleScannerClosed() {
      // Refresh inventory when scanner is closed
      this.fetchInventory();
    },
    
    handleScannerReset() {
      // Reset fields when scanner is reset
      this.serialNumber = '';
      this.fnsku = '';
      this.locationInput = '';
      this.fnskuValid = false;
      this.fnskuStatus = '';
    },
    
    // Methods for handling responsiveness
    handleResize() {
      // If we're on mobile and dropdowns are open, we might want to close them
      if (this.isMobile) {
        const hasOpenDropdowns = Object.values(this.serialDropdowns).some(isOpen => isOpen);
        if (hasOpenDropdowns) {
          this.serialDropdowns = {};
        }
      }
    },
    
    closeDropdownsOnClickOutside(event) {
      // Check if click is outside any dropdown
      const isOutside = !event.target.closest('.serial-dropdown');
      if (isOutside) {
        this.serialDropdowns = {};
      }
    }
  },
  watch: {
  searchQuery() {
    this.currentPage = 1;
    this.fetchInventory();
  },
  // Watch for changes to selectedItems to update location field
  selectedItems(newValue) {
    // If exactly one item is selected, try to get its current location
    if (newValue.length === 1 && this.currentProcessItem && this.currentProcessItem.serials) {
      const selectedSerial = this.currentProcessItem.serials.find(serial => serial.ProductID === newValue[0]);
      if (selectedSerial) {
        this.processLocation = selectedSerial.warehouselocation || '';
      }
    } else if (newValue.length > 1) {
      // Clear location when multiple items are selected
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
    
    // Fetch stores for dropdown
    this.fetchStores();
    
    // Fetch initial data
    this.fetchInventory();
    
    // Listen for window resize to update isMobile
    window.addEventListener('resize', this.handleResize);
    
    // Initialize serialDropdowns
    this.inventory.forEach((_, index) => {
      this.$set(this.serialDropdowns, index, false);
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', this.closeDropdownsOnClickOutside);
  },
  beforeUnmount() {
    // Clean up any timeouts
    if (this.autoVerifyTimeout) {
      clearTimeout(this.autoVerifyTimeout);
    }
    
    window.removeEventListener('resize', this.handleResize);
    document.removeEventListener('click', this.closeDropdownsOnClickOutside);
  }
}