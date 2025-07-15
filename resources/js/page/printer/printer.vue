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
          <div class="input-group">
            <label for="printerSerial">Serial Number</label>
            <input 
              type="text" 
              id="printerSerial"
              v-model="serialNumber"
              placeholder="Scan or enter serial number"
              @keyup.enter="processPrintScan"
              @input="onSerialInput"
              :disabled="isProcessing"
              ref="serialInput"
            >
          </div>
          
          <!-- Manual mode submit button -->
          <button 
            v-if="isManualMode" 
            @click="processPrintScan" 
            class="submit-button"
            :disabled="!serialNumber || isProcessing"
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
  emits: ['close-modal'], // Declare the emit for Vue 3
  data() {
    return {
      serialNumber: '',
      isProcessing: false,
      isManualMode: false
    };
  },
  mounted() {
    // Auto-open the scanner when component mounts
    this.openPrinterScanner();
  },
  methods: {
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
      if (!this.isManualMode && this.serialNumber.length >= 8) {
        this.processPrintScan();
      }
    },
    
    async processPrintScan() {
      if (!this.serialNumber.trim()) {
        this.showError('Please enter a serial number');
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
        // Use the new API endpoint
        const response = await fetch('/api/printer/print-label', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({
            serial_number: this.serialNumber,
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
        if (this.$refs.serialInput) {
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

.input-group input {
  padding: 12px;
  border: 2px solid #ddd;
  border-radius: 6px;
  font-size: 16px;
  transition: border-color 0.3s ease;
}

.input-group input:focus {
  border-color: #4CAF50;
  outline: none;
  box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
}

.input-group input:disabled {
  opacity: 0.6;
  cursor: not-allowed;
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
  .input-group input {
    padding: 15px;
    font-size: 16px;
  }
  
  .submit-button {
    padding: 15px 20px;
    font-size: 16px;
  }
}
</style>