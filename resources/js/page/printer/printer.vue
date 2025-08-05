<template>
  <div class="printer-container">
    <!-- Use your existing scanner component -->
    <ScannerComponent
      ref="scannerComponent"
      :hideButton="true"
      :enableCamera="false"
      :scannerTitle="'Label Printer'"
      :storagePrefix="'printer'"
      :displayFields="['serial_number', 'status']"
      :initialMode="'auto'"
      @scanner-opened="onScannerOpened"
      @scanner-closed="onScannerClosed"
      @process-scan="processPrintScan"
      @mode-changed="onModeChanged"
    >
      <!-- Input fields slot -->
      <template #input-fields>
        <div class="printer-input-section">
          <!-- Tab Navigation -->
          <div class="tab-navigation">
            <button 
              @click="activeTab = 'print'"
              :class="['tab-button', { active: activeTab === 'print' }]"
            >
              <i class="fas fa-print"></i> Print All Labels
            </button>
            <button 
              @click="activeTab = 'reprint'"
              :class="['tab-button', { active: activeTab === 'reprint' }]"
            >
              <i class="fas fa-redo"></i> Reprint Single Label
            </button>
          </div>

          <!-- Print All Labels Tab -->
          <div v-if="activeTab === 'print'" class="tab-content">
            <!-- Printer Selection Dropdown -->
            <div class="input-group">
              <label for="printerSelect">Select Printer</label>
              <select 
                id="printerSelect"
                v-model="selectedPrinter"
                @change="onPrinterChange"
                :disabled="isProcessing || loadingPrinters"
                class="printer-select"
              >
                <option :value="null" disabled>
                  {{ loadingPrinters ? 'Loading printers...' : 'Choose a printer' }}
                </option>
                <option 
                  v-for="printer in printers" 
                  :key="printer.printerid" 
                  :value="parseInt(printer.printerid)"
                >
                  {{ printer.printername }}
                </option>
              </select>
            </div>
            
              <div class="input-group">
              <label for="printerSerial">Serial Number / PCN / RT Counter</label>
              <input 
                type="text" 
                id="printerSerial"
                v-model="serialNumber"
                placeholder="Scan or enter serial number, PCN, or RT counter (e.g., RT12345)"
                @keyup.enter="processPrintScan"
                @input="onSerialInput"
                :disabled="isProcessing || !selectedPrinter"
                ref="serialInput"
              >
            </div>
            
            <!-- Manual mode submit button -->
            <button 
              v-if="isManualMode" 
              @click="processPrintScan" 
              class="submit-button"
              :disabled="!serialNumber || isProcessing || !selectedPrinter"
            >
              <i class="fas fa-print"></i> Print All Labels
            </button>
          </div>

          <!-- Reprint Single Label Tab -->
          <div v-if="activeTab === 'reprint'" class="tab-content">
            <!-- Printer Selection for Reprint -->
            <div class="input-group">
              <label for="reprintPrinterSelect">Select Printer</label>
              <select 
                id="reprintPrinterSelect"
                v-model="reprintSelectedPrinter"
                @change="onReprintPrinterChange"
                :disabled="isProcessing || loadingPrinters"
                class="printer-select"
              >
                <option :value="null" disabled>
                  {{ loadingPrinters ? 'Loading printers...' : 'Choose a printer' }}
                </option>
                <option 
                  v-for="printer in printers" 
                  :key="printer.printerid" 
                  :value="parseInt(printer.printerid)"
                >
                  {{ printer.printername }}
                </option>
              </select>
            </div>

            <!-- Search Input -->
            <div class="input-group">
              <label for="reprintSearch">Serial Number / PCN / RT Counter</label>
              <input 
                type="text" 
                id="reprintSearch"
                v-model="reprintSearchTerm"
                placeholder="Scan or enter serial number, PCN, or RT counter (e.g., RT12345)"
                @keyup.enter="searchForReprint"
                @input="onReprintSearchInput"
                :disabled="isProcessing || !reprintSelectedPrinter"
                ref="reprintSearchInput"
              >
            </div>

            <!-- Label Type Selection -->
            <div class="input-group" v-if="availableLabelTypes.length > 0">
              <label for="labelTypeSelect">Select Label Type to Print</label>
              <select 
                id="labelTypeSelect"
                v-model="selectedLabelType"
                :disabled="isProcessing"
                class="label-type-select"
              >
                <option :value="null" disabled>Choose a label type</option>
                <option 
                  v-for="labelType in availableLabelTypes" 
                  :key="labelType.key" 
                  :value="labelType.key"
                >
                  {{ labelType.name }} {{ labelType.description ? `- ${labelType.description}` : '' }}
                </option>
              </select>
            </div>

            <!-- Product Info Display -->
            <div v-if="reprintProductInfo" class="product-info-card">
              <h4><i class="fas fa-info-circle"></i> Product Information</h4>
              <div class="info-grid">
                <div class="info-item">
                  <span class="label">Title:</span>
                  <span class="value">{{ reprintProductInfo.AStitle || 'N/A' }}</span>
                </div>
                <div class="info-item">
                  <span class="label">ASIN:</span>
                  <span class="value">{{ reprintProductInfo.ASINviewer || 'N/A' }}</span>
                </div>
                <div class="info-item">
                  <span class="label">FNSKU:</span>
                  <span class="value">{{ reprintProductInfo.FNSKUviewer || 'N/A' }}</span>
                </div>
                <div class="info-item">
                  <span class="label">RT Counter:</span>
                  <span class="value">{{ reprintProductInfo.rtcounter || 'N/A' }}</span>
                </div>
                <div class="info-item">
                  <span class="label">Serial Numbers:</span>
                  <span class="value">
                    {{ getSerialNumbers(reprintProductInfo) }}
                  </span>
                </div>
                <div class="info-item">
                  <span class="label">Current Print Count:</span>
                  <span class="value">{{ reprintProductInfo.printCount || 0 }}</span>
                </div>
              </div>
            </div>

            <!-- Reprint Button -->
            <button 
              @click="processReprint" 
              class="submit-button reprint-button"
              :disabled="!selectedLabelType || isProcessing || !reprintSelectedPrinter || !reprintProductInfo"
            >
              <i class="fas fa-redo"></i> Reprint Selected Label
            </button>
          </div>
        </div>
      </template>
    </ScannerComponent>
  </div>
</template>

<script>
// Fix the import paths - use relative paths from the printer component location
import ScannerComponent from '../../components/Scanner.vue';
import { SoundService } from '../../components/Sound_service.js';

export default {
  name: 'PrinterModule',
  components: {
    ScannerComponent
  },
  computed: {
    selectedPrinterName() {
      if (!this.selectedPrinter) return '';
      const printer = this.printers.find(p => p.printerid == this.selectedPrinter);
      return printer ? printer.printername : '';
    },
    reprintSelectedPrinterName() {
      if (!this.reprintSelectedPrinter) return '';
      const printer = this.printers.find(p => p.printerid == this.reprintSelectedPrinter);
      return printer ? printer.printername : '';
    }
  },
  emits: ['close-modal'], // Declare the emit for Vue 3
  data() {
    return {
      // Existing data
      serialNumber: '',
      isProcessing: false,
      isManualMode: false,
      selectedPrinter: null,
      printers: [],
      loadingPrinters: false,
      
      // New reprint data
      activeTab: 'print', // 'print' or 'reprint'
      reprintSelectedPrinter: null,
      reprintSearchTerm: '',
      reprintProductInfo: null,
      selectedLabelType: null,
      availableLabelTypes: [
        { key: 'serial_labels', name: 'Serial Number Labels', description: 'All serial number labels (A, B, C, D)' },
        { key: 'fnsku_label', name: 'FNSKU Label', description: 'Main FNSKU barcode label' },
        { key: 'title_label', name: 'Title Label', description: 'Product title with RT/AR package number' },
        { key: 'item_number_label', name: 'Item Number Label', description: 'Item number with barcode' },
        { key: 'timestamp_label', name: 'Timestamp Label', description: 'Priority and timestamp information' },
        { key: 'sticker_note_label', name: 'Sticker Note Label', description: 'Custom notes and comments' },
        { key: 'warehouse_location_label', name: 'Warehouse Location Label', description: 'Storage location information' },
        { key: 'rtcounter_label', name: 'RT/AR Counter Label', description: 'RT/AR counter with barcode' },
        { key: 'qr_manual', name: 'QR Code - Manual', description: 'QR code for user manual' },
        { key: 'qr_serial', name: 'QR Code - Serial Photos', description: 'QR code for serial photos' },
        { key: 'vector_image', name: 'Vector Image', description: 'Product vector image' },
        { key: 'instruction_cards', name: 'Instruction Cards', description: 'All instruction cards' },
        { key: 'transparency_qr', name: 'Transparency QR Status', description: 'Amazon transparency status' },
        { key: 'print_count', name: 'Print Count Label', description: 'Current print count information' }
      ]
    };
  },
  mounted() {
    // Load printers first, then open scanner
    this.loadPrinters().then(() => {
      this.openPrinterScanner();
    });
  },
  methods: {
    async loadPrinters() {
      this.loadingPrinters = true;
      try {
        const response = await fetch('/api/printer/get-printers', {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          }
        });
        
        if (!response.ok) {
          const errorText = await response.text();
          console.error('Response error:', errorText);
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
          const responseText = await response.text();
          console.error('Non-JSON response:', responseText);
          throw new Error('Server returned non-JSON response');
        }
        
        const data = await response.json();
        console.log('Raw API response:', data); // Debug log
        
        if (data.success) {
          this.printers = data.printers || [];
          console.log('Loaded printers:', this.printers); // Debug log
          
          // Load saved printer selection
          this.loadSavedPrinter();
        } else {
          console.error('Failed to load printers:', data.message);
          this.showError('Failed to load printers: ' + data.message);
        }
        
      } catch (error) {
        console.error('Error loading printers:', error);
        this.showError('Error loading printers: ' + error.message);
      } finally {
        this.loadingPrinters = false;
      }
    },
    
    loadSavedPrinter() {
      // Load saved printer from localStorage for both tabs
      const savedPrinter = localStorage.getItem('selectedPrinter');
      const savedReprintPrinter = localStorage.getItem('reprintSelectedPrinter');
      
      if (savedPrinter && this.printers.find(p => p.printerid == savedPrinter)) {
        this.selectedPrinter = parseInt(savedPrinter);
      }
      
      if (savedReprintPrinter && this.printers.find(p => p.printerid == savedReprintPrinter)) {
        this.reprintSelectedPrinter = parseInt(savedReprintPrinter);
      } else if (savedPrinter && this.printers.find(p => p.printerid == savedPrinter)) {
        // Use main printer as default for reprint
        this.reprintSelectedPrinter = parseInt(savedPrinter);
      }
    },
    
    onPrinterChange() {
      // Save selected printer to localStorage
      if (this.selectedPrinter) {
        localStorage.setItem('selectedPrinter', this.selectedPrinter.toString());
        this.focusInput();
      }
    },

    onReprintPrinterChange() {
      // Save reprint printer selection
      if (this.reprintSelectedPrinter) {
        localStorage.setItem('reprintSelectedPrinter', this.reprintSelectedPrinter.toString());
        this.focusReprintInput();
      }
    },
    
    openPrinterScanner() {
      // Open the scanner modal
      if (this.$refs.scannerComponent) {
        this.$refs.scannerComponent.openScannerModal();
      }
    },
    
    onScannerOpened() {
      console.log('Printer scanner opened');
      this.focusActiveInput();
    },
    
    onScannerClosed() {
      console.log('Printer scanner closed');
      // Clean up the printer app when scanner closes
      this.handleScannerClose();
    },
    
    onModeChanged(data) {
      this.isManualMode = data.manual;
      console.log('Mode changed to:', data.manual ? 'Manual' : 'Auto');
    },
    
    onSerialInput() {
      // Auto-process if in auto mode and serial looks complete
      if (!this.isManualMode && this.serialNumber.length >= 8 && this.selectedPrinter) {
        this.processPrintScan();
      }
    },

    onReprintSearchInput() {
      // Auto-search if in auto mode and input looks complete
      if (!this.isManualMode && this.reprintSearchTerm.length >= 5 && this.reprintSelectedPrinter) {
        this.searchForReprint();
      }
    },

    async searchForReprint() {
      if (!this.reprintSearchTerm.trim()) {
        this.showError('Please enter a search term (serial number, PCN, or RT counter)');
        return;
      }
      
      if (!this.reprintSelectedPrinter) {
        this.showError('Please select a printer first');
        return;
      }

      this.$refs.scannerComponent.startLoading('Searching for product...');
      this.isProcessing = true;
      
      try {
        const response = await fetch('/api/printer/search-for-reprint', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({
            search_term: this.reprintSearchTerm.trim()
          })
        });
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
          this.reprintProductInfo = data.product_data;
          this.selectedLabelType = null; // Reset label selection
          console.log('Product found for reprint:', this.reprintProductInfo);
        } else {
          this.reprintProductInfo = null;
          this.selectedLabelType = null;
          this.showError(data.message || 'Product not found');
        }
        
      } catch (error) {
        console.error('Search error:', error);
        this.reprintProductInfo = null;
        this.selectedLabelType = null;
        this.showError('Search failed: ' + error.message);
      } finally {
        this.$refs.scannerComponent.stopLoading();
        this.isProcessing = false;
      }
    },

    async processReprint() {
      if (!this.selectedLabelType) {
        this.showError('Please select a label type to reprint');
        return;
      }
      
      if (!this.reprintProductInfo) {
        this.showError('No product selected for reprint');
        return;
      }

      this.$refs.scannerComponent.startLoading('Reprinting label...');
      this.isProcessing = true;
      
      try {
        const selectedPrinterData = this.printers.find(p => p.printerid == this.reprintSelectedPrinter);
        
        const response = await fetch('/api/printer/reprint-single-label', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({
            product_id: this.reprintProductInfo.ProductID,
            label_type: this.selectedLabelType,
            printer_id: this.reprintSelectedPrinter,
            printer_name: selectedPrinterData ? selectedPrinterData.printername : 'Unknown',
            search_term: this.reprintSearchTerm
          })
        });
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
          this.handleReprintSuccess(result);
        } else {
          this.handleReprintError(result.message || 'Reprint failed');
        }
        
      } catch (error) {
        console.error('Reprint error:', error);
        this.handleReprintError('Reprint failed: ' + error.message);
      } finally {
        this.$refs.scannerComponent.stopLoading();
        this.isProcessing = false;
      }
    },

    getSerialNumbers(product) {
      const serials = [];
      if (product.serialnumber) serials.push(`A: ${product.serialnumber}`);
      if (product.serialnumberb) serials.push(`B: ${product.serialnumberb}`);
      if (product.serialnumberc) serials.push(`C: ${product.serialnumberc}`);
      if (product.serialnumberd) serials.push(`D: ${product.serialnumberd}`);
      return serials.length > 0 ? serials.join(', ') : 'N/A';
    },

    handleReprintSuccess(result) {
      const labelTypeName = this.availableLabelTypes.find(lt => lt.key === this.selectedLabelType)?.name || this.selectedLabelType;
      
      // Add to scanner success
      this.$refs.scannerComponent.addSuccessScan({
        serial_number: this.reprintSearchTerm,
        status: `Reprinted: ${labelTypeName}`,
        timestamp: new Date().toISOString()
      });
      
      // Show success notification
      this.$refs.scannerComponent.showScanSuccess(`${labelTypeName} reprinted successfully`);
      
      // Play success sound
      SoundService.successScan(false);
      
      // Clear form
      this.clearReprintForm();
      
      console.log('Label reprinted successfully:', result);
    },

    handleReprintError(message) {
      // Add to scanner error
      this.$refs.scannerComponent.addErrorScan({
        serial_number: this.reprintSearchTerm,
        status: 'Reprint Failed'
      }, message);
      
      // Show error notification
      this.$refs.scannerComponent.showScanError(message);
      
      // Play error sound
      SoundService.error(true);
      
      console.error('Reprint failed:', message);
    },

    clearReprintForm() {
      this.reprintSearchTerm = '';
      this.reprintProductInfo = null;
      this.selectedLabelType = null;
      this.focusReprintInput();
    },
    
    // Existing methods continue...
    async processPrintScan() {
      if (!this.serialNumber.trim()) {
        this.showError('Please enter a serial number');
        return;
      }
      
      if (!this.selectedPrinter) {
        this.showError('Please select a printer first');
        return;
      }
      
      // Show loading state
      this.$refs.scannerComponent.startLoading('Checking database...');
      this.isProcessing = true;
      
      try {
        // Check database with your conditions
        const result = await this.checkPrintConditions(this.serialNumber);
        
        if (result.success) {
          // Print the label
          await this.printLabel(result.data);
          this.handlePrintSuccess(result.data);
        } else {
          this.handlePrintError(result.message);
        }
        
      } catch (error) {
        console.error('Print processing error:', error);
        this.handlePrintError('Database error occurred');
      } finally {
        this.$refs.scannerComponent.stopLoading();
        this.isProcessing = false;
        this.clearSerial();
      }
    },
    
    async checkPrintConditions(serialNumber) {
      try {
        // Use the new API endpoint
        const response = await fetch('/api/printer/check-serial', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({
            serial_number: serialNumber
          })
        });
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        // The API returns meets_print_conditions boolean
        if (data.success && data.meets_print_conditions) {
          return {
            success: true,
            data: data
          };
        } else {
          return {
            success: false,
            message: data.message || 'Item not ready for printing'
          };
        }
        
      } catch (error) {
        console.error('Database check error:', error);
        return {
          success: false,
          message: 'Database connection failed'
        };
      }
    },
    
    async printLabel(data) {
      try {
        // Get selected printer info
        const selectedPrinterData = this.printers.find(p => p.printerid == this.selectedPrinter);
        const printerName = selectedPrinterData ? selectedPrinterData.printername : 'Unknown Printer';
        
        // Use the existing API endpoint and include printer info
       const response = await fetch('/api/printer/print-label', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({
            serial_number: this.serialNumber,
            printer_id: this.selectedPrinter,
            printer_name: printerName,
            print_data: data
          })
        });
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (!result.success) {
          throw new Error(result.message || 'Print failed');
        }
        
        return result;
        
      } catch (error) {
        console.error('Print service error:', error);
        throw new Error('Print service error: ' + error.message);
      }
    },
    
    handlePrintSuccess(data) {
      // Add to scanner success
      this.$refs.scannerComponent.addSuccessScan({
        serial_number: this.serialNumber,
        status: 'Printed',
        timestamp: new Date().toISOString()
      });
      
      // Show success notification
      this.$refs.scannerComponent.showScanSuccess(this.serialNumber);
      
      // Play success sound
      SoundService.successScan(false);
      
      console.log('Label printed successfully for:', this.serialNumber);
    },
    
    handlePrintError(message) {
      // Add to scanner error
      this.$refs.scannerComponent.addErrorScan({
        serial_number: this.serialNumber,
        status: 'Failed'
      }, message);
      
      // Show error notification
      this.$refs.scannerComponent.showScanError(message);
      
      // Play error sound
      SoundService.error(true);
      
      console.error('Print failed:', message);
    },
    
    showError(message) {
      this.$refs.scannerComponent.showScanError(message);
      SoundService.error(true);
    },
    
    clearSerial() {
      this.serialNumber = '';
      this.focusInput();
    },
    
    focusInput() {
      this.$nextTick(() => {
        if (this.$refs.serialInput && this.selectedPrinter) {
          this.$refs.serialInput.focus();
        }
      });
    },

    focusReprintInput() {
      this.$nextTick(() => {
        if (this.$refs.reprintSearchInput && this.reprintSelectedPrinter) {
          this.$refs.reprintSearchInput.focus();
        }
      });
    },

    focusActiveInput() {
      if (this.activeTab === 'print') {
        this.focusInput();
      } else {
        this.focusReprintInput();
      }
    },
    
    handleScannerClose() {
      // Clean up the printer app when closing
      if (window.cleanupPrinterApp) {
        window.cleanupPrinterApp();
      } else {
        // Fallback navigation
        if (window.history.length > 1) {
          window.history.back();
        } else {
          window.location.href = '/dashboard';
        }
      }
    }
  }
};
</script>

<style scoped>
.printer-container {
  width: 100%;
  min-height: 400px;
}

.printer-input-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 15px;
}

/* Tab Navigation */
.tab-navigation {
  display: flex;
  gap: 0;
  margin-bottom: 20px;
  border-bottom: 2px solid #e0e0e0;
}

.tab-button {
  flex: 1;
  padding: 12px 20px;
  background-color: #f5f5f5;
  border: none;
  border-bottom: 3px solid transparent;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.tab-button:hover {
  background-color: #e8e8e8;
}

.tab-button.active {
  background-color: white;
  border-bottom-color: #4CAF50;
  color: #4CAF50;
}

.tab-content {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

/* Form Elements */
.input-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.input-group label {
  font-weight: 600;
  font-size: 14px;
  color: #333;
}

.input-group input,
.printer-select,
.label-type-select {
  padding: 12px;
  border: 2px solid #ddd;
  border-radius: 6px;
  font-size: 16px;
  transition: border-color 0.3s ease;
}

.input-group input:focus,
.printer-select:focus,
.label-type-select:focus {
  border-color: #4CAF50;
  outline: none;
  box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
}

.input-group input:disabled,
.printer-select:disabled,
.label-type-select:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.printer-select,
.label-type-select {
  background-color: white;
  cursor: pointer;
}

/* Product Info Card */
.product-info-card {
  background-color: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 8px;
  padding: 15px;
  margin: 10px 0;
}

.product-info-card h4 {
  margin: 0 0 15px 0;
  color: #495057;
  display: flex;
  align-items: center;
  gap: 8px;
}

.product-info-card h4 i {
  color: #007bff;
}

.info-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
}

.info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 5px 0;
  border-bottom: 1px solid #e9ecef;
}

.info-item:last-child {
  border-bottom: none;
}

.info-item .label {
  font-weight: 600;
  color: #6c757d;
}

.info-item .value {
  color: #495057;
  text-align: right;
  flex: 1;
  margin-left: 10px;
}

/* Buttons */
.submit-button {
  padding: 12px 20px;
  background-color: #4CAF50;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 16px;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.submit-button:hover:not(:disabled) {
  background-color: #45a049;
  transform: translateY(-2px);
}

.submit-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.reprint-button {
  background-color: #ff9800;
}

.reprint-button:hover:not(:disabled) {
  background-color: #e68900;
}

.submit-button i {
  font-size: 14px;
}

/* Mobile responsive */
@media (max-width: 768px) {
  .printer-input-section {
    padding: 10px;
    gap: 15px;
  }
  
  /* Keep tabs horizontal on mobile - just smaller */
  .tab-navigation {
    display: flex;
    flex-direction: row;
    gap: 0;
    margin-bottom: 15px;
    border-bottom: 2px solid #e0e0e0;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  
  .tab-button {
    flex: 1;
    min-width: 140px;
    padding: 14px 12px;
    background-color: #f5f5f5;
    border: none;
    border-bottom: 3px solid transparent;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    white-space: nowrap;
  }
  
  .tab-button:hover {
    background-color: #e8e8e8;
  }
  
  .tab-button.active {
    background-color: white;
    border-bottom-color: #4CAF50;
    color: #4CAF50;
  }
  
  .tab-button i {
    font-size: 12px;
  }
  
  .tab-content {
    gap: 20px;
  }
  
  .input-group {
    gap: 8px;
  }
  
  .input-group label {
    font-size: 16px;
    font-weight: 700;
  }
  
  /* Fix input overflow issues */
  .input-group input,
  .printer-select,
  .label-type-select {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    padding: 16px 15px;
    font-size: 16px; /* Prevent iOS zoom */
    border-radius: 8px;
    border: 2px solid #ddd;
    min-height: 54px;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    word-wrap: break-word;
    overflow-wrap: break-word;
  }
  
  /* Fix placeholder text overflow */
  .input-group input::placeholder {
    font-size: 14px;
    color: #999;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  
  /* Specific fix for search input */
  #reprintSearch {
    font-size: 16px !important;
  }
  
  #reprintSearch::placeholder {
    font-size: 13px;
    line-height: 1.2;
  }
  
  /* Fix select dropdown arrow on mobile */
  .printer-select,
  .label-type-select {
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 15px center;
    background-size: 20px;
    background-color: white;
    padding-right: 50px;
    cursor: pointer;
  }
  
  .submit-button {
    width: 100%;
    padding: 18px 25px;
    font-size: 18px;
    min-height: 56px;
    border-radius: 8px;
    font-weight: 700;
    box-sizing: border-box;
  }
  
  /* Product Info Card Mobile */
  .product-info-card {
    padding: 20px 15px;
    margin: 15px 0;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    width: 100%;
    box-sizing: border-box;
  }
  
  .product-info-card h4 {
    font-size: 18px;
    margin-bottom: 18px;
    text-align: center;
  }
  
  .info-grid {
    gap: 12px;
    width: 100%;
  }
  
  .info-item {
    flex-direction: column;
    align-items: stretch;
    gap: 6px;
    padding: 12px 0;
    border-bottom: 2px solid #e9ecef;
    width: 100%;
    box-sizing: border-box;
  }
  
  .info-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
  }
  
  .info-item .label {
    font-size: 14px;
    font-weight: 700;
    color: #495057;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  
  .info-item .value {
    font-size: 16px;
    color: #212529;
    text-align: left;
    margin-left: 0;
    font-weight: 600;
    word-break: break-word;
    line-height: 1.4;
    overflow-wrap: break-word;
    max-width: 100%;
  }
}

/* Extra small devices (phones, less than 576px) */
@media (max-width: 575px) {
  .printer-input-section {
    padding: 8px;
  }
  
  .tab-button {
    min-width: 120px;
    padding: 12px 8px;
    font-size: 12px;
    gap: 4px;
  }
  
  .tab-button i {
    font-size: 11px;
  }
  
  .input-group input,
  .printer-select,
  .label-type-select {
    padding: 14px 12px;
    font-size: 16px;
    min-height: 50px;
  }
  
  .input-group input::placeholder {
    font-size: 12px;
  }
  
  #reprintSearch::placeholder {
    font-size: 12px;
  }
  
  .printer-select,
  .label-type-select {
    padding-right: 45px;
    background-size: 18px;
    background-position: right 12px center;
  }
  
  .submit-button {
    padding: 16px 20px;
    font-size: 16px;
    min-height: 52px;
  }
  
  .product-info-card {
    padding: 15px 12px;
    margin: 12px 0;
  }
  
  .product-info-card h4 {
    font-size: 16px;
    margin-bottom: 15px;
  }
  
  .info-item {
    padding: 10px 0;
  }
  
  .info-item .label {
    font-size: 13px;
  }
  
  .info-item .value {
    font-size: 15px;
  }
}

/* Landscape phone orientation */
@media (max-width: 900px) and (orientation: landscape) {
  .tab-navigation {
    flex-direction: row;
    margin-bottom: 15px;
  }
  
  .tab-button {
    min-width: 130px;
    padding: 10px 15px;
  }
  
  .product-info-card {
    margin: 10px 0;
  }
  
  .info-grid {
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }
  
  .info-item {
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
  }
  
  .info-item .value {
    text-align: right;
    margin-left: 10px;
    max-width: 60%;
  }
}

/* Touch improvements */
@media (hover: none) and (pointer: coarse) {
  .tab-button:hover {
    background-color: #f5f5f5;
  }
  
  .tab-button.active:hover {
    background-color: white;
  }
  
  .submit-button:hover:not(:disabled) {
    transform: none;
  }
  
  .submit-button:active:not(:disabled) {
    transform: scale(0.98);
    transition: transform 0.1s ease;
  }
  
  /* Improve touch targets */
  .input-group input,
  .printer-select,
  .label-type-select,
  .submit-button {
    min-height: 48px;
  }
}

/* Container width fixes */
@media (max-width: 768px) {
  .printer-container {
    width: 100%;
    max-width: 100%;
    overflow-x: hidden;
  }
  
  .printer-input-section {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
  }
  
  .tab-content {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
  }
}
</style>