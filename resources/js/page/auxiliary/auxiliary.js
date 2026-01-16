import axios from 'axios';
import Swal from 'sweetalert2';

const API_BASE_URL = import.meta.env.VITE_API_URL;
const LAST_PRINTER_KEY = 'auxiliary_last_printer';

export default {
    data() {
        return {
            // State
            auxiliaries: [],
            printers: [],
            loading: false,
            error: null,

            // Upload Modal
            showUploadModal: false,
            uploading: false,
            uploadForm: {
                auxname: '',
                auxcode: '',
                image: null
            },
            imagePreview: null,
            // Validation warnings
            uploadWarnings: {
                nameExists: false,
                codeExists: false
            },

            // Edit Modal
            showEditModal: false,
            updating: false,
            editForm: {
                id: null,
                auxname: '',
                auxcode: ''
            },
            // Edit validation warnings
            editWarnings: {
                nameExists: false,
                codeExists: false
            },

            // Print Modal
            showPrintModal: false,
            printing: false,
            printForm: {
                id: null,
                auxname: '',
                auximgname: '',
                quantity: 1,
                printerId: null,
                printerName: ''
            },

            // Delete Dialog
            showDeleteDialog: false,
            deleting: false,
            deleteTarget: null,

            // Last selected printer
            lastPrinter: null,
        };
    },

    computed: {
        /**
         * Validate upload form
         */
        isUploadFormValid() {
            return this.uploadForm.auxname && 
                   this.uploadForm.auxname.trim().length >= 3 &&
                   this.uploadForm.auxcode && 
                   this.uploadForm.auxcode.trim().length >= 2 &&
                   this.uploadForm.image !== null &&
                   !this.uploadWarnings.nameExists &&
                   !this.uploadWarnings.codeExists;
        }
    },

    methods: {
        /**
         * Load last selected printer from localStorage
         */
        loadLastPrinter() {
            try {
                const savedPrinter = localStorage.getItem(LAST_PRINTER_KEY);
                if (savedPrinter) {
                    this.lastPrinter = JSON.parse(savedPrinter);
                    console.log('Loaded last printer:', this.lastPrinter);
                }
            } catch (err) {
                console.error('Error loading last printer:', err);
            }
        },

        /**
         * Save last selected printer to localStorage
         */
        saveLastPrinter(printer) {
            try {
                if (printer) {
                    localStorage.setItem(LAST_PRINTER_KEY, JSON.stringify(printer));
                    this.lastPrinter = printer;
                    console.log('Saved last printer:', printer);
                }
            } catch (err) {
                console.error('Error saving last printer:', err);
            }
        },

        /**
         * Apply last printer to all auxiliaries
         */
        applyLastPrinterToAll() {
            if (this.lastPrinter && this.auxiliaries.length > 0) {
                // Find the matching printer in the current printers list
                const matchingPrinter = this.printers.find(
                    p => p.printerid === this.lastPrinter.printerid
                );

                if (matchingPrinter) {
                    this.auxiliaries.forEach(aux => {
                        if (!aux.selectedPrinter) {
                            aux.selectedPrinter = matchingPrinter;
                        }
                    });
                    console.log('Applied last printer to all auxiliaries');
                }
            }
        },

        /**
         * Fetch all auxiliaries from server
         */
        async fetchAuxiliaries() {
            this.loading = true;
            this.error = null;
            
            try {
                const response = await axios.get(`${API_BASE_URL}/api/auxiliary/get-auxiliaries`);
                
                // Add default properties to each auxiliary
                this.auxiliaries = response.data.map(aux => ({
                    ...aux,
                    quantity: 1,
                    selectedPrinter: null,
                    isPrinting: false
                }));

                console.log('Fetched auxiliaries:', this.auxiliaries.length);

                // Apply last printer after loading auxiliaries
                this.$nextTick(() => {
                    this.applyLastPrinterToAll();
                });
            } catch (err) {
                console.error('Error fetching auxiliaries:', err);
                this.error = 'Failed to load auxiliaries';
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error Loading Data',
                    text: 'Failed to load auxiliaries',
                    confirmButtonColor: '#dc3545'
                });
            } finally {
                this.loading = false;
            }
        },

        /**
         * Fetch available printers (using auxiliary endpoint)
         */
        async fetchPrinters() {
            try {
                console.log('Fetching printers from auxiliary endpoint:', `${API_BASE_URL}/api/auxiliary/get-printers`);
                const response = await axios.get(`${API_BASE_URL}/api/auxiliary/get-printers`);
                
                console.log('Auxiliary Printers API response:', response.data);
                
                if (response.data.success) {
                    this.printers = response.data.printers;
                    console.log('Loaded auxiliary printers:', this.printers.length);
                    console.log('Printer details:', this.printers);

                    // Apply last printer after loading printers
                    this.$nextTick(() => {
                        this.applyLastPrinterToAll();
                    });
                } else {
                    console.warn('Auxiliary printers API returned unsuccessful response');
                }
            } catch (err) {
                console.error('Error fetching auxiliary printers:', err);
                console.error('Error details:', err.response?.data);
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error Loading Printers',
                    text: 'Failed to load printers',
                    confirmButtonColor: '#dc3545'
                });
            }
        },

        /**
         * Check if auxiliary name exists (for upload)
         */
        checkAuxNameExists() {
            const trimmedName = this.uploadForm.auxname?.trim() || '';
            
            if (trimmedName.length < 3) {
                this.uploadWarnings.nameExists = false;
                return;
            }

            const exists = this.auxiliaries.some(aux => 
                aux.auxname.toLowerCase() === trimmedName.toLowerCase()
            );
            
            this.uploadWarnings.nameExists = exists;

            if (exists) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Duplicate Name',
                    text: 'An auxiliary with this name already exists',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            }
        },

        /**
         * Check if auxiliary code exists (for upload)
         */
        checkAuxCodeExists() {
            const trimmedCode = this.uploadForm.auxcode?.trim() || '';
            
            if (trimmedCode.length < 2) {
                this.uploadWarnings.codeExists = false;
                return;
            }

            const exists = this.auxiliaries.some(aux => 
                aux.auxcode.toLowerCase() === trimmedCode.toLowerCase()
            );
            
            this.uploadWarnings.codeExists = exists;

            if (exists) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Duplicate Code',
                    text: 'An auxiliary with this code already exists',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            }
        },

        /**
         * Check if edit name exists (excluding current item)
         */
        checkEditNameExists() {
            const trimmedName = this.editForm.auxname?.trim() || '';
            
            if (trimmedName.length < 3) {
                this.editWarnings.nameExists = false;
                return;
            }

            const exists = this.auxiliaries.some(aux => 
                aux.id !== this.editForm.id &&
                aux.auxname.toLowerCase() === trimmedName.toLowerCase()
            );
            
            this.editWarnings.nameExists = exists;

            if (exists) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Duplicate Name',
                    text: 'An auxiliary with this name already exists',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            }
        },

        /**
         * Check if edit code exists (excluding current item)
         */
        checkEditCodeExists() {
            const trimmedCode = this.editForm.auxcode?.trim() || '';
            
            if (trimmedCode.length < 2) {
                this.editWarnings.codeExists = false;
                return;
            }

            const exists = this.auxiliaries.some(aux => 
                aux.id !== this.editForm.id &&
                aux.auxcode.toLowerCase() === trimmedCode.toLowerCase()
            );
            
            this.editWarnings.codeExists = exists;

            if (exists) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Duplicate Code',
                    text: 'An auxiliary with this code already exists',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            }
        },

        /**
         * Get image URL for display
         */
        getImageUrl(imageName) {
            return `/images/auxiliary/${imageName}`;
        },

        /**
         * Handle image loading errors
         */
        handleImageError(event) {
            event.target.src = '/images/default-placeholder.png';
        },

        /**
         * Increment quantity
         */
        incrementQuantity(id) {
            const aux = this.auxiliaries.find(a => a.id === id);
            if (aux && aux.quantity < 999) {
                aux.quantity++;
            }
        },

        /**
         * Decrement quantity
         */
        decrementQuantity(id) {
            const aux = this.auxiliaries.find(a => a.id === id);
            if (aux && aux.quantity > 1) {
                aux.quantity--;
            }
        },

        /**
         * Validate quantity input
         */
        validateQuantity(aux) {
            if (aux.quantity > 999) {
                aux.quantity = 999;
                Swal.fire({
                    icon: 'warning',
                    title: 'Maximum Quantity',
                    text: 'Maximum quantity is 999',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true
                });
            } else if (aux.quantity < 1 || isNaN(aux.quantity)) {
                aux.quantity = 1;
            }
        },

        /**
         * Validate aux name input (with duplicate check)
         */
        validateAuxName() {
            this.uploadForm.auxname = this.uploadForm.auxname.trim();
            
            // Debounce duplicate check
            clearTimeout(this.auxNameTimeout);
            this.auxNameTimeout = setTimeout(() => {
                this.checkAuxNameExists();
            }, 500);
        },

        /**
         * Validate aux code input (with duplicate check)
         */
        validateAuxCode() {
            this.uploadForm.auxcode = this.uploadForm.auxcode.trim();
            
            // Debounce duplicate check
            clearTimeout(this.auxCodeTimeout);
            this.auxCodeTimeout = setTimeout(() => {
                this.checkAuxCodeExists();
            }, 500);
        },

        /**
         * Validate edit name (with duplicate check)
         */
        validateEditName() {
            this.editForm.auxname = this.editForm.auxname.trim();
            
            // Debounce duplicate check
            clearTimeout(this.editNameTimeout);
            this.editNameTimeout = setTimeout(() => {
                this.checkEditNameExists();
            }, 500);
        },

        /**
         * Validate edit code (with duplicate check)
         */
        validateEditCode() {
            this.editForm.auxcode = this.editForm.auxcode.trim();
            
            // Debounce duplicate check
            clearTimeout(this.editCodeTimeout);
            this.editCodeTimeout = setTimeout(() => {
                this.checkEditCodeExists();
            }, 500);
        },

        /**
         * Open upload modal
         */
        openUploadModal() {
            this.showUploadModal = true;
            this.uploadForm = {
                auxname: '',
                auxcode: '',
                image: null
            };
            this.imagePreview = null;
            this.uploadWarnings = {
                nameExists: false,
                codeExists: false
            };
            
            // Reset file input
            if (this.$refs.fileInput) {
                this.$refs.fileInput.value = '';
            }
        },

        /**
         * Close upload modal
         */
        closeUploadModal() {
            this.showUploadModal = false;
            this.uploadForm = {
                auxname: '',
                auxcode: '',
                image: null
            };
            this.imagePreview = null;
            this.uploadWarnings = {
                nameExists: false,
                codeExists: false
            };
            
            // Clear timeouts
            clearTimeout(this.auxNameTimeout);
            clearTimeout(this.auxCodeTimeout);
            
            // Reset file input
            if (this.$refs.fileInput) {
                this.$refs.fileInput.value = '';
            }
        },

        /**
         * Handle file selection
         */
        handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                this.uploadForm.image = file;
                
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.imagePreview = e.target.result;
                };
                reader.readAsDataURL(file);
            } else {
                this.uploadForm.image = null;
                this.imagePreview = null;
            }
        },

        /**
         * Upload new auxiliary
         */
        async uploadAuxiliary() {
            // Trim values before validation
            this.uploadForm.auxname = this.uploadForm.auxname?.trim() || '';
            this.uploadForm.auxcode = this.uploadForm.auxcode?.trim() || '';

            // Validate
            if (!this.uploadForm.auxname || this.uploadForm.auxname.length < 3) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Validation Error',
                    text: 'Auxiliary name must be at least 3 characters',
                    confirmButtonColor: '#ffc107'
                });
                return;
            }

            if (!this.uploadForm.auxcode || this.uploadForm.auxcode.length < 2) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Validation Error',
                    text: 'Auxiliary code must be at least 2 characters',
                    confirmButtonColor: '#ffc107'
                });
                return;
            }

            if (!this.uploadForm.image) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Validation Error',
                    text: 'Please select an image',
                    confirmButtonColor: '#ffc107'
                });
                return;
            }

            // Check for duplicates one more time before upload
            if (this.uploadWarnings.nameExists || this.uploadWarnings.codeExists) {
                Swal.fire({
                    icon: 'error',
                    title: 'Duplicate Entry',
                    text: 'Name or code already exists. Please use different values.',
                    confirmButtonColor: '#dc3545'
                });
                return;
            }

            this.uploading = true;

            try {
                const formData = new FormData();
                formData.append('auxname', this.uploadForm.auxname);
                formData.append('auxcode', this.uploadForm.auxcode);
                formData.append('image', this.uploadForm.image);

                const response = await axios.post(`${API_BASE_URL}/api/auxiliary/add-auxiliary`, formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                });

                if (response.data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Upload Successful!',
                        text: 'Auxiliary uploaded successfully',
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    });
                    
                    this.closeUploadModal();
                    this.fetchAuxiliaries();
                } else {
                    throw new Error(response.data.message || 'Upload failed');
                }
            } catch (err) {
                console.error('Upload error:', err);
                
                Swal.fire({
                    icon: 'error',
                    title: 'Upload Failed!',
                    text: err.response?.data?.message || 'Failed to upload auxiliary',
                    confirmButtonColor: '#dc3545'
                });
            } finally {
                this.uploading = false;
            }
        },

        /**
         * Open edit modal
         */
        openEditModal(aux) {
            this.editForm = {
                id: aux.id,
                auxname: aux.auxname,
                auxcode: aux.auxcode
            };
            this.editWarnings = {
                nameExists: false,
                codeExists: false
            };
            this.showEditModal = true;
        },

        /**
         * Close edit modal
         */
        closeEditModal() {
            this.showEditModal = false;
            this.editForm = {
                id: null,
                auxname: '',
                auxcode: ''
            };
            this.editWarnings = {
                nameExists: false,
                codeExists: false
            };
            
            // Clear timeouts
            clearTimeout(this.editNameTimeout);
            clearTimeout(this.editCodeTimeout);
        },

        /**
         * Update auxiliary
         */
        async updateAuxiliary() {
            // Trim values
            this.editForm.auxname = this.editForm.auxname?.trim() || '';
            this.editForm.auxcode = this.editForm.auxcode?.trim() || '';

            if (!this.editForm.auxname || !this.editForm.auxcode) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Validation Error',
                    text: 'Please fill all fields',
                    confirmButtonColor: '#ffc107'
                });
                return;
            }

            // Check for duplicates
            if (this.editWarnings.nameExists || this.editWarnings.codeExists) {
                Swal.fire({
                    icon: 'error',
                    title: 'Duplicate Entry',
                    text: 'Name or code already exists. Please use different values.',
                    confirmButtonColor: '#dc3545'
                });
                return;
            }

            this.updating = true;

            try {
                const response = await axios.post(
                    `${API_BASE_URL}/api/auxiliary/update-auxiliary/${this.editForm.id}`,
                    {
                        auxname: this.editForm.auxname,
                        auxcode: this.editForm.auxcode
                    }
                );

                if (response.data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Updated!',
                        text: 'Auxiliary updated successfully',
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    });
                    
                    this.closeEditModal();
                    this.fetchAuxiliaries();
                } else {
                    throw new Error(response.data.message || 'Update failed');
                }
            } catch (err) {
                console.error('Update error:', err);
                
                Swal.fire({
                    icon: 'error',
                    title: 'Update Failed!',
                    text: err.response?.data?.message || 'Failed to update auxiliary',
                    confirmButtonColor: '#dc3545'
                });
            } finally {
                this.updating = false;
            }
        },

        /**
         * Open print modal
         */
        openPrintModal(aux) {
            if (!aux.selectedPrinter) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Printer Selected',
                    text: 'Please select a printer first',
                    confirmButtonColor: '#ffc107'
                });
                return;
            }

            this.printForm = {
                id: aux.id,
                auxname: aux.auxname,
                auximgname: aux.auximgname,
                quantity: aux.quantity,
                printerId: aux.selectedPrinter.printerid,
                printerName: aux.selectedPrinter.printername_short
            };
            this.showPrintModal = true;
        },

        /**
         * Close print modal
         */
        closePrintModal() {
            this.showPrintModal = false;
            this.printForm = {
                id: null,
                auxname: '',
                auximgname: '',
                quantity: 1,
                printerId: null,
                printerName: ''
            };
        },

        /**
         * Print auxiliary labels
         */
        async printAuxiliary() {
            this.printing = true;

            // Find the auxiliary being printed and set its printing state
            const aux = this.auxiliaries.find(a => a.id === this.printForm.id);
            if (aux) {
                aux.isPrinting = true;
            }

            try {
                const response = await axios.post(`${API_BASE_URL}/api/auxiliary/print-auxiliary`, {
                    image_name: this.printForm.auximgname,
                    quantity: this.printForm.quantity,
                    printer_id: this.printForm.printerId
                });

                if (response.data.success) {
                    // Close modal first
                    this.closePrintModal();
                    
                    // Show SweetAlert success
                    Swal.fire({
                        icon: 'success',
                        title: 'Print Successful!',
                        html: `
                            <div style="text-align: center;">
                                <p style="font-size: 18px; margin: 10px 0;">
                                    <strong>${this.printForm.quantity}</strong> label(s) sent to printer
                                </p>
                                <p style="color: #6c757d; margin: 5px 0;">
                                    ${this.printForm.printerName}
                                </p>
                            </div>
                        `,
                        timer: 3000,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        customClass: {
                            popup: 'animated-popup'
                        }
                    });
                } else {
                    throw new Error(response.data.message || 'Print failed');
                }
            } catch (err) {
                console.error('Print error:', err);
                
                // Close modal
                this.closePrintModal();
                
                // Show SweetAlert error
                Swal.fire({
                    icon: 'error',
                    title: 'Print Failed!',
                    html: `
                        <div style="text-align: center;">
                            <p style="font-size: 16px; margin: 10px 0;">
                                ${err.response?.data?.message || 'Failed to send print job'}
                            </p>
                            <p style="color: #6c757d; font-size: 14px; margin: 5px 0;">
                                Please check your printer connection and try again
                            </p>
                        </div>
                    `,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#dc3545'
                });
            } finally {
                this.printing = false;
                if (aux) {
                    aux.isPrinting = false;
                }
            }
        },

        /**
         * Confirm delete
         */
        confirmDelete(aux) {
            this.deleteTarget = aux;
            this.showDeleteDialog = true;
        },

        /**
         * Close delete dialog
         */
        closeDeleteDialog() {
            this.showDeleteDialog = false;
            this.deleteTarget = null;
        },

        /**
         * Delete auxiliary
         */
        async deleteAuxiliary() {
            this.deleting = true;

            try {
                const response = await axios.delete(
                    `${API_BASE_URL}/api/auxiliary/delete-auxiliary/${this.deleteTarget.id}`
                );

                if (response.data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: 'Auxiliary deleted successfully',
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    });
                    
                    this.closeDeleteDialog();
                    this.fetchAuxiliaries();
                } else {
                    throw new Error(response.data.message || 'Delete failed');
                }
            } catch (err) {
                console.error('Delete error:', err);
                
                Swal.fire({
                    icon: 'error',
                    title: 'Delete Failed!',
                    text: err.response?.data?.message || 'Failed to delete auxiliary',
                    confirmButtonColor: '#dc3545'
                });
            } finally {
                this.deleting = false;
            }
        }
    },

    mounted() {
        console.log('Auxiliary component mounted');
        console.log('API Base URL:', API_BASE_URL);
        
        // Load last printer preference first
        this.loadLastPrinter();
        
        // Then fetch data
        this.fetchAuxiliaries();
        this.fetchPrinters();
    },

    beforeUnmount() {
        // Clear any pending timeouts
        clearTimeout(this.auxNameTimeout);
        clearTimeout(this.auxCodeTimeout);
        clearTimeout(this.editNameTimeout);
        clearTimeout(this.editCodeTimeout);
    }
};