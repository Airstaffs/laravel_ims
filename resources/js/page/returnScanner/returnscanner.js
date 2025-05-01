import { eventBus } from '../../components/eventBus';
import ScannerComponent from '../../components/Scanner.vue';
import { SoundService } from '../../components/Sound_service';
import '../../../css/modules.css';
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: 'ReturnScannerModule',
    components: {
        // Import your existing scanner component
        ScannerComponent
    },
    data() {
        return {
            stores: [],
            selectedStore: '',
            
            // Scanner data
            serialNumber: '',
            fnsku: '',
            returnReason: '',
            otherReason: '',
            showManualInput: false,
            
            // Return history
            returnHistory: [],
            
            // For auto verification
            autoVerifyTimeout: null,
        };
    },
    methods: {
        // Open scanner modal
        openScannerModal() {
            this.$refs.scanner.openScannerModal();
        },
        
        // Format date for display
        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        },
        
        // Format status for display
        formatStatus(status) {
            const statusMap = {
                'pending': 'Pending',
                'processed': 'Processed',
                'rejected': 'Rejected',
                'missing': 'Not Found'
            };
            return statusMap[status] || status;
        },
        
        // View return details
        viewReturnDetails(item) {
            alert(`Return Details for ${item.serial_number || item.fnsku}\nStatus: ${this.formatStatus(item.status)}\nReason: ${item.reason}\nDate: ${this.formatDate(item.created_at)}`);
        },
        
        // Store dropdown functions
        async fetchStores() {
            try {
                const response = await axios.get('/api/returns/stores', {
                    withCredentials: true
                });
                this.stores = response.data;
            } catch (error) {
                console.error("Error fetching stores:", error);
            }
        },
        
        changeStore() {
            this.fetchReturnHistory();
        },
        
        // Fetch return history
        async fetchReturnHistory() {
            try {
                const response = await axios.get('/api/returns/history', {
                    params: { 
                        store: this.selectedStore
                    },
                    withCredentials: true
                });

                this.returnHistory = response.data || [];
            } catch (error) {
                console.error("Error fetching return history:", error);
            }
        },
        
        // Input handlers
        handleSerialInput() {
            // Handle serial number input like in stockroom
            if (this.serialNumber.trim().length > 5 && !this.showManualInput) {
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }
                
                this.autoVerifyTimeout = setTimeout(() => {
                    // Play success sound (if you have SoundService)
                    if (window.SoundService && window.SoundService.success) {
                        window.SoundService.success();
                    }
                    
                    // Focus on next field
                    this.focusNextField('fnskuInput');
                }, 500);
            }
        },
        
        handleFnskuInput() {
            if (this.fnsku.trim().length > 5 && !this.showManualInput) {
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }
                
                this.autoVerifyTimeout = setTimeout(() => {
                    // Play success sound
                    if (window.SoundService && window.SoundService.success) {
                        window.SoundService.success();
                    }
                    
                    // Focus on reason field
                    this.focusNextField('reasonSelect');
                }, 500);
            }
        },
        
        handleReasonChange() {
            if (this.returnReason === 'other') {
                this.$nextTick(() => {
                    this.$refs.otherReasonInput.focus();
                });
            } else if (this.returnReason && !this.showManualInput) {
                // If a valid reason is selected and we're in auto mode, process the scan
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }
                
                this.autoVerifyTimeout = setTimeout(() => {
                    this.processScan();
                }, 500);
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
        
        // Process the scan
        async processScan(scannedCode = null) {
            try {
                // Basic validation
                if (!this.serialNumber && !this.fnsku) {
                    this.$refs.scanner.showScanError("Serial Number or FNSKU is required");
                    if (window.SoundService && window.SoundService.error) {
                        window.SoundService.error();
                    }
                    return;
                }
                
                if (!this.returnReason) {
                    this.$refs.scanner.showScanError("Return reason is required");
                    if (window.SoundService && window.SoundService.error) {
                        window.SoundService.error();
                    }
                    return;
                }
                
                // Get the final reason (either selected or other)
                const finalReason = this.returnReason === 'other' ? this.otherReason : this.returnReason;
                
                if (this.returnReason === 'other' && !this.otherReason) {
                    this.$refs.scanner.showScanError("Please specify the return reason");
                    if (window.SoundService && window.SoundService.error) {
                        window.SoundService.error();
                    }
                    return;
                }
                
                // Show loading state
                this.$refs.scanner.startLoading('Processing Return Scan');
                
                // Get images from scanner (if any)
                const imageData = this.$refs.scanner.capturedImages.map(img => img.data);
                
                // Prepare scan data
                const scanData = {
                    SerialNumber: this.serialNumber,
                    FNSKU: this.fnsku,
                    Reason: finalReason,
                    Store: this.selectedStore,
                    Images: imageData
                };
                
                // Send to API
                const response = await axios.post('/api/returns/process-scan', scanData, {
                    withCredentials: true,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    }
                });
                
                // Hide loading
                this.$refs.scanner.stopLoading();
                
                if (response.data.success) {
                    // Success case
                    this.$refs.scanner.showScanSuccess(response.data.message || 'Return processed successfully');
                    if (window.SoundService && window.SoundService.successScan) {
                        window.SoundService.successScan(true);
                    }
                    
                    // Add to scan history in the scanner
                    this.$refs.scanner.addSuccessScan({
                        Serial: this.serialNumber,
                        FNSKU: this.fnsku,
                        Reason: finalReason
                    });
                    
                    // Refresh the return history table
                    this.fetchReturnHistory();
                    
                } else {
                    // Error case
                    this.$refs.scanner.showScanError(response.data.message || 'Error processing return');
                    if (window.SoundService && window.SoundService.scanRejected) {
                        window.SoundService.scanRejected(true);
                    }
                }
                
                // Clear input fields
                this.serialNumber = '';
                this.fnsku = '';
                this.returnReason = '';
                this.otherReason = '';
                
                // Reset focus to first field
                this.focusNextField('serialNumberInput');
                
            } catch (error) {
                console.error('Error processing return scan:', error);
                this.$refs.scanner.stopLoading();
                this.$refs.scanner.showScanError('Network or server error');
                
                if (window.SoundService && window.SoundService.scanRejected) {
                    window.SoundService.scanRejected(true);
                }
            }
        },
        
        // Scanner event handlers
        handleScanProcess() {
            this.processScan();
        },
        
        handleHardwareScan(scannedCode) {
            // For hardware scanner, just put the code in the FNSKU field
            this.fnsku = scannedCode;
            this.focusNextField('reasonSelect');
        },
        
        handleModeChange(event) {
            this.showManualInput = event.manual;
        },
        
        handleScannerOpened() {
            this.showManualInput = this.$refs.scanner.showManualInput;
            
            // Reset fields
            this.serialNumber = '';
            this.fnsku = '';
            this.returnReason = '';
            this.otherReason = '';
            
            // Focus on first field
            this.$nextTick(() => {
                if (this.$refs.serialNumberInput) {
                    this.$refs.serialNumberInput.focus();
                }
            });
        },
        
        handleScannerClosed() {
            // Refresh data when scanner is closed
            this.fetchReturnHistory();
        },
        
        handleScannerReset() {
            // Reset fields when scanner is reset
            this.serialNumber = '';
            this.fnsku = '';
            this.returnReason = '';
            this.otherReason = '';
        }
    },
    mounted() {
        // Set up axios defaults
        axios.defaults.baseURL = window.location.origin;
        axios.defaults.withCredentials = true;
        
        // Set CSRF token
        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) {
            axios.defaults.headers.common['X-CSRF-TOKEN'] = token.getAttribute('content');
        }
        
        // Fetch stores for dropdown
        this.fetchStores();
        
        // Fetch initial return history
        this.fetchReturnHistory();
    },
    beforeUnmount() {
        // Clear any timeouts
        if (this.autoVerifyTimeout) {
            clearTimeout(this.autoVerifyTimeout);
        }
    }
}