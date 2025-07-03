import { eventBus } from '../../components/eventBus';

const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: 'AsinViewerModule',
    // Data properties
    data() {
        return {
            asinData: [],
            currentPage: 1,
            totalPages: 1,
            perPage: 15,
            expandedRows: {},
            sortColumn: "",
            sortOrder: "asc",
            
            // Store filter
            stores: [],
            selectedStore: '',
            
            // For ASIN details modal
            showAsinDetailsModal: false,
            selectedAsin: null,
            enlargeImage: false,
            
            // For instruction card modal
            showInstructionCardModal: false,
            
            // For ASIN image management modal
            showAsinImageModal: false,
            
            // Edit mode
            editMode: false,
            editedAsin: {},
            
            // Image upload - now supports multiple cards
            instructionCardUploading: false, // Can be false, 1, or 2
            instructionCardUrls: {}, // Store uploaded image URLs per ASIN
            
            // User manual upload
            userManualUploading: false,
            userManualUrls: {}, // Store uploaded PDF URLs per ASIN
            
            // ASIN image upload
            asinImageUploading: false,
            asinImageUrls: {}, // Store uploaded image URLs per ASIN
            
            // Vector image upload
            vectorImageUploading: false,
            vectorImageUrls: {}, // Store uploaded vector image URLs per ASIN
            
            // Saving states
            savingRelatedAsins: false,
            savingAsinDetails: false,
            savingDefaultDimensions: false,
            
            // For image handling
            defaultImagePath: '/images/default-product.png',
            isLoading: false
        };
    },
    computed: {
        searchQuery() {
            return eventBus.searchQuery;
        },
        sortedAsinData() {
            if (!this.sortColumn) return this.asinData;
            return [...this.asinData].sort((a, b) => {
                const valueA = a[this.sortColumn];
                const valueB = b[this.sortColumn];

                if (typeof valueA === "number" && typeof valueB === "number") {
                    return this.sortOrder === "asc" ? valueA - valueB : valueB - valueA;
                }

                return this.sortOrder === "asc"
                    ? String(valueA).localeCompare(String(valueB))
                    : String(valueB).localeCompare(String(valueA));
            });
        },
        isMobile() {
            return window.innerWidth <= 768;
        }
    },
    methods: {
        // Image handling
        getImagePath(asin) {
            if (!asin) return this.defaultImagePath;
            
            // Check if we have a stored URL for this ASIN from recent upload
            if (this.asinImageUrls[asin]) {
                return this.asinImageUrls[asin];
            }
            
            // Check if the selected ASIN has image from the API
            if (this.selectedAsin && this.selectedAsin.ASIN === asin) {
                if (this.selectedAsin.asinimg) {
                    return `${window.location.origin}/images/asinimg/${this.selectedAsin.asinimg}`;
                }
                if (this.selectedAsin.asin_image_url) {
                    return this.selectedAsin.asin_image_url;
                }
            }
            
            // Check in the main data array
            const asinData = this.asinData.find(item => item.ASIN === asin);
            if (asinData) {
                if (asinData.asinimg) {
                    return `${window.location.origin}/images/asinimg/${asinData.asinimg}`;
                }
                if (asinData.asin_image_url) {
                    return asinData.asin_image_url;
                }
            }
            
            // Default fallback to the old pattern
            return `/images/asinimg/${asin}_0.png`;
        },
        
        getInstructionCardPath(asin, cardSlot = 1) {
            if (!asin) return this.defaultImagePath;
            
            // Check if we have a stored URL for this ASIN and card slot from recent upload
            const uploadKey = `${asin}_card${cardSlot}`;
            if (this.instructionCardUrls[uploadKey]) {
                return this.instructionCardUrls[uploadKey];
            }
            
            // Check if the selected ASIN has instruction card URLs from the API
            if (this.selectedAsin && this.selectedAsin.ASIN === asin) {
                if (cardSlot === 1 && this.selectedAsin.instructioncard) {
                    return `${window.location.origin}/images/instructioncard/${this.selectedAsin.instructioncard}`;
                }
                if (cardSlot === 2 && this.selectedAsin.instructioncard2) {
                    return `${window.location.origin}/images/instructioncard/${this.selectedAsin.instructioncard2}`;
                }
                
                // Check instruction_card_urls if available
                if (this.selectedAsin.instruction_card_urls) {
                    const cardUrl = cardSlot === 1 ? 
                        this.selectedAsin.instruction_card_urls.card1 : 
                        this.selectedAsin.instruction_card_urls.card2;
                    if (cardUrl) return cardUrl;
                }
            }
            
            // Check in the main data array
            const asinData = this.asinData.find(item => item.ASIN === asin);
            if (asinData) {
                if (cardSlot === 1 && asinData.instructioncard) {
                    return `${window.location.origin}/images/instructioncard/${asinData.instructioncard}`;
                }
                if (cardSlot === 2 && asinData.instructioncard2) {
                    return `${window.location.origin}/images/instructioncard/${asinData.instructioncard2}`;  
                }
                
                // Check instruction_card_urls if available
                if (asinData.instruction_card_urls) {
                    const cardUrl = cardSlot === 1 ? 
                        asinData.instruction_card_urls.card1 : 
                        asinData.instruction_card_urls.card2;
                    if (cardUrl) return cardUrl;
                }
            }
            
            // Default fallback
            return this.defaultImagePath;
        },

        getMainInstructionCardPath(asin) {
            // Show card 1 if available, otherwise card 2, otherwise default
            const card1Path = this.getInstructionCardPath(asin, 1);
            if (card1Path !== this.defaultImagePath) {
                return card1Path;
            }
            
            const card2Path = this.getInstructionCardPath(asin, 2);
            if (card2Path !== this.defaultImagePath) {
                return card2Path;
            }
            
            return this.defaultImagePath;
        },

        getVectorImagePath(asin) {
            if (!asin) return null;
            
            // Check if we have a stored URL for this ASIN from recent upload
            if (this.vectorImageUrls[asin]) {
                return this.vectorImageUrls[asin];
            }
            
            // Check if the selected ASIN has vector image from the API
            if (this.selectedAsin && this.selectedAsin.ASIN === asin) {
                if (this.selectedAsin.vectorimage) {
                    return `${window.location.origin}/images/asinvectorsimg/${this.selectedAsin.vectorimage}`;
                }
                if (this.selectedAsin.vector_image_url) {
                    return this.selectedAsin.vector_image_url;
                }
            }
            
            // Check in the main data array
            const asinData = this.asinData.find(item => item.ASIN === asin);
            if (asinData) {
                if (asinData.vectorimage) {
                    return `${window.location.origin}/images/asinvectorsimg/${asinData.vectorimage}`;
                }
                if (asinData.vector_image_url) {
                    return asinData.vector_image_url;
                }
            }
            
            return null;
        },

        getMainAsinImagePath(asin) {
            // Get ASIN image if available, otherwise default
            const asinPath = this.getImagePath(asin);
            if (asinPath !== this.defaultImagePath) {
                return asinPath;
            }
            return this.defaultImagePath;
        },

        getMainVectorImagePath(asin) {
            // Get vector image if available, otherwise default vector icon
            const vectorPath = this.getVectorImagePath(asin);
            if (vectorPath) {
                return vectorPath;
            }
            return this.createDefaultVectorSVG();
        },

        hasVectorImage(asin) {
            return this.getVectorImagePath(asin) !== null;
        },

        getUserManualPath(asin) {
            if (!asin) return null;
            
            // Check if we have a stored URL for this ASIN from recent upload
            if (this.userManualUrls[asin]) {
                return this.userManualUrls[asin];
            }
            
            // Check if the selected ASIN has user manual from the API
            if (this.selectedAsin && this.selectedAsin.ASIN === asin) {
                if (this.selectedAsin.usermanuallink) {
                    return `${window.location.origin}/images/usermanual/${this.selectedAsin.usermanuallink}`;
                }
                if (this.selectedAsin.user_manual_url) {
                    return this.selectedAsin.user_manual_url;
                }
            }
            
            // Check in the main data array
            const asinData = this.asinData.find(item => item.ASIN === asin);
            if (asinData) {
                if (asinData.usermanuallink) {
                    return `${window.location.origin}/images/usermanual/${asinData.usermanuallink}`;
                }
                if (asinData.user_manual_url) {
                    return asinData.user_manual_url;
                }
            }
            
            return null;
        },

        hasUserManual(asin) {
            return this.getUserManualPath(asin) !== null;
        },

        hasInstructionCard(asin, cardSlot) {
            const path = this.getInstructionCardPath(asin, cardSlot);
            return path !== this.defaultImagePath;
        },
        
        handleImageError(event, item) {
            event.target.src = this.defaultImagePath;
            if (item) item.useDefaultImage = true;
        },
        
        handleInstructionCardError(event, cardSlot) {
            event.target.src = this.defaultImagePath;
            event.target.style.opacity = '0.5';
        },
        
        createDefaultImageSVG() {
            return `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' fill='%23f0f0f0'/%3E%3Ctext x='50' y='50' text-anchor='middle' dy='0.3em' font-family='Arial' font-size='12' fill='%23999'%3ENo Image%3C/text%3E%3C/svg%3E`;
        },

        createDefaultVectorSVG() {
            return `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' fill='%23f8f9fa' stroke='%23dee2e6'/%3E%3Cpath d='M30 30h40v40H30z' fill='none' stroke='%236f42c1' stroke-width='2'/%3E%3Cpath d='M35 35l30 30M65 35l-30 30' stroke='%236f42c1' stroke-width='1'/%3E%3Ctext x='50' y='85' text-anchor='middle' font-family='Arial' font-size='8' fill='%23999'%3ENo Vector%3C/text%3E%3C/svg%3E`;
        },

        // Store management
        async fetchStores() {
            try {
                const response = await axios.get(`${API_BASE_URL}/api/asinlist/stores`, {
                    withCredentials: true
                });
                this.stores = response.data;
            } catch (error) {
                console.error("Error fetching stores:", error);
                this.stores = [];
            }
        },
        
        changeStore() {
            this.currentPage = 1;
            this.fetchAsinData();
        },

        // Data fetching
        async fetchAsinData() {
            try {
                this.isLoading = true;
                
                const response = await axios.get(`${API_BASE_URL}/api/asinlist/products`, {
                    params: { 
                        search: this.searchQuery, 
                        page: this.currentPage, 
                        per_page: this.perPage,
                        store: this.selectedStore
                    },
                    withCredentials: true
                });

                const asinItems = (response.data.data || []).map(item => ({
                    ...item,
                    useDefaultImage: false,
                    fnskus: item.fnskus || []
                }));

                this.asinData = asinItems;
                this.totalPages = response.data.last_page || 1;
                
            } catch (error) {
                console.error("Error fetching ASIN data:", error);
                this.asinData = [];
            } finally {
                this.isLoading = false;
            }
        },

        // Pagination
        changePerPage() {
            this.currentPage = 1;
            this.fetchAsinData();
        },
        
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.fetchAsinData();
            }
        },
        
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.fetchAsinData();
            }
        },

        // UI
        toggleDetails(index) {
            const updatedExpandedRows = { ...this.expandedRows };
            updatedExpandedRows[index] = !updatedExpandedRows[index];
            this.expandedRows = updatedExpandedRows;
        },

        // Sorting
        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortOrder = this.sortOrder === "asc" ? "desc" : "asc";
            } else {
                this.sortColumn = column;
                this.sortOrder = "asc";
            }
        },

        // Modal management
        viewAsinDetails(item) {
            this.selectedAsin = item;
            this.showAsinDetailsModal = true;
        },
        
        closeAsinDetailsModal() {
            this.showAsinDetailsModal = false;
            this.selectedAsin = null;
            this.editMode = false;
            this.editedAsin = {};
            this.instructionCardUploading = false;
            this.userManualUploading = false;
            this.asinImageUploading = false;
            this.vectorImageUploading = false;
            this.savingAsinDetails = false;
            this.savingRelatedAsins = false;
            this.savingDefaultDimensions = false;
        },

        // Instruction Card Modal Management
        openInstructionCardModal() {
            this.showInstructionCardModal = true;
        },

        closeInstructionCardModal() {
            this.showInstructionCardModal = false;
            this.instructionCardUploading = false;
        },

        // ASIN Image Modal Management
        openAsinImageModal() {
            this.showAsinImageModal = true;
        },

        closeAsinImageModal() {
            this.showAsinImageModal = false;
            this.asinImageUploading = false;
            this.vectorImageUploading = false;
        },

        // Edit Mode
        toggleEditMode() {
            this.editMode = !this.editMode;
            if (this.editMode) {
                this.editedAsin = {
                    ASIN: this.selectedAsin.ASIN,
                    EAN: this.selectedAsin.EAN || '',
                    UPC: this.selectedAsin.UPC || '',
                    instructionlink: this.selectedAsin.instructionlink || '',
                    metakeyword: this.selectedAsin.metakeyword || '',
                    TRANSPARENCY_QR_STATUS: this.selectedAsin.TRANSPARENCY_QR_STATUS || '',
                    ParentAsin: this.selectedAsin.ParentAsin || '',
                    CousinASIN: this.selectedAsin.CousinASIN || '',
                    UpgradeASIN: this.selectedAsin.UpgradeASIN || '',
                    GrandASIN: this.selectedAsin.GrandASIN || '',
                    // Default dimensions (editable)
                    def_length: this.selectedAsin.white_length || '',
                    def_width: this.selectedAsin.white_width || '',
                    def_height: this.selectedAsin.white_height || '',
                    def_weight: this.selectedAsin.white_value || '',
                    def_weight_unit: this.selectedAsin.white_unit || ''
                };
            } else {
                this.editedAsin = {};
            }
        },

        // ASIN Details Management - Enhanced with new fields
        async saveAsinDetails() {
            if (!this.editedAsin.ASIN) {
                alert('ASIN is required');
                return;
            }

            this.savingAsinDetails = true;
            try {
                console.log('Saving ASIN details:', {
                    asin: this.editedAsin.ASIN,
                    ean: this.editedAsin.EAN,
                    upc: this.editedAsin.UPC,
                    instruction_link: this.editedAsin.instructionlink,
                    metakeyword: this.editedAsin.metakeyword,
                    transparency_qr_status: this.editedAsin.TRANSPARENCY_QR_STATUS
                });

                const response = await axios.post(`${API_BASE_URL}/api/asinlist/update-asin-details`, {
                    asin: this.editedAsin.ASIN,
                    ean: this.editedAsin.EAN || null,
                    upc: this.editedAsin.UPC || null,
                    instruction_link: this.editedAsin.instructionlink || null,
                    metakeyword: this.editedAsin.metakeyword || null,
                    transparency_qr_status: this.editedAsin.TRANSPARENCY_QR_STATUS || null
                }, {
                    withCredentials: true,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });

                console.log('Save response:', response.data);

                if (response.data.success) {
                    // Update the selected ASIN
                    this.selectedAsin.EAN = this.editedAsin.EAN;
                    this.selectedAsin.UPC = this.editedAsin.UPC;
                    this.selectedAsin.instructionlink = this.editedAsin.instructionlink;
                    this.selectedAsin.metakeyword = this.editedAsin.metakeyword;
                    this.selectedAsin.TRANSPARENCY_QR_STATUS = this.editedAsin.TRANSPARENCY_QR_STATUS;
                    
                    // Update the main data array
                    const asinIndex = this.asinData.findIndex(item => item.ASIN === this.editedAsin.ASIN);
                    if (asinIndex !== -1) {
                        this.asinData[asinIndex].EAN = this.editedAsin.EAN;
                        this.asinData[asinIndex].UPC = this.editedAsin.UPC;
                        this.asinData[asinIndex].instructionlink = this.editedAsin.instructionlink;
                        this.asinData[asinIndex].metakeyword = this.editedAsin.metakeyword;
                        this.asinData[asinIndex].TRANSPARENCY_QR_STATUS = this.editedAsin.TRANSPARENCY_QR_STATUS;
                    }
                    
                    alert('ASIN details updated successfully');
                } else {
                    throw new Error(response.data.message || 'Failed to update ASIN details');
                }
            } catch (error) {
                console.error('Error updating ASIN details:', error);
                alert('Failed to update ASIN details: ' + (error.response?.data?.message || error.message));
            } finally {
                this.savingAsinDetails = false;
            }
        },

        // Default Dimensions Management
        async saveDefaultDimensions() {
            if (!this.editedAsin.ASIN) {
                alert('ASIN is required');
                return;
            }

            this.savingDefaultDimensions = true;
            try {
                const response = await axios.post(`${API_BASE_URL}/api/asinlist/update-default-dimensions`, {
                    asin: this.editedAsin.ASIN,
                    def_length: this.editedAsin.def_length || null,
                    def_width: this.editedAsin.def_width || null,
                    def_height: this.editedAsin.def_height || null,
                    def_weight: this.editedAsin.def_weight || null,
                    def_weight_unit: this.editedAsin.def_weight_unit || null
                }, {
                    withCredentials: true
                });

                if (response.data.success) {
                    // Update the selected ASIN
                    this.selectedAsin.white_length = this.editedAsin.def_length;
                    this.selectedAsin.white_width = this.editedAsin.def_width;
                    this.selectedAsin.white_height = this.editedAsin.def_height;
                    this.selectedAsin.white_value = this.editedAsin.def_weight;
                    this.selectedAsin.white_unit = this.editedAsin.def_weight_unit;
                    
                    // Update the main data array
                    const asinIndex = this.asinData.findIndex(item => item.ASIN === this.editedAsin.ASIN);
                    if (asinIndex !== -1) {
                        this.asinData[asinIndex].white_length = this.editedAsin.def_length;
                        this.asinData[asinIndex].white_width = this.editedAsin.def_width;
                        this.asinData[asinIndex].white_height = this.editedAsin.def_height;
                        this.asinData[asinIndex].white_value = this.editedAsin.def_weight;
                        this.asinData[asinIndex].white_unit = this.editedAsin.def_weight_unit;
                    }
                    
                    alert('Default dimensions updated successfully');
                } else {
                    throw new Error(response.data.message || 'Failed to update default dimensions');
                }
            } catch (error) {
                console.error('Error updating default dimensions:', error);
                alert('Failed to update default dimensions: ' + (error.response?.data?.message || error.message));
            } finally {
                this.savingDefaultDimensions = false;
            }
        },

        // Related ASINs Management
        async saveRelatedAsins() {
            this.savingRelatedAsins = true;
            try {
                const response = await axios.post(`${API_BASE_URL}/api/asinlist/update-related-asins`, {
                    asin: this.editedAsin.ASIN,
                    parent_asin: this.editedAsin.ParentAsin || null,
                    cousin_asin: this.editedAsin.CousinASIN || null,
                    upgrade_asin: this.editedAsin.UpgradeASIN || null,
                    grand_asin: this.editedAsin.GrandASIN || null
                }, {
                    withCredentials: true
                });

                if (response.data.success) {
                    this.selectedAsin.ParentAsin = this.editedAsin.ParentAsin;
                    this.selectedAsin.CousinASIN = this.editedAsin.CousinASIN;
                    this.selectedAsin.UpgradeASIN = this.editedAsin.UpgradeASIN;
                    this.selectedAsin.GrandASIN = this.editedAsin.GrandASIN;
                    
                    // Update the main data array
                    const asinIndex = this.asinData.findIndex(item => item.ASIN === this.editedAsin.ASIN);
                    if (asinIndex !== -1) {
                        this.asinData[asinIndex].ParentAsin = this.editedAsin.ParentAsin;
                        this.asinData[asinIndex].CousinASIN = this.editedAsin.CousinASIN;
                        this.asinData[asinIndex].UpgradeASIN = this.editedAsin.UpgradeASIN;
                        this.asinData[asinIndex].GrandASIN = this.editedAsin.GrandASIN;
                    }
                    
                    alert('Related ASINs updated successfully');
                } else {
                    throw new Error(response.data.message || 'Failed to update related ASINs');
                }
            } catch (error) {
                console.error('Error updating related ASINs:', error);
                alert('Failed to update related ASINs');
            } finally {
                this.savingRelatedAsins = false;
            }
        },

        // Instruction Card Upload - Updated to support multiple cards
        async handleInstructionCardUpload(event, cardSlot) {
            const file = event.target.files[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) {
                alert('Please select an image file');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                return;
            }

            this.instructionCardUploading = cardSlot;
            
            try {
                const formData = new FormData();
                formData.append('instruction_card', file);
                formData.append('asin', this.selectedAsin.ASIN);
                formData.append('card_slot', cardSlot);

                const response = await axios.post(`${API_BASE_URL}/api/asinlist/upload-instruction-card`, formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    },
                    withCredentials: true
                });

                if (response.data.success) {
                    alert(`Instruction card ${cardSlot} uploaded successfully`);
                    
                    // Store the uploaded file URL for this ASIN and card slot
                    const uploadKey = `${this.selectedAsin.ASIN}_card${cardSlot}`;
                    this.instructionCardUrls[uploadKey] = response.data.file_url;
                    
                    // Update the selected ASIN data
                    const columnName = cardSlot === 1 ? 'instructioncard' : 'instructioncard2';
                    this.selectedAsin[columnName] = response.data.filename;
                    
                    // Update the main data array
                    const asinIndex = this.asinData.findIndex(item => item.ASIN === this.selectedAsin.ASIN);
                    if (asinIndex !== -1) {
                        this.asinData[asinIndex][columnName] = this.selectedAsin[columnName];
                    }
                    
                    // Force refresh of images by updating all relevant image elements
                    setTimeout(() => {
                        const imgElements = document.querySelectorAll(`img[alt*="card ${cardSlot}"], img[alt*="cards"]`);
                        imgElements.forEach(img => {
                            const currentSrc = img.src.split('?')[0]; // Remove any existing cache busters
                            img.src = currentSrc + '?t=' + Date.now();
                            img.style.opacity = '1';
                            img.style.filter = 'none';
                            img.style.borderStyle = 'solid';
                            img.classList.add('uploaded');
                        });
                        
                        // Force Vue to re-render the instruction card thumbnails
                        this.$forceUpdate();
                    }, 100);
                } else {
                    throw new Error(response.data.message || 'Failed to upload instruction card');
                }
            } catch (error) {
                console.error('Error uploading instruction card:', error);
                alert('Failed to upload instruction card: ' + (error.response?.data?.message || error.message));
            } finally {
                this.instructionCardUploading = false;
                event.target.value = '';
            }
        },

        // User Manual Upload
        async handleUserManualUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            if (file.type !== 'application/pdf') {
                alert('Please select a PDF file');
                return;
            }

            if (file.size > 10 * 1024 * 1024) {
                alert('File size must be less than 10MB');
                return;
            }

            this.userManualUploading = true;
            
            try {
                const formData = new FormData();
                formData.append('user_manual', file);
                formData.append('asin', this.selectedAsin.ASIN);

                const response = await axios.post(`${API_BASE_URL}/api/asinlist/upload-user-manual`, formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    },
                    withCredentials: true
                });

                if (response.data.success) {
                    alert('User manual uploaded successfully');
                    
                    // Store the uploaded file URL for this ASIN
                    this.userManualUrls[this.selectedAsin.ASIN] = response.data.file_url;
                    
                    // Update the selected ASIN data
                    this.selectedAsin.usermanuallink = response.data.filename;
                    this.selectedAsin.user_manual_url = response.data.file_url;
                    
                    // Update the main data array
                    const asinIndex = this.asinData.findIndex(item => item.ASIN === this.selectedAsin.ASIN);
                    if (asinIndex !== -1) {
                        this.asinData[asinIndex].usermanuallink = this.selectedAsin.usermanuallink;
                        this.asinData[asinIndex].user_manual_url = this.selectedAsin.user_manual_url;
                    }
                    
                    // Force Vue to re-render
                    this.$forceUpdate();
                } else {
                    throw new Error(response.data.message || 'Failed to upload user manual');
                }
            } catch (error) {
                console.error('Error uploading user manual:', error);
                alert('Failed to upload user manual: ' + (error.response?.data?.message || error.message));
            } finally {
                this.userManualUploading = false;
                event.target.value = '';
            }
        },

        // ASIN Image Upload
        async handleAsinImageUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) {
                alert('Please select an image file');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                return;
            }

            this.asinImageUploading = true;
            
            try {
                const formData = new FormData();
                formData.append('asin_image', file);
                formData.append('asin', this.selectedAsin.ASIN);

                const response = await axios.post(`${API_BASE_URL}/api/asinlist/upload-asin-image`, formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    },
                    withCredentials: true
                });

                if (response.data.success) {
                    alert('ASIN image uploaded successfully');
                    
                    // Store the uploaded file URL for this ASIN
                    this.asinImageUrls[this.selectedAsin.ASIN] = response.data.file_url;
                    
                    // Update the selected ASIN data
                    this.selectedAsin.asinimg = response.data.filename;
                    this.selectedAsin.asin_image_url = response.data.file_url;
                    
                    // Update the main data array
                    const asinIndex = this.asinData.findIndex(item => item.ASIN === this.selectedAsin.ASIN);
                    if (asinIndex !== -1) {
                        this.asinData[asinIndex].asinimg = this.selectedAsin.asinimg;
                        this.asinData[asinIndex].asin_image_url = this.selectedAsin.asin_image_url;
                    }
                    
                    // Force refresh of images
                    setTimeout(() => {
                        const imgElements = document.querySelectorAll(`img[alt*="ASIN image"], img[alt*="asin"]`);
                        imgElements.forEach(img => {
                            const currentSrc = img.src.split('?')[0];
                            img.src = currentSrc + '?t=' + Date.now();
                            img.classList.add('uploaded');
                        });
                        this.$forceUpdate();
                    }, 100);
                } else {
                    throw new Error(response.data.message || 'Failed to upload ASIN image');
                }
            } catch (error) {
                console.error('Error uploading ASIN image:', error);
                alert('Failed to upload ASIN image: ' + (error.response?.data?.message || error.message));
            } finally {
                this.asinImageUploading = false;
                event.target.value = '';
            }
        },

        // Vector Image Upload
        async handleVectorImageUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) {
                alert('Please select an image file (PNG or JPG)');
                return;
            }

            const allowedTypes = ['image/png', 'image/jpg', 'image/jpeg'];
            if (!allowedTypes.includes(file.type)) {
                alert('Please select a PNG or JPG image file');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                return;
            }

            this.vectorImageUploading = true;
            
            try {
                const formData = new FormData();
                formData.append('vector_image', file);
                formData.append('asin', this.selectedAsin.ASIN);

                const response = await axios.post(`${API_BASE_URL}/api/asinlist/upload-vector-image`, formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    },
                    withCredentials: true
                });

                if (response.data.success) {
                    alert('Vector image uploaded successfully');
                    
                    // Store the uploaded file URL for this ASIN
                    this.vectorImageUrls[this.selectedAsin.ASIN] = response.data.file_url;
                    
                    // Update the selected ASIN data
                    this.selectedAsin.vectorimage = response.data.filename;
                    this.selectedAsin.vector_image_url = response.data.file_url;
                    
                    // Update the main data array
                    const asinIndex = this.asinData.findIndex(item => item.ASIN === this.selectedAsin.ASIN);
                    if (asinIndex !== -1) {
                        this.asinData[asinIndex].vectorimage = this.selectedAsin.vectorimage;
                        this.asinData[asinIndex].vector_image_url = this.selectedAsin.vector_image_url;
                    }
                    
                    // Force refresh of images
                    setTimeout(() => {
                        const imgElements = document.querySelectorAll(`img[alt*="vector"], img[alt*="Vector"]`);
                        imgElements.forEach(img => {
                            const currentSrc = img.src.split('?')[0];
                            img.src = currentSrc + '?t=' + Date.now();
                            img.classList.add('uploaded');
                        });
                        this.$forceUpdate();
                    }, 100);
                } else {
                    throw new Error(response.data.message || 'Failed to upload vector image');
                }
            } catch (error) {
                console.error('Error uploading vector image:', error);
                alert('Failed to upload vector image: ' + (error.response?.data?.message || error.message));
            } finally {
                this.vectorImageUploading = false;
                event.target.value = '';
            }
        },

        // Utility methods
        getUniqueStores(fnskus) {
            if (!fnskus || fnskus.length === 0) return [];
            const stores = fnskus.map(fnsku => fnsku.storename).filter(store => store);
            return [...new Set(stores)];
        },

        handleResize() {
            this.$forceUpdate();
        },

        // Helper method to construct URLs
        url(path) {
            if (!path) return this.defaultImagePath;
            if (path.startsWith('http')) return path;
            return `${window.location.origin}/${path}`;
        }
    },
    watch: {
        searchQuery() {
            this.currentPage = 1;
            this.fetchAsinData();
        }
    },
    mounted() {
        // Set axios defaults
        axios.defaults.baseURL = window.location.origin;
        axios.defaults.withCredentials = true;
        
        // Set CSRF token
        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) {
            axios.defaults.headers.common['X-CSRF-TOKEN'] = token.getAttribute('content');
        }
        
        // Load Font Awesome if not already loaded
        if (!document.querySelector('link[href*="font-awesome"]')) {
            const fontAwesome = document.createElement('link');
            fontAwesome.rel = 'stylesheet';
            fontAwesome.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css';
            document.head.appendChild(fontAwesome);
        }
        
        // Set default image
        this.defaultImagePath = this.createDefaultImageSVG();
        
        // Initialize data
        this.fetchStores();
        this.fetchAsinData();

        // Add resize listener
        window.addEventListener('resize', this.handleResize);
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.handleResize);
    }
};