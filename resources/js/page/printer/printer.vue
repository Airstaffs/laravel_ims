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
            <label for="printerSerial">Serial Number</label>
            <input 
              type="text" 
              id="printerSerial"
              v-model="serialNumber"
              placeholder="Scan or enter serial number"
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
            <i class="fas fa-print"></i> Print Label
          </button>
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
    }
  },
  emits: ['close-modal'], // Declare the emit for Vue 3
  data() {
    return {
      serialNumber: '',
      isProcessing: false,
      isManualMode: false,
      selectedPrinter: null, // Changed from empty string to null
      printers: [],
      loadingPrinters: false
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
          console.log('First printer structure:', this.printers[0]); // Debug log
          
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
      // Load saved printer from localStorage
      const savedPrinter = localStorage.getItem('selectedPrinter');
      if (savedPrinter && this.printers.find(p => p.printerid == savedPrinter)) {
        // Convert to number to match option values
        this.selectedPrinter = parseInt(savedPrinter);
        console.log('Loaded saved printer:', this.selectedPrinter);
      }
    },
    
    onPrinterChange() {
      // Save selected printer to localStorage
      if (this.selectedPrinter) {
        localStorage.setItem('selectedPrinter', this.selectedPrinter.toString());
        
        // Get printer name for display
        const selectedPrinterData = this.printers.find(p => p.printerid == this.selectedPrinter);
        console.log('Printer selected:', {
          id: this.selectedPrinter,
          name: selectedPrinterData ? selectedPrinterData.printername : 'Unknown'
        });
        
        // Focus on serial input after printer selection
        this.focusInput();
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
      this.focusInput();
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
.printer-select {
  padding: 12px;
  border: 2px solid #ddd;
  border-radius: 6px;
  font-size: 16px;
  transition: border-color 0.3s ease;
}

.input-group input:focus,
.printer-select:focus {
  border-color: #4CAF50;
  outline: none;
  box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
}

.input-group input:disabled,
.printer-select:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.printer-select {
  background-color: white;
  cursor: pointer;
}

.printer-select:disabled {
  cursor: not-allowed;
}

.selected-printer-info {
  margin-top: 5px;
  padding: 8px 12px;
  background-color: #e8f5e8;
  border: 1px solid #4CAF50;
  border-radius: 4px;
  font-size: 14px;
  color: #2d5a2d;
  display: flex;
  align-items: center;
  gap: 8px;
}

.selected-printer-info i {
  color: #4CAF50;
  font-size: 16px;
}

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

.submit-button i {
  font-size: 14px;
}

/* Mobile responsive */
@media (max-width: 600px) {
  .input-group input,
  .printer-select {
    padding: 15px;
    font-size: 16px;
  }
  
  .submit-button {
    padding: 15px 20px;
    font-size: 16px;
  }
}
</style>