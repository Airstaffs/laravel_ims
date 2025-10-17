<template>
  <div class="printer-container">
    <!-- Use your existing scanner component -->
    <ScannerComponent
      ref="scannerComponent"
      :hideButton="true"
      :enableCamera="false"
      :scannerTitle="'Enhanced Label Printer'"
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
            <!-- Enhanced Printer Selection with Marriage Info -->
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
                  v-for="printer in uniquePrinters" 
                  :key="printer.printerid"
                  :value="parseInt(printer.printerid)"
                  :class="{ 'married-printer': printer.is_married }"
                >
                  {{ printer.printername_short }}
                  <span v-if="printer.is_married && printer.married_printer">
                    (Married to {{ printer.married_printer.name }})
                  </span>
                </option>
              </select>
              
              <!-- Marriage Info Display -->
              <div v-if="selectedPrinterInfo && selectedPrinterInfo.is_married" class="marriage-info">
                <div class="marriage-details">
                  <span class="marriage-text">
                    Married to: {{ selectedPrinterInfo.married_printer.name }}
                  </span>
                  <span class="marriage-name" v-if="selectedPrinterInfo.marriage_name">
                    ({{ selectedPrinterInfo.marriage_name }})
                  </span>
                </div>
                <div class="marriage-description" v-if="selectedPrinterInfo.marriage_description">
                  {{ selectedPrinterInfo.marriage_description }}
                </div>
              </div>
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
              <i class="fas fa-print"></i> 
              {{ selectedPrinterInfo && selectedPrinterInfo.is_married ? 'Print to Married Printers' : 'Print All Labels' }}
            </button>
          </div>

          <!-- Reprint Single Label Tab -->
          <div v-if="activeTab === 'reprint'" class="tab-content">
            <!-- Enhanced Printer Selection for Reprint -->
            <div class="input-group">
              <label for="reprintPrinterSelect">Select Printer</label>
              <select 
                id="reprintPrinterSelect"
                v-model="reprintSelectedPrinter"
                @change="onReprintPrinterChange"
                :disabled="isProcessing || loadingPrinters"
                class="printer-select"
                :class="{ 'has-warning': compatibilityWarning && compatibilityWarning.type === 'error' }"
              >
                <option :value="null" disabled>
                  {{ loadingPrinters ? 'Loading printers...' : 'Choose a printer' }}
                </option>
                <option 
                  v-for="printer in allIndividualPrinters" 
                  :key="printer.printerid" 
                  :value="parseInt(printer.printerid)"
                >
                  {{ printer.displayName }}
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

            <!-- Enhanced Label Type Selection with Strict Compatibility -->
            <div class="input-group" v-if="availableLabelTypes.length > 0">
              <label for="labelTypeSelect">Select Label Type to Print</label>
              <select 
                id="labelTypeSelect"
                v-model="selectedLabelType"
                @change="onLabelTypeChanged"
                :disabled="isProcessing"
                class="label-type-select"
                :class="{ 'has-warning': compatibilityWarning && compatibilityWarning.type === 'error' }"
              >
                <option :value="null" disabled>Choose a label type</option>
                <optgroup label="Small Labels (Small Label Printer Required)">
                  <option 
                    v-for="labelType in smallLabelTypes" 
                    :key="labelType.key" 
                    :value="labelType.key"
                  >
                    {{ labelType.name }} {{ labelType.description ? `- ${labelType.description}` : '' }}
                  </option>
                </optgroup>
                <optgroup label="Instruction Cards (Instruction Card Printer Required)" v-if="instructionCardTypes.length > 0">
                  <option 
                    v-for="labelType in instructionCardTypes" 
                    :key="labelType.key" 
                    :value="labelType.key"
                  >
                    {{ labelType.name }} {{ labelType.description ? `- ${labelType.description}` : '' }}
                  </option>
                </optgroup>
              </select>
              
              <!-- Compatibility Warning Display -->
              <div v-if="compatibilityWarning" class="compatibility-warning" :class="compatibilityWarning.type">
                <div class="warning-header">
                  <i class="fas fa-exclamation-triangle text-danger"></i>
                  <span class="warning-title">Printer Incompatibility</span>
                </div>
                <p class="warning-message">{{ compatibilityWarning.message }}</p>
                <div class="strict-enforcement-note">
                  <strong>Strict Enforcement:</strong> For single label reprinting, each label type must use the correct printer type. No exceptions.
                </div>
              </div>

              <!-- Printer Suggestions -->
              <div v-if="showPrinterSuggestions && suggestedPrinters.length > 0" class="printer-suggestions">
                <h4><i class="fas fa-lightbulb"></i> Compatible Printers Available</h4>
                <div class="suggested-printer-list">
                  <button 
                    v-for="printer in suggestedPrinters" 
                    :key="printer.printerid"
                    @click="selectSuggestedPrinter(printer)"
                    class="suggested-printer-btn"
                  >
                    <i class="fas fa-print"></i>
                    {{ printer.printername }}
                  </button>
                </div>
              </div>

              <!-- Smart Routing Info -->
              <div v-if="smartRoutingInfo && !compatibilityWarning" class="smart-routing-display">
                <div class="routing-indicator">
                  <i class="fas fa-arrow-right text-success"></i>
                  <span class="routing-message">{{ smartRoutingInfo.message }}</span>
                </div>
                <div class="target-printer">
                  Target: <strong>{{ smartRoutingInfo.targetPrinter }}</strong>
                </div>
              </div>
            </div>

            <!-- Enhanced Product Info Display -->
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
                <div class="info-item">
                  <span class="label">Location:</span>
                  <span class="value">{{ reprintProductInfo.ProductModuleLoc || 'N/A' }}</span>
                </div>
                <div class="info-item" v-if="reprintProductInfo.warehouselocation">
                  <span class="label">Warehouse Location:</span>
                  <span class="value">{{ reprintProductInfo.warehouselocation }}</span>
                </div>
              </div>
            </div>

            <!-- Enhanced Reprint Button with Strict Enforcement -->
            <button 
              @click="processReprint" 
              class="submit-button reprint-button"
              :disabled="!selectedLabelType || isProcessing || !reprintSelectedPrinter || !reprintProductInfo || (compatibilityWarning && compatibilityWarning.type === 'error')"
              :class="{ 'has-compatibility-warning': compatibilityWarning && compatibilityWarning.type === 'error' }"
            >
              <i class="fas fa-redo"></i> 
              Reprint {{ selectedLabelTypeName }}
              <span v-if="smartRoutingInfo" class="smart-routed-indicator">
                → {{ smartRoutingInfo.targetPrinter }}
              </span>
              <span v-else-if="reprintSelectedPrinterInfo && reprintSelectedPrinterInfo.is_married" class="married-print-indicator">
                (Smart Routed)
              </span>
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
    // Filter printers to show only unique entries (no duplicates from married pairs) - FOR MAIN PRINT TAB ONLY
    uniquePrinters() {
      if (!this.printers || this.printers.length === 0) {
        return [];
      }

      const seenMarriageKeys = new Set();
      const filtered = [];
      
      for (const printer of this.printers) {
        if (printer.is_married && printer.married_printer && printer.married_printer.id) {
          // Create a consistent marriage key using sorted printer IDs
          const marriageIds = [printer.printerid, printer.married_printer.id].sort((a, b) => a - b);
          const marriageKey = marriageIds.join('-');
          
          // Only add if we haven't seen this marriage pair yet
          if (!seenMarriageKeys.has(marriageKey)) {
            seenMarriageKeys.add(marriageKey);
            // Always add the printer with the smaller ID for consistency
            if (printer.printerid === marriageIds[0]) {
              filtered.push(printer);
              console.log('Added married printer (primary):', printer.printername_short, '→', printer.married_printer.name);
            }
          }
        } else {
          // For non-married printers, always add them
          filtered.push(printer);
          console.log('Added single printer:', printer.printername_short);
        }
      }
      
      console.log('Total printers loaded:', this.printers.length);
      console.log('Filtered unique printers:', filtered.length);
      console.log('Filtered list:', filtered.map(p => `${p.printername_short}${p.is_married ? ' (Married)' : ''}`));
      
      return filtered;
    },

    // All individual printers for reprint dropdown - shows ALL printers individually
    allIndividualPrinters() {
      if (!this.printers || this.printers.length === 0) {
        return [];
      }

      // Create individual printer entries from all printers
      const individualPrinters = [];
      
      for (const printer of this.printers) {
        // Add the main printer
        individualPrinters.push({
          ...printer,
          displayName: printer.printername_short,
          isFromMarriage: printer.is_married
        });

        // If married, also add the married printer as a separate option
        if (printer.is_married && printer.married_printer && printer.married_printer.id) {
          // Check if we already added this married printer
          const marriedExists = individualPrinters.find(p => p.printerid === printer.married_printer.id);
          if (!marriedExists) {
            individualPrinters.push({
              printerid: printer.married_printer.id,
              printername_short: printer.married_printer.name,
              printerip: printer.married_printer.ip,
              printer_type: printer.married_printer.type,
              status: printer.married_printer.status,
              is_married: true,
              married_printer: {
                id: printer.printerid,
                name: printer.printername_short,
                type: printer.printer_type
              },
              displayName: printer.married_printer.name,
              isFromMarriage: true
            });
          }
        }
      }

      // Remove duplicates and sort by name
      const unique = individualPrinters.filter((printer, index, self) => 
        index === self.findIndex(p => p.printerid === printer.printerid)
      );

      return unique.sort((a, b) => a.displayName.localeCompare(b.displayName));
    },
    
    selectedPrinterInfo() {
      if (!this.selectedPrinter) return null;
      return this.printers.find(p => p.printerid == this.selectedPrinter);
    },
    
    reprintSelectedPrinterInfo() {
      if (!this.reprintSelectedPrinter) return null;
      return this.allIndividualPrinters.find(p => p.printerid == this.reprintSelectedPrinter);
    },
    
    selectedPrinterName() {
      return this.selectedPrinterInfo ? this.selectedPrinterInfo.printername_short : '';
    },
    
    reprintSelectedPrinterName() {
      return this.reprintSelectedPrinterInfo ? this.reprintSelectedPrinterInfo.printername_short : '';
    },
    
    selectedLabelTypeName() {
      if (!this.selectedLabelType) return '';
      const labelType = this.availableLabelTypes.find(lt => lt.key === this.selectedLabelType);
      return labelType ? labelType.name : this.selectedLabelType;
    },
    
    smallLabelTypes() {
      return this.availableLabelTypes.filter(lt => lt.category === 'small');
    },
    
    instructionCardTypes() {
      return this.availableLabelTypes.filter(lt => lt.category === 'instruction');
    }
  },
  
  emits: ['close-modal'],
  
  data() {
    return {
      // Existing data
      serialNumber: '',
      isProcessing: false,
      isManualMode: false,
      selectedPrinter: null,
      printers: [],
      loadingPrinters: false,
      
      // Enhanced reprint data with strict enforcement
      activeTab: 'print',
      reprintSelectedPrinter: null,
      reprintSearchTerm: '',
      reprintProductInfo: null,
      selectedLabelType: null,
      
      // Strict compatibility enforcement
      compatibilityWarning: null,
      suggestedPrinters: [],
      showPrinterSuggestions: false,
      smartRoutingInfo: null,
      
      // Performance optimization
      autoProcessTimer: null,
      autoSearchTimer: null,
      
      // Label types with vector_image in small labels
      availableLabelTypes: [
        // Small Label Types - INCLUDING VECTOR IMAGE
        { key: 'serial_labels', name: 'Serial Number Labels', description: 'All serial number labels (A, B, C, D)', category: 'small' },
        { key: 'fnsku_label', name: 'FNSKU Label', description: 'Main FNSKU barcode label', category: 'small' },
        { key: 'title_label', name: 'Title Label', description: 'Product title with RT/AR package number', category: 'small' },
        { key: 'item_number_label', name: 'Item Number Label', description: 'Item number with barcode', category: 'small' },
        { key: 'timestamp_label', name: 'Timestamp Label', description: 'Priority and timestamp information', category: 'small' },
        { key: 'sticker_note_label', name: 'Sticker Note Label', description: 'Custom notes and comments', category: 'small' },
        { key: 'warehouse_location_label', name: 'Warehouse Location Label', description: 'Storage location information', category: 'small' },
        { key: 'rtcounter_label', name: 'RT/AR Counter Label', description: 'RT/AR counter with barcode', category: 'small' },
        { key: 'qr_manual', name: 'QR Code - Manual', description: 'QR code for user manual', category: 'small' },
        { key: 'qr_serial', name: 'QR Code - Serial Photos', description: 'QR code for serial photos', category: 'small' },
        { key: 'transparency_qr', name: 'Transparency QR Status', description: 'Amazon transparency status', category: 'small' },
        { key: 'print_count', name: 'Print Count Label', description: 'Current print count information', category: 'small' },
        { key: 'vector_image', name: 'Vector Image', description: 'Product vector image (SMALL LABEL PRINTER)', category: 'small' },
        { key: 'small_label_card', name: 'Small Label Card (Page6)', description: 'Compact label with serial QR code (2"x1" - 3 copies)', category: 'small' },
        // Instruction Card Types - ONLY INSTRUCTION CARDS
        { key: 'instruction_cards', name: 'Instruction Cards', description: 'All instruction cards (INSTRUCTION CARD PRINTER ONLY)', category: 'instruction' }
      ]
    };
  },

  watch: {
    selectedLabelType() {
      this.onLabelTypeChanged();
    }
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
        console.log('Raw API response:', data);
        
        if (data.success) {
          this.printers = data.printers || [];
          console.log('Loaded printers with marriage info:', this.printers);
          
          // Load saved printer selection after printers are loaded
          this.$nextTick(() => {
            this.loadSavedPrinter();
          });
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
      
      console.log('Loading saved printers:', { savedPrinter, savedReprintPrinter });
      console.log('Available unique printers:', this.uniquePrinters.map(p => ({ id: p.printerid, name: p.printername_short })));
      console.log('Available individual printers:', this.allIndividualPrinters.map(p => ({ id: p.printerid, name: p.displayName })));
      
      // Check if saved printer exists in uniquePrinters (for main print tab)
      if (savedPrinter && this.uniquePrinters.find(p => p.printerid == savedPrinter)) {
        this.selectedPrinter = parseInt(savedPrinter);
        console.log('Restored main printer:', savedPrinter);
      }
      
      // Check if saved reprint printer exists in allIndividualPrinters (for reprint tab)
      if (savedReprintPrinter && this.allIndividualPrinters.find(p => p.printerid == savedReprintPrinter)) {
        this.reprintSelectedPrinter = parseInt(savedReprintPrinter);
        console.log('Restored reprint printer:', savedReprintPrinter);
      } else if (savedPrinter && this.allIndividualPrinters.find(p => p.printerid == savedPrinter)) {
        // Use main printer as default for reprint if it exists in individual list
        this.reprintSelectedPrinter = parseInt(savedPrinter);
        console.log('Using main printer as reprint default:', savedPrinter);
      }
      
      console.log('Final printer selections:', {
        selectedPrinter: this.selectedPrinter,
        reprintSelectedPrinter: this.reprintSelectedPrinter,
        uniquePrintersCount: this.uniquePrinters.length,
        individualPrintersCount: this.allIndividualPrinters.length
      });
    },
    
    onPrinterChange() {
      // Save selected printer to localStorage
      if (this.selectedPrinter) {
        localStorage.setItem('selectedPrinter', this.selectedPrinter.toString());
        this.focusInput();
        
        // Log marriage info
        const printerInfo = this.selectedPrinterInfo;
        if (printerInfo && printerInfo.is_married) {
          console.log('Selected married printer:', {
            primary: printerInfo.printername_short,
            married_to: printerInfo.married_printer.name,
            marriage_name: printerInfo.marriage_name
          });
        }
      }
    },

    onReprintPrinterChange() {
      // Save reprint printer selection
      if (this.reprintSelectedPrinter) {
        localStorage.setItem('reprintSelectedPrinter', this.reprintSelectedPrinter.toString());
        this.focusReprintInput();
        
        // Clear any previous warnings
        this.clearCompatibilityWarning();
        
        // Check compatibility if we already have a label type selected
        if (this.selectedLabelType) {
          this.checkLabelTypePrinterCompatibility();
        }

        // Log marriage info for reprint
        const printerInfo = this.reprintSelectedPrinterInfo;
        if (printerInfo && printerInfo.is_married) {
          console.log('Selected married printer for reprint:', {
            primary: printerInfo.printername_short,
            married_to: printerInfo.married_printer.name,
            marriage_name: printerInfo.marriage_name
          });
        }
      }
    },

    /**
     * Handle label type selection with STRICT printer compatibility check
     */
    onLabelTypeChanged() {
      if (!this.selectedLabelType || !this.reprintSelectedPrinter) {
        this.clearCompatibilityWarning();
        return;
      }

      // Check compatibility immediately when label type changes
      this.checkLabelTypePrinterCompatibility();
    },

    /**
     * Check label type and printer compatibility with STRICT ENFORCEMENT
     */
    async checkLabelTypePrinterCompatibility() {
      if (!this.selectedLabelType || !this.reprintSelectedPrinter) return;

      try {
        // Define label categories
        const instructionCardLabels = ['instruction_cards'];
        const isInstructionCardLabel = instructionCardLabels.includes(this.selectedLabelType);
        
        const printerInfo = this.reprintSelectedPrinterInfo;
        if (!printerInfo) return;

        // STRICT ENFORCEMENT: Check for exact incompatibilities
        if (isInstructionCardLabel && printerInfo.printer_type === 'small_label') {
          // Check if married printer can handle it
          if (printerInfo.is_married && printerInfo.married_printer && 
              printerInfo.married_printer.type === 'instruction_card') {
            // Married pair can handle it via smart routing
            this.showSmartRoutingInfo(true, printerInfo);
            this.clearCompatibilityWarning();
          } else {
            // Cannot handle instruction cards
            this.showIncompatibilityWarning(
              'instruction_card',
              'Instruction card labels can ONLY be printed on instruction card printers. Please select an instruction card printer.'
            );
          }
          return;
        }

        if (!isInstructionCardLabel && printerInfo.printer_type === 'instruction_card') {
          // Check if married printer can handle small labels
          if (printerInfo.is_married && printerInfo.married_printer && 
              printerInfo.married_printer.type === 'small_label') {
            // Married pair can handle it via smart routing
            this.showSmartRoutingInfo(false, printerInfo);
            this.clearCompatibilityWarning();
          } else {
            // Cannot handle small labels (including vector)
            this.showIncompatibilityWarning(
              'small_label',
              'Small labels (including vector images) can ONLY be printed on small label printers. Please select a small label printer.'
            );
          }
          return;
        }

        // If we get here, direct compatibility is good
        this.clearCompatibilityWarning();
        
        // Show direct printer usage info
        if (printerInfo.is_married) {
          this.showDirectPrinterInfo(isInstructionCardLabel, printerInfo);
        }

      } catch (error) {
        console.error('Error checking compatibility:', error);
      }
    },

    /**
     * Show incompatibility warning and fetch suggested printers
     */
    async showIncompatibilityWarning(requiredPrinterType, message) {
      this.compatibilityWarning = {
        message: message,
        type: 'error'
      };

      // Fetch suggested printers
      try {
        const response = await fetch('/api/printer/get-available-printers-for-label-type', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({
            label_type: this.selectedLabelType
          })
        });

        if (response.ok) {
          const data = await response.json();
          if (data.success) {
            this.suggestedPrinters = data.compatible_printers;
            this.showPrinterSuggestions = true;
          }
        }
      } catch (error) {
        console.error('Error fetching suggested printers:', error);
      }
    },

    /**
     * Show direct printer usage info for married printers when no routing needed
     */
    showDirectPrinterInfo(isInstructionCardLabel, printerInfo) {
      let message = '';
      let targetPrinter = '';

      if (isInstructionCardLabel && printerInfo.printer_type === 'instruction_card') {
        message = 'Will use selected instruction card printer';
        targetPrinter = printerInfo.printername_short;
      } else if (!isInstructionCardLabel && printerInfo.printer_type === 'small_label') {
        message = 'Will use selected small label printer';
        targetPrinter = printerInfo.printername_short;
      }

      if (message) {
        this.smartRoutingInfo = {
          message: message,
          targetPrinter: targetPrinter,
          type: 'info'
        };
      }
    },

    /**
     * Show smart routing information for married printers
     */
    showSmartRoutingInfo(isInstructionCardLabel, printerInfo) {
      if (!printerInfo.is_married) return;

      let targetPrinterName = '';
      let routingMessage = '';

      if (isInstructionCardLabel) {
        if (printerInfo.printer_type === 'instruction_card') {
          targetPrinterName = printerInfo.printername_short;
          routingMessage = 'Will use selected instruction card printer';
        } else if (printerInfo.married_printer && printerInfo.married_printer.type === 'instruction_card') {
          targetPrinterName = printerInfo.married_printer.name;
          routingMessage = 'Smart routing: Will use married instruction card printer';
        }
      } else {
        if (printerInfo.printer_type === 'small_label') {
          targetPrinterName = printerInfo.printername_short;
          routingMessage = 'Will use selected small label printer';
        } else if (printerInfo.married_printer && printerInfo.married_printer.type === 'small_label') {
          targetPrinterName = printerInfo.married_printer.name;
          routingMessage = 'Smart routing: Will use married small label printer';
        }
      }

      if (targetPrinterName) {
        this.smartRoutingInfo = {
          message: routingMessage,
          targetPrinter: targetPrinterName,
          type: 'info'
        };
      }
    },

    /**
     * Clear compatibility warnings and suggestions
     */
    clearCompatibilityWarning() {
      this.compatibilityWarning = null;
      this.suggestedPrinters = [];
      this.showPrinterSuggestions = false;
      this.smartRoutingInfo = null;
    },

    /**
     * Switch to a suggested printer
     */
    selectSuggestedPrinter(printer) {
      this.reprintSelectedPrinter = printer.printerid;
      this.showPrinterSuggestions = false;
      this.clearCompatibilityWarning();
      
      // Re-check compatibility with new printer
      this.$nextTick(() => {
        this.checkLabelTypePrinterCompatibility();
      });

      console.log('Switched to suggested printer:', printer.printername);
    },
    
    openPrinterScanner() {
      // Open the scanner modal
      if (this.$refs.scannerComponent) {
        this.$refs.scannerComponent.openScannerModal();
      }
    },
    
    onScannerOpened() {
      console.log('Enhanced printer scanner opened');
      this.focusActiveInput();
    },
    
    onScannerClosed() {
      console.log('Enhanced printer scanner closed');
      // Clean up the printer app when scanner closes
      this.handleScannerClose();
    },
    
    onModeChanged(data) {
      this.isManualMode = data.manual;
      console.log('Mode changed to:', data.manual ? 'Manual' : 'Auto');
    },
    
    onSerialInput() {
      // Clear any existing timer
      if (this.autoProcessTimer) {
        clearTimeout(this.autoProcessTimer);
      }
      
      // Auto-process if in auto mode and serial looks complete (optimized with debouncing)
      if (!this.isManualMode && this.serialNumber.length >= 8 && this.selectedPrinter && !this.isProcessing) {
        // Add small delay to prevent multiple rapid calls
        this.autoProcessTimer = setTimeout(() => {
          if (!this.isProcessing) {
            this.processPrintScan();
          }
        }, 300); // 300ms delay
      }
    },

    onReprintSearchInput() {
      // Clear any existing timer
      if (this.autoSearchTimer) {
        clearTimeout(this.autoSearchTimer);
      }
      
      // Auto-search if in auto mode and input looks complete (optimized with debouncing)
      if (!this.isManualMode && this.reprintSearchTerm.length >= 5 && this.reprintSelectedPrinter && !this.isProcessing) {
        // Add small delay to prevent multiple rapid calls
        this.autoSearchTimer = setTimeout(() => {
          if (!this.isProcessing) {
            this.searchForReprint();
          }
        }, 300); // 300ms delay
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

      console.time('searchForReprint');
      this.$refs.scannerComponent.startLoading('Searching for product...');
      this.isProcessing = true;
      
      try {
        // Add timeout to search request
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 15000); // 15 second timeout
        
        const response = await fetch('/api/printer/search-for-reprint', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({
            search_term: this.reprintSearchTerm.trim()
          }),
          signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        console.timeEnd('searchForReprint');
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
          this.reprintProductInfo = data.product_data;
          this.selectedLabelType = null; // Reset label selection
          this.clearCompatibilityWarning(); // Clear any warnings
          console.log('Product found for reprint:', this.reprintProductInfo);
        } else {
          this.reprintProductInfo = null;
          this.selectedLabelType = null;
          this.clearCompatibilityWarning();
          this.showError(data.message || 'Product not found');
        }
        
      } catch (error) {
        console.timeEnd('searchForReprint');
        
        if (error.name === 'AbortError') {
          this.showError('Search timed out - please try again');
        } else {
          console.error('Search error:', error);
          this.showError('Search failed: ' + error.message);
        }
        
        this.reprintProductInfo = null;
        this.selectedLabelType = null;
        this.clearCompatibilityWarning();
      } finally {
        this.$refs.scannerComponent.stopLoading();
        this.isProcessing = false;
      }
    },

    /**
     * Process reprint with STRICT compatibility enforcement
     */
    async processReprint() {
      if (!this.selectedLabelType) {
        this.showError('Please select a label type to reprint');
        return;
      }
      
      if (!this.reprintProductInfo) {
        this.showError('No product selected for reprint');
        return;
      }

      // STRICT: Final compatibility check before proceeding
      if (this.compatibilityWarning && this.compatibilityWarning.type === 'error') {
        this.showError('Please resolve printer compatibility issues before reprinting');
        return;
      }

      const printerInfo = this.reprintSelectedPrinterInfo;
      const labelTypeName = this.selectedLabelTypeName;
      
      this.$refs.scannerComponent.startLoading(`Reprinting ${labelTypeName}...`);
      this.isProcessing = true;
      
      try {
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
            printer_name: printerInfo ? printerInfo.printername_short : 'Unknown',
            search_term: this.reprintSearchTerm
          })
        });
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
          this.handleEnhancedReprintSuccess(result);
        } else {
          // Check if it's a compatibility error with suggestions
          if (result.available_printers && result.available_printers.length > 0) {
            this.handleCompatibilityError(result);
          } else {
            this.handleReprintError(result.message || 'Reprint failed');
          }
        }
        
      } catch (error) {
        console.error('Reprint error:', error);
        this.handleReprintError('Reprint failed: ' + error.message);
      } finally {
        this.$refs.scannerComponent.stopLoading();
        this.isProcessing = false;
      }
    },

    /**
     * Handle enhanced reprint success with routing information
     */
    handleEnhancedReprintSuccess(result) {
      const labelTypeName = this.selectedLabelTypeName;
      let successMessage = `${labelTypeName} reprinted successfully`;
      
      // Add routing information if applicable
      if (result.printer_info && result.printer_info.was_routed) {
        successMessage += ` (Smart routed to ${result.printer_info.target_printer})`;
      } else if (result.printer_info) {
        successMessage += ` to ${result.printer_info.target_printer || result.printer_info.selected_printer}`;
      }
      
      // Add to scanner success
      this.$refs.scannerComponent.addSuccessScan({
        serial_number: this.reprintSearchTerm,
        status: `Reprinted: ${labelTypeName} → ${result.printer_info?.target_printer || 'Printer'}`,
        timestamp: new Date().toISOString()
      });
      
      // Show success notification
      this.$refs.scannerComponent.showScanSuccess(successMessage);
      
      // Play success sound
      SoundService.successScan(false);
      
      // Clear form
      this.clearReprintForm();
      
      console.log('Enhanced reprint completed:', result);
    },

    /**
     * Handle compatibility error with printer suggestions
     */
    handleCompatibilityError(result) {
      this.compatibilityWarning = {
        message: result.message,
        type: 'error'
      };
      
      this.suggestedPrinters = result.available_printers || [];
      this.showPrinterSuggestions = this.suggestedPrinters.length > 0;
      
      // Show error notification
      this.$refs.scannerComponent.showScanError(result.message);
      
      // Play error sound
      SoundService.error(true);
      
      console.error('Compatibility error with suggestions:', result);
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

    /**
     * Clear reprint form with compatibility warnings
     */
    clearReprintForm() {
      this.reprintSearchTerm = '';
      this.reprintProductInfo = null;
      this.selectedLabelType = null;
      this.clearCompatibilityWarning();
      this.focusReprintInput();
    },

    getSerialNumbers(product) {
      const serials = [];
      if (product.serialnumber) serials.push(`A: ${product.serialnumber}`);
      if (product.serialnumberb) serials.push(`B: ${product.serialnumberb}`);
      if (product.serialnumberc) serials.push(`C: ${product.serialnumberc}`);
      if (product.serialnumberd) serials.push(`D: ${product.serialnumberd}`);
      return serials.length > 0 ? serials.join(', ') : 'N/A';
    },
    
    // Enhanced print processing with married printer support AND VALIDATION CHECK
    async processPrintScan() {
      if (!this.serialNumber.trim()) {
        this.showError('Please enter a serial number');
        return;
      }
      
      if (!this.selectedPrinter) {
        this.showError('Please select a printer first');
        return;
      }
      
      const printerInfo = this.selectedPrinterInfo;
      const loadingMessage = printerInfo && printerInfo.is_married ? 
        'Checking database and preparing married printers...' : 
        'Checking database...';
      
      console.log('Starting print process:', {
        serial_number: this.serialNumber,
        printer_id: this.selectedPrinter,
        printer_info: printerInfo
      });
      
      // Show loading state
      this.$refs.scannerComponent.startLoading(loadingMessage);
      this.isProcessing = true;
      
      try {
        // Check database with your conditions
        console.log('Checking print conditions...');
        const result = await this.checkPrintConditions(this.serialNumber);
        
        console.log('Print conditions result:', result);
        
        if (result.success) {
          // Print the label with enhanced married printer support
          console.log('Print conditions passed, attempting to print...');
          const printResult = await this.printLabel(result.data);
          console.log('Print completed successfully:', printResult);
          this.handlePrintSuccess(result.data);
        } else {
          // NEW: Check if it's a validation issue
          if (result.requires_confirmation) {
            console.log('Item not validated:', result.message);
            this.handleValidationError(result.message, result.product_data);
          } else {
            console.log('Print conditions failed:', result.message);
            this.handlePrintError(result.message);
          }
        }
        
      } catch (error) {
        console.error('Print processing error details:', {
          error: error.message,
          stack: error.stack,
          serial_number: this.serialNumber,
          printer_id: this.selectedPrinter
        });
        
        // Provide more specific error messages
        let errorMessage = 'Database error occurred';
        if (error.message.includes('HTTP error! status: 500')) {
          errorMessage = 'Server error occurred. Please check the application logs and try again.';
        } else if (error.message.includes('Print service error:')) {
          errorMessage = error.message;
        } else if (error.message.includes('Network')) {
          errorMessage = 'Network connection error. Please check your connection and try again.';
        }
        
        this.handlePrintError(errorMessage);
      } finally {
        this.$refs.scannerComponent.stopLoading();
        this.isProcessing = false;
        this.clearSerial();
      }
    },
    
    async checkPrintConditions(serialNumber) {
      try {
        console.time('checkPrintConditions');
        
        // Use the existing API endpoint with timeout
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
        
        const response = await fetch('/api/printer/check-serial', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({
            serial_number: serialNumber
          }),
          signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        console.timeEnd('checkPrintConditions');
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        // The API returns meets_print_conditions boolean AND requires_confirmation flag
        if (data.success && data.meets_print_conditions) {
          return {
            success: true,
            data: data
          };
        } else {
          return {
            success: false,
            message: data.message || 'Item not ready for printing',
            requires_confirmation: data.requires_confirmation || false,
            product_data: data.product_data || null
          };
        }
        
      } catch (error) {
        console.timeEnd('checkPrintConditions');
        if (error.name === 'AbortError') {
          console.error('Print conditions check timed out');
          return {
            success: false,
            message: 'Database check timed out - please try again'
          };
        }
        
        console.error('Database check error:', error);
        return {
          success: false,
          message: 'Database connection failed'
        };
      }
    },
    
    async printLabel(data) {
      try {
        console.time('printLabel');
        
        const printerInfo = this.selectedPrinterInfo;
        const printerName = printerInfo ? printerInfo.printername_short : 'Unknown Printer';
        
        // Enhanced logging for married printers
        if (printerInfo && printerInfo.is_married) {
          console.log('Printing with married printer system:', {
            primary_printer: printerName,
            married_to: printerInfo.married_printer.name,
            marriage_name: printerInfo.marriage_name
          });
        }
        
        console.log('Sending print request with data:', {
          serial_number: this.serialNumber,
          printer_id: this.selectedPrinter,
          printer_name: printerName,
          print_data_keys: Object.keys(data),
          data_size: JSON.stringify(data).length
        });
        
        // Add timeout to print request
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout for printing
        
        // Use the updated API endpoint that supports married printers
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
          }),
          signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        console.timeEnd('printLabel');
        
        // Log the response details
        console.log('Print API response status:', response.status);
        
        if (!response.ok) {
          // Try to get error details from response
          let errorMessage = `HTTP error! status: ${response.status}`;
          try {
            const errorText = await response.text();
            console.error('Print API error response body:', errorText);
            
            // Try to parse as JSON first
            try {
              const errorJson = JSON.parse(errorText);
              errorMessage = errorJson.message || errorMessage;
            } catch (jsonError) {
              // If not JSON, use the text directly if it's useful
              if (errorText.length > 0 && errorText.length < 200) {
                errorMessage += ': ' + errorText;
              }
            }
          } catch (textError) {
            console.error('Could not read error response body:', textError);
          }
          
          throw new Error(errorMessage);
        }
        
        const result = await response.json();
        console.log('Print API success response:', result);
        
        if (!result.success) {
          throw new Error(result.message || 'Print failed');
        }
        
        return result;
        
      } catch (error) {
        console.timeEnd('printLabel');
        
        if (error.name === 'AbortError') {
          console.error('Print request timed out');
          throw new Error('Print request timed out - please try again');
        }
        
        console.error('Print service error details:', {
          error: error.message,
          stack: error.stack,
          serial_number: this.serialNumber,
          printer_id: this.selectedPrinter
        });
        throw new Error('Print service error: ' + error.message);
      }
    },
    
    handlePrintSuccess(data) {
      const printerInfo = this.selectedPrinterInfo;
      let statusMessage = 'Printed';
      
      // Enhanced success message for married printers
      if (printerInfo && printerInfo.is_married) {
        statusMessage = `Printed to married printers`;
        if (printerInfo.marriage_name) {
          statusMessage += ` (${printerInfo.marriage_name})`;
        }
      } else {
        statusMessage = `Printed to ${printerInfo.printername_short}`;
      }
      
      // Add to scanner success
      this.$refs.scannerComponent.addSuccessScan({
        serial_number: this.serialNumber,
        status: statusMessage,
        timestamp: new Date().toISOString()
      });
      
      // Show enhanced success notification
      const successMsg = printerInfo && printerInfo.is_married ? 
        `Labels printed to married printer system successfully!` :
        `Labels printed to ${printerInfo.printername_short} successfully!`;
      
      this.$refs.scannerComponent.showScanSuccess(successMsg);
      
      // Play success sound
      SoundService.successScan(false);
      
      console.log('Label printed successfully for:', this.serialNumber);
    },
    
    // NEW: Handle validation errors separately with clear messaging
    handleValidationError(message, productData) {
      // Add to scanner error with validation-specific styling
      this.$refs.scannerComponent.addErrorScan({
        serial_number: this.serialNumber,
        status: 'Not Validated'
      }, message);
      
      // Show clear validation error notification at the top
      this.$refs.scannerComponent.showScanError('❌ Item Not Validated - ' + message);
      
      // Play error sound
      SoundService.error(true);
      
      console.warn('Validation check failed:', {
        message,
        product_data: productData
      });
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
      // Clean up timers
      if (this.autoProcessTimer) {
        clearTimeout(this.autoProcessTimer);
      }
      if (this.autoSearchTimer) {
        clearTimeout(this.autoSearchTimer);
      }
      
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
  },
  
  // Add cleanup when component is destroyed
  beforeUnmount() {
    if (this.autoProcessTimer) {
      clearTimeout(this.autoProcessTimer);
    }
    if (this.autoSearchTimer) {
      clearTimeout(this.autoSearchTimer);
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

/* Marriage Info Styling */
.marriage-info {
  background-color: #fff3cd;
  border: 1px solid #ffeaa7;
  border-radius: 6px;
  padding: 10px 12px;
  margin-top: 8px;
}

.marriage-details {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  color: #856404;
}

.marriage-details i {
  color: #dc3545;
}

.marriage-text {
  color: #495057;
}

.marriage-name {
  color: #6c757d;
  font-style: italic;
}

.marriage-description {
  margin-top: 5px;
  font-size: 13px;
  color: #6c757d;
  font-style: italic;
}

.smart-routing-info {
  margin-top: 8px;
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #0056b3;
}

/* Enhanced compatibility and routing styles */
.has-warning {
  border-color: #dc3545 !important;
  box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.2) !important;
}

.compatibility-warning {
  margin-top: 10px;
  padding: 12px;
  border-radius: 6px;
  border-left: 4px solid;
}

.compatibility-warning.error {
  background-color: #fff5f5;
  border-left-color: #e53e3e;
  color: #742a2a;
  border: 2px solid #feb2b2;
}

.strict-enforcement-note {
  margin-top: 10px;
  padding: 8px;
  background-color: #fed7d7;
  border-radius: 4px;
  font-size: 13px;
  border-left: 3px solid #e53e3e;
}

.compatibility-warning.info {
  background-color: #f0f9ff;
  border-left-color: #3182ce;
  color: #2a69ac;
}

.warning-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  margin-bottom: 6px;
}

.warning-message {
  margin: 0;
  font-size: 14px;
  line-height: 1.4;
}

.printer-suggestions {
  background-color: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 8px;
  padding: 15px;
  margin-top: 10px;
}

.printer-suggestions h4 {
  margin: 0 0 12px 0;
  color: #495057;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.printer-suggestions h4 i {
  color: #ffc107;
}

.suggested-printer-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.suggested-printer-btn {
  background-color: #007bff;
  color: white;
  border: none;
  border-radius: 6px;
  padding: 8px 12px;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 6px;
}

.suggested-printer-btn:hover {
  background-color: #0056b3;
  transform: translateY(-1px);
}

.smart-routing-display {
  background-color: #e7f3ff;
  border: 1px solid #b3d9ff;
  border-radius: 6px;
  padding: 10px;
  margin-top: 10px;
}

.routing-indicator {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.routing-message {
  font-size: 14px;
  color: #0056b3;
  font-weight: 500;
}

.target-printer {
  font-size: 13px;
  color: #495057;
  margin-left: 24px;
}

.smart-routed-indicator {
  font-size: 12px;
  color: #28a745;
  font-weight: normal;
  margin-left: 8px;
}

.married-print-indicator {
  font-size: 12px;
  color: #dc3545;
  font-weight: normal;
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

.married-printer {
  background-color: #fff3cd !important;
  color: #856404 !important;
}

.marriage-indicator {
  margin-left: 5px;
  font-size: 12px;
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

.submit-button.has-compatibility-warning {
  background-color: #6c757d;
  cursor: not-allowed;
}

.submit-button.has-compatibility-warning:hover {
  background-color: #6c757d;
  transform: none;
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
  
  .input-group input,
  .printer-select,
  .label-type-select {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    padding: 16px 15px;
    font-size: 16px;
    border-radius: 8px;
    border: 2px solid #ddd;
    min-height: 54px;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    word-wrap: break-word;
    overflow-wrap: break-word;
  }
  
  .input-group input::placeholder {
    font-size: 14px;
    color: #999;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  
  #reprintSearch {
    font-size: 16px !important;
  }
  
  #reprintSearch::placeholder {
    font-size: 13px;
    line-height: 1.2;
  }
  
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

  .marriage-info {
    padding: 8px 10px;
    margin-top: 6px;
  }

  .marriage-details {
    flex-wrap: wrap;
    gap: 4px;
    font-size: 13px;
  }

  .smart-routing-info {
    margin-top: 6px;
    font-size: 12px;
  }

  .printer-suggestions {
    padding: 12px;
  }
  
  .suggested-printer-list {
    flex-direction: column;
  }
  
  .suggested-printer-btn {
    width: 100%;
    justify-content: center;
    padding: 12px;
    font-size: 14px;
  }
  
  .smart-routing-display {
    padding: 8px;
  }
  
  .routing-indicator {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
  }
  
  .target-printer {
    margin-left: 0;
  }
  
  .warning-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
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

  .marriage-info {
    padding: 6px 8px;
  }

  .marriage-details {
    font-size: 12px;
  }

  .smart-routing-info {
    font-size: 11px;
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