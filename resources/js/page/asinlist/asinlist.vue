<template>
    <div class="vue-container asin-viewer-module">
        <!-- Top header bar -->
        <div class="top-header">
            <div class="store-filter">
                <label for="store-select">Store:</label>
                <select id="store-select" v-model="selectedStore" @change="changeStore" class="store-select">
                    <option value="">All Stores</option>
                    <option v-for="store in stores" :key="store" :value="store">
                        {{ store }}
                    </option>
                </select>
            </div>
        </div>

        <h2 class="module-title">ASIN List Manager</h2>

        <!-- Desktop Table Container -->
        <div class="table-container desktop-view">
            <table>
                <thead>
                    <tr>
                        <th class="sticky-header first-col" style="width: 350px; min-width: 350px;">
                            <div class="product-name">
                                <span class="sortable" @click="sortBy('AStitle')">
                                    Product Name
                                    <i v-if="sortColumn === 'AStitle'"
                                        :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                                </span>
                            </div>
                        </th>
                        <th style="width: 120px; min-width: 120px;">
                            <div class="sortable" @click="sortBy('ASIN')">
                                ASIN
                                <i v-if="sortColumn === 'ASIN'"
                                    :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                            </div>
                        </th>
                        <th style="width: 180px; min-width: 180px;">
                            <div class="">
                                EAN / UPC
                            </div>
                        </th>
                        <th style="width: 250px; min-width: 250px;">
                            <div class="">
                                Related ASINs
                            </div>
                        </th>
                        <th style="width: 120px; min-width: 120px;">
                            <div class="">
                                FNSKUs
                            </div>
                        </th>
                        <th style="width: 200px; min-width: 200px;">
                            <div class="th-content">
                                Actions
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(item, index) in sortedAsinData" :key="item.ASIN">
                        <tr>
                            <td class="sticky-col first-col" style="width: 350px; min-width: 350px;">
                                <div class="product-container">
                                    <div class="product-image-container clickable" @click="viewAsinDetails(item)">
                                        <img :src="item.useDefaultImage ? defaultImagePath : getImagePath(item.ASIN)"
                                            :alt="item.AStitle" class="product-thumbnail"
                                            @error="handleImageError($event, item)" />
                                    </div>
                                    <div class="product-info">
                                        <p class="product-name clickable" @click="viewAsinDetails(item)">
                                            {{ item.AStitle }}
                                        </p>
                                        <p class="product-title" v-if="item.metakeyword">
                                            {{ item.metakeyword }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td style="width: 120px;">{{ item.ASIN }}</td>
                            <td style="width: 180px;">
                                <div class="codes-container">
                                    <div v-if="item.EAN" class="code-item">
                                        <span class="code-label">EAN:</span>
                                        <span class="code-value">{{ item.EAN }}</span>
                                    </div>
                                    <div v-if="item.UPC" class="code-item">
                                        <span class="code-label">UPC:</span>
                                        <span class="code-value">{{ item.UPC }}</span>
                                    </div>
                                    <div v-if="!item.EAN && !item.UPC" class="no-codes">-</div>
                                </div>
                            </td>
                            <td style="width: 250px;">
                                <div class="related-asins">
                                    <div v-if="item.ParentAsin" class="related-item">
                                        <span class="related-label">Parent:</span>
                                        <span class="related-value">{{ item.ParentAsin }}</span>
                                    </div>
                                    <div v-if="item.CousinASIN" class="related-item">
                                        <span class="related-label">Cousin:</span>
                                        <span class="related-value">{{ item.CousinASIN }}</span>
                                    </div>
                                    <div v-if="item.UpgradeASIN" class="related-item">
                                        <span class="related-label">Upgrade:</span>
                                        <span class="related-value">{{ item.UpgradeASIN }}</span>
                                    </div>
                                    <div v-if="item.GrandASIN" class="related-item">
                                        <span class="related-label">Grand:</span>
                                        <span class="related-value">{{ item.GrandASIN }}</span>
                                    </div>
                                    <div v-if="!item.ParentAsin && !item.CousinASIN && !item.UpgradeASIN && !item.GrandASIN" class="no-related">-</div>
                                </div>
                            </td>
                            <td style="width: 120px;">
                                <div class="fnsku-count">
                                    {{ item.fnsku_count }} FNSKUs
                                </div>
                            </td>
                            <td style="width: 200px;">
                                <div class="action-buttons">
                                    <button class="btn-expand" @click="toggleDetails(index)">
                                        {{ expandedRows[index] ? 'Hide FNSKUs' : 'Show FNSKUs' }}
                                    </button>
                                    <button class="btn-details" @click="viewAsinDetails(item)">
                                        <i class="fas fa-info-circle"></i> Full Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <!-- Expanded FNSKU Details Row -->
                        <tr v-if="expandedRows[index]" class="expanded-row">
                            <td colspan="6">
                                <div class="expanded-content">
                                    <div class="expanded-fnskus">
                                        <strong>FNSKUs for {{ item.ASIN }}:</strong>
                                        <div class="fnsku-table-container">
                                            <table class="fnsku-detail-table">
                                                <thead>
                                                    <tr>
                                                        <th>FNSKU</th>
                                                        <th>MSKU</th>
                                                        <th>Store</th>
                                                        <th>Units</th>
                                                        <th>Grade</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="fnsku in item.fnskus" :key="fnsku.FNSKU">
                                                        <td class="fnsku-code">{{ fnsku.FNSKU }}</td>
                                                        <td>{{ fnsku.MSKU || '-' }}</td>
                                                        <td>{{ fnsku.storename }}</td>
                                                        <td>{{ fnsku.Units || 0 }}</td>
                                                        <td>
                                                            <span class="grade-display" :class="getGradeClass(fnsku.grading)">
                                                                {{ fnsku.grading || 'Not Graded' }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <tr v-if="!item.fnskus || item.fnskus.length === 0">
                                                        <td colspan="5" class="text-center">No FNSKUs found</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards View -->
        <div class="mobile-view">
            <div class="mobile-cards">
                <div v-for="(item, index) in sortedAsinData" :key="item.ASIN" class="mobile-card">
                    <div class="mobile-card-header">
                        <div class="mobile-product-image">
                            <img :src="item.useDefaultImage ? defaultImagePath : getImagePath(item.ASIN)"
                                :alt="item.AStitle" class="product-thumbnail-mobile"
                                @error="handleImageError($event, item)" />
                        </div>
                        <div class="mobile-product-info">
                            <h3 class="mobile-product-name">
                                {{ item.AStitle }}
                            </h3>
                        </div>
                    </div>

                    <hr>

                    <div class="mobile-card-details">
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">ASIN:</span>
                            <span class="mobile-detail-value">{{ item.ASIN }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">EAN:</span>
                            <span class="mobile-detail-value">{{ item.EAN || '-' }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">UPC:</span>
                            <span class="mobile-detail-value">{{ item.UPC || '-' }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">FNSKUs:</span>
                            <span class="mobile-detail-value">{{ item.fnsku_count }}</span>
                        </div>
                    </div>

                    <hr>

                    <div class="mobile-card-actions">
                        <button class="btn btn-expand" @click="toggleDetails(index)">
                            <i class="fas fa-list"></i> {{ expandedRows[index] ? 'Hide' : 'FNSKUs' }}
                        </button>
                        <button class="btn btn-details" @click="viewAsinDetails(item)">
                            <i class="fas fa-info-circle"></i> Details
                        </button>
                    </div>

                    <hr v-if="expandedRows[index]">

                    <div v-if="expandedRows[index]" class="mobile-expanded-content">
                        <div class="mobile-section">
                            <h4>FNSKUs:</h4>
                            <div class="mobile-fnsku-list">
                                <div v-for="fnsku in item.fnskus" :key="fnsku.FNSKU" class="mobile-fnsku-item">
                                    <div class="mobile-fnsku-detail">
                                        <span class="mobile-fnsku-label">FNSKU:</span>
                                        <span class="mobile-fnsku-value fnsku-code">{{ fnsku.FNSKU }}</span>
                                    </div>
                                    <div class="mobile-fnsku-detail">
                                        <span class="mobile-fnsku-label">MSKU:</span>
                                        <span class="mobile-fnsku-value">{{ fnsku.MSKU || '-' }}</span>
                                    </div>
                                    <div class="mobile-fnsku-detail">
                                        <span class="mobile-fnsku-label">Store:</span>
                                        <span class="mobile-fnsku-value">{{ fnsku.storename }}</span>
                                    </div>
                                    <div class="mobile-fnsku-detail">
                                        <span class="mobile-fnsku-label">Units:</span>
                                        <span class="mobile-fnsku-value">{{ fnsku.Units || 0 }}</span>
                                    </div>
                                    <div class="mobile-fnsku-detail">
                                        <span class="mobile-fnsku-label">Grade:</span>
                                        <span class="mobile-fnsku-value">
                                            <span class="grade-display" :class="getGradeClass(fnsku.grading)">
                                                {{ fnsku.grading || 'Not Graded' }}
                                            </span>
                                        </span>
                                    </div>
                                </div>
                                <div v-if="!item.fnskus || item.fnskus.length === 0" class="mobile-empty">
                                    No FNSKUs found
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="pagination-container">
            <div class="pagination-wrapper">
                <div class="per-page-selector">
                    <span>Rows per page</span>
                    <select v-model="perPage" @change="changePerPage" class="per-page-select">
                        <option v-for="option in [10, 15, 20, 50, 100]" :key="option" :value="option">
                            {{ option }}
                        </option>
                    </select>
                </div>

                <div class="pagination">
                    <button @click="prevPage" :disabled="currentPage === 1" class="pagination-button">
                        <i class="fas fa-chevron-left"></i> Back
                    </button>
                    <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
                    <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button">
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ASIN Details Modal -->
        <div v-if="showAsinDetailsModal" class="asin-details-modal">
            <div class="asin-details-content">
                <div class="asin-details-header">
                    <h2>ASIN Details</h2>
                    <div class="header-actions">
                        <button class="btn-edit" @click="toggleEditMode" :class="{ active: editMode }">
                            <i class="fas fa-edit"></i> {{ editMode ? 'Cancel Edit' : 'Edit' }}
                        </button>
                        <button class="asin-details-close" @click="closeAsinDetailsModal">&times;</button>
                    </div>
                </div>

                <div class="asin-details-body" v-if="selectedAsin">
                    <div class="asin-details-layout">
                        <!-- Left Column: Images and Basic Info -->
                        <div class="asin-details-left">
                            <!-- Images Section - Horizontal Layout -->
                            <div class="images-section">
                                <div class="image-container">
                                    <div class="asin-details-image clickable" @click="enlargeImage = !enlargeImage">
                                        <img :src="selectedAsin.useDefaultImage ? defaultImagePath : getImagePath(selectedAsin.ASIN)"
                                            :alt="selectedAsin.AStitle"
                                            :class="['asin-details-thumbnail', enlargeImage ? 'enlarged' : '']"
                                            @error="handleImageError($event, selectedAsin)" />
                                    </div>
                                    <div class="image-label">ASIN Image</div>
                                </div>
                                
                                <div class="image-container">
                                    <div class="instruction-card-image">
                                        <img :src="getInstructionCardPath(selectedAsin.ASIN)"
                                            :alt="`Instruction card for ${selectedAsin.ASIN}`"
                                            class="instruction-card-thumbnail"
                                            @error="handleInstructionCardError($event)" />
                                        
                                        <!-- Upload Button Overlay -->
                                        <div class="upload-overlay" v-if="editMode">
                                            <input type="file" 
                                                   ref="instructionCardInput"
                                                   @change="handleInstructionCardUpload"
                                                   accept="image/*"
                                                   style="display: none" />
                                            <button class="btn-upload-image" @click="$refs.instructionCardInput.click()">
                                                <i class="fas fa-camera"></i> Upload
                                            </button>
                                        </div>
                                    </div>
                                    <div class="image-label">
                                        Instruction Card
                                        <span v-if="instructionCardUploading" class="upload-status">
                                            <i class="fas fa-spinner fa-spin"></i> Uploading...
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="asin-details-info">
                                <h3 class="asin-details-title">{{ selectedAsin.AStitle }}</h3>
                                <div class="asin-details-row">
                                    <span class="asin-details-label">ASIN:</span>
                                    <span class="asin-details-value">{{ selectedAsin.ASIN }}</span>
                                </div>
                                <div class="asin-details-row">
                                    <span class="asin-details-label">EAN:</span>
                                    <input v-if="editMode" 
                                           v-model="editedAsin.EAN"
                                           class="details-input"
                                           placeholder="Enter EAN" />
                                    <span v-else class="asin-details-value">{{ selectedAsin.EAN || '-' }}</span>
                                </div>
                                <div class="asin-details-row">
                                    <span class="asin-details-label">UPC:</span>
                                    <input v-if="editMode" 
                                           v-model="editedAsin.UPC"
                                           class="details-input"
                                           placeholder="Enter UPC" />
                                    <span v-else class="asin-details-value">{{ selectedAsin.UPC || '-' }}</span>
                                </div>
                                <div class="asin-details-row">
                                    <span class="asin-details-label">Total FNSKUs:</span>
                                    <span class="asin-details-value">{{ selectedAsin.fnsku_count }}</span>
                                </div>
                                
                                <!-- Save button for ASIN details -->
                                <div v-if="editMode" class="asin-details-actions">
                                    <button class="btn-save-asin-details" @click="saveAsinDetails" :disabled="savingAsinDetails">
                                        <i class="fas fa-save"></i> 
                                        {{ savingAsinDetails ? 'Saving...' : 'Save ASIN Details' }}
                                    </button>
                                </div>
                                
                                <!-- Stores Section -->
                                <div class="asin-details-stores-section" v-if="getUniqueStores(selectedAsin.fnskus).length > 0">
                                    <h4>Stores</h4>
                                    <div class="stores-list">
                                        <div v-for="store in getUniqueStores(selectedAsin.fnskus)" :key="store" class="store-item">
                                            {{ store }}
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Related ASINs Section - Editable -->
                                <div class="asin-details-related-section">
                                    <h4>Related ASINs</h4>
                                    <div class="related-asins-details">
                                        <div class="related-asin-item">
                                            <span class="related-asin-label">Parent ASIN:</span>
                                            <input v-if="editMode" 
                                                   v-model="editedAsin.ParentAsin"
                                                   class="related-asin-input"
                                                   placeholder="Enter Parent ASIN" />
                                            <span v-else class="related-asin-value">{{ selectedAsin.ParentAsin || '-' }}</span>
                                        </div>
                                        <div class="related-asin-item">
                                            <span class="related-asin-label">Cousin ASIN:</span>
                                            <input v-if="editMode" 
                                                   v-model="editedAsin.CousinASIN"
                                                   class="related-asin-input"
                                                   placeholder="Enter Cousin ASIN" />
                                            <span v-else class="related-asin-value">{{ selectedAsin.CousinASIN || '-' }}</span>
                                        </div>
                                        <div class="related-asin-item">
                                            <span class="related-asin-label">Upgrade ASIN:</span>
                                            <input v-if="editMode" 
                                                   v-model="editedAsin.UpgradeASIN"
                                                   class="related-asin-input"
                                                   placeholder="Enter Upgrade ASIN" />
                                            <span v-else class="related-asin-value">{{ selectedAsin.UpgradeASIN || '-' }}</span>
                                        </div>
                                        <div class="related-asin-item">
                                            <span class="related-asin-label">Grand ASIN:</span>
                                            <input v-if="editMode" 
                                                   v-model="editedAsin.GrandASIN"
                                                   class="related-asin-input"
                                                   placeholder="Enter Grand ASIN" />
                                            <span v-else class="related-asin-value">{{ selectedAsin.GrandASIN || '-' }}</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Save button for related ASINs -->
                                    <div v-if="editMode" class="related-asins-actions">
                                        <button class="btn-save-related" @click="saveRelatedAsins" :disabled="savingRelatedAsins">
                                            <i class="fas fa-save"></i> 
                                            {{ savingRelatedAsins ? 'Saving...' : 'Save Related ASINs' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: FNSKU Details with Grading -->
                        <div class="asin-details-right">
                            <div class="asin-details-section fnsku-section">
                                <h4>FNSKU Details</h4>
                                <div class="asin-details-fnskus">
                                    <div class="responsive-table-container">
                                        <table class="asin-details-table">
                                            <thead>
                                                <tr>
                                                    <th>FNSKU</th>
                                                    <th>MSKU</th>
                                                    <th>Units</th>
                                                    <th>Grade</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="fnsku in selectedAsin.fnskus" :key="fnsku.FNSKU">
                                                    <td class="fnsku-code">{{ fnsku.FNSKU }}</td>
                                                    <td>{{ fnsku.MSKU || '-' }}</td>
                                                    <td class="units-cell">{{ fnsku.Units || 0 }}</td>
                                                    <td class="grade-cell">
                                                        <span class="grade-display" :class="getGradeClass(fnsku.grading)">
                                                            {{ fnsku.grading || 'Not Graded' }}
                                                        </span>
                                                    </td>
                                                </tr>
                                                <tr v-if="!selectedAsin.fnskus || selectedAsin.fnskus.length === 0">
                                                    <td colspan="4" class="text-center">No FNSKUs found</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="asin-details-footer">
                    <button class="btn-close-details" @click="closeAsinDetailsModal">Close</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { eventBus } from '../../components/eventBus';
import '../../../css/modules.css';

const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: 'AsinViewerModule',
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
            
            // Edit mode
            editMode: false,
            editedAsin: {},
            
            // Image upload
            instructionCardUploading: false,
            
            // Saving states
            savingRelatedAsins: false,
            savingAsinDetails: false,
            
            // For image handling
            defaultImagePath: '/images/default-product.png',

            // Loading states
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
        }
    },
    methods: {
        // Image handling methods
        getImagePath(asin) {
            return asin ? `/images/asinimg/${asin}_0.png` : this.defaultImagePath;
        },
        
        getInstructionCardPath(asin) {
            return asin ? `/images/instructioncard/${asin}.jpg` : this.defaultImagePath;
        },
        
        handleImageError(event, item) {
            event.target.src = this.defaultImagePath;
            if (item) item.useDefaultImage = true;
        },
        
        handleInstructionCardError(event) {
            event.target.src = this.defaultImagePath;
            event.target.style.opacity = '0.5';
        },
        
        createDefaultImageSVG() {
            return `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100' width='100' height='100'%3E%3Crect width='100' height='100' fill='%23f0f0f0'/%3E%3Cpath d='M35,30L65,30L65,70L35,70Z' fill='%23e0e0e0' stroke='%23bbbbbb' stroke-width='2'/%3E%3Cpath d='M45,40L55,40L55,60L45,60Z' fill='%23d0d0d0' stroke='%23bbbbbb'/%3E%3Cpath d='M35,80L65,80L65,85L35,85Z' fill='%23e0e0e0'/%3E%3C/svg%3E`;
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
                console.log('Fetching ASIN data...');
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

                console.log('ASIN API Response:', response.data);

                // Process items with flags
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
        
        // Edit Mode Management
        toggleEditMode() {
            this.editMode = !this.editMode;
            if (this.editMode) {
                // Initialize editable data
                this.editedAsin = {
                    ASIN: this.selectedAsin.ASIN,
                    EAN: this.selectedAsin.EAN || '',
                    UPC: this.selectedAsin.UPC || '',
                    ParentAsin: this.selectedAsin.ParentAsin || '',
                    CousinASIN: this.selectedAsin.CousinASIN || '',
                    UpgradeASIN: this.selectedAsin.UpgradeASIN || '',
                    GrandASIN: this.selectedAsin.GrandASIN || ''
                };
            } else {
                // Reset editing state
                this.editedAsin = {};
            }
        },

        // Grade Management
        getGradeClass(grade) {
            if (!grade) return '';
            
            const gradeMap = {
                'A+': 'grade-a-plus',
                'A': 'grade-a',
                'B+': 'grade-b-plus',
                'B': 'grade-b',
                'C+': 'grade-c-plus',
                'C': 'grade-c',
                'D': 'grade-d',
                'F': 'grade-f'
            };
            
            return gradeMap[grade] || '';
        },

        // ASIN Details Management
        async saveAsinDetails() {
            this.savingAsinDetails = true;
            try {
                const response = await axios.post(`${API_BASE_URL}/api/asinlist/update-asin-details`, {
                    asin: this.editedAsin.ASIN,
                    ean: this.editedAsin.EAN,
                    upc: this.editedAsin.UPC
                }, {
                    withCredentials: true
                });

                if (response.data.success) {
                    // Update local data
                    this.selectedAsin.EAN = this.editedAsin.EAN;
                    this.selectedAsin.UPC = this.editedAsin.UPC;
                    
                    // Update main table data
                    const asinIndex = this.asinData.findIndex(item => item.ASIN === this.editedAsin.ASIN);
                    if (asinIndex !== -1) {
                        this.asinData[asinIndex].EAN = this.editedAsin.EAN;
                        this.asinData[asinIndex].UPC = this.editedAsin.UPC;
                    }
                    
                    alert('ASIN details updated successfully');
                } else {
                    throw new Error(response.data.message || 'Failed to update ASIN details');
                }
            } catch (error) {
                console.error('Error updating ASIN details:', error);
                alert('Failed to update ASIN details');
            } finally {
                this.savingAsinDetails = false;
            }
        },

        // Related ASINs Management
        async saveRelatedAsins() {
            this.savingRelatedAsins = true;
            try {
                const response = await axios.post(`${API_BASE_URL}/api/asinlist/update-related-asins`, {
                    asin: this.editedAsin.ASIN,
                    parent_asin: this.editedAsin.ParentAsin,
                    cousin_asin: this.editedAsin.CousinASIN,
                    upgrade_asin: this.editedAsin.UpgradeASIN,
                    grand_asin: this.editedAsin.GrandASIN
                }, {
                    withCredentials: true
                });

                if (response.data.success) {
                    // Update local data
                    this.selectedAsin.ParentAsin = this.editedAsin.ParentAsin;
                    this.selectedAsin.CousinASIN = this.editedAsin.CousinASIN;
                    this.selectedAsin.UpgradeASIN = this.editedAsin.UpgradeASIN;
                    this.selectedAsin.GrandASIN = this.editedAsin.GrandASIN;
                    
                    // Update main table data
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

        // Instruction Card Upload
        async handleInstructionCardUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            // Validate file type
            if (!file.type.startsWith('image/')) {
                alert('Please select an image file');
                return;
            }

            // Validate file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                return;
            }

            this.instructionCardUploading = true;
            
            try {
                const formData = new FormData();
                formData.append('instruction_card', file);
                formData.append('asin', this.selectedAsin.ASIN);

                const response = await axios.post(`${API_BASE_URL}/api/asinlist/upload-instruction-card`, formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    },
                    withCredentials: true
                });

                if (response.data.success) {
                    alert('Instruction card uploaded successfully');
                    // Refresh the instruction card image
                    const imgElement = this.$el.querySelector('.instruction-card-thumbnail');
                    if (imgElement) {
                        imgElement.src = response.data.file_url + '?t=' + Date.now();
                    }
                } else {
                    throw new Error(response.data.message || 'Failed to upload instruction card');
                }
            } catch (error) {
                console.error('Error uploading instruction card:', error);
                alert('Failed to upload instruction card');
            } finally {
                this.instructionCardUploading = false;
                // Reset the file input
                event.target.value = '';
            }
        },

        // Enhanced modal close
        closeAsinDetailsModal() {
            this.showAsinDetailsModal = false;
            this.selectedAsin = null;
            this.enlargeImage = false;
            this.editMode = false;
            this.editedAsin = {};
            this.instructionCardUploading = false;
            this.savingAsinDetails = false;
            this.savingRelatedAsins = false;
        },

        // Get unique stores from FNSKUs
        getUniqueStores(fnskus) {
            if (!fnskus || fnskus.length === 0) return [];
            const stores = fnskus.map(fnsku => fnsku.storename).filter(store => store);
            return [...new Set(stores)];
        },

        // Handle window resize for responsiveness
        handleResize() {
            // Update mobile state
            this.$forceUpdate();
        }
    },
    watch: {
        searchQuery() {
            this.currentPage = 1;
            this.fetchAsinData();
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
        
        // Set default image
        this.defaultImagePath = this.createDefaultImageSVG();
        
        // Fetch initial data
        this.fetchStores();
        this.fetchAsinData();

        // Add resize listener
        window.addEventListener('resize', this.handleResize);
    },
    beforeUnmount() {
        // Clean up event listener
        window.removeEventListener('resize', this.handleResize);
    }
}
</script>

<style scoped>
/* Import base module styles */
@import '../../../css/modules.css';

/* Simple ASIN viewer styles */
.asin-viewer-module {
    max-width: 100%;
    margin: 0 auto;
}

.fnsku-count {
    font-weight: 600;
    color: #007bff;
}

/* Title cell styling */
.title-cell {
    max-width: 300px;
}

.title-content {
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 12px;
    color: #495057;
}

/* Codes container styling */
.codes-container {
    font-size: 11px;
}

.code-item {
    margin-bottom: 2px;
}

.code-label {
    font-weight: 600;
    color: #495057;
    margin-right: 4px;
}

.code-value {
    color: #007bff;
    font-family: 'Courier New', monospace;
}

.no-codes {
    color: #6c757d;
    font-style: italic;
}

/* Related ASINs styling */
.related-asins {
    font-size: 11px;
    max-width: 200px;
}

.related-item {
    margin-bottom: 2px;
    display: flex;
    flex-wrap: wrap;
}

.related-label {
    font-weight: 600;
    color: #495057;
    margin-right: 4px;
    min-width: 50px;
}

.related-value {
    color: #007bff;
    font-family: 'Courier New', monospace;
    font-size: 10px;
    word-break: break-all;
}

.no-related {
    color: #6c757d;
    font-style: italic;
}

/* Table layout fixes */
.table-container table {
    table-layout: fixed;
    width: 100%;
    min-width: 1220px;
}

.sticky-col.first-col {
    position: sticky;
    left: 0;
    background-color: white;
    z-index: 10;
    box-shadow: 2px 0 5px rgba(0,0,0,0.1);
}

/* Product container fixed width */
.product-container {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    max-width: 340px;
}

.product-image-container {
    flex-shrink: 0;
    width: 60px;
    height: 60px;
}

.product-thumbnail {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 4px;
    border: 1px solid #dee2e6;
}

.product-info {
    flex: 1;
    min-width: 0;
}

.product-name {
    margin: 0 0 4px 0;
    font-size: 13px;
    font-weight: 600;
    color: #495057;
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    word-wrap: break-word;
}

.product-title {
    margin: 0;
    font-size: 11px;
    color: #6c757d;
    line-height: 1.2;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    word-wrap: break-word;
    font-style: italic;
}

.fnsku-table-container {
    width: 100%;
    overflow-x: auto;
    margin-top: 10px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.fnsku-detail-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    background-color: #fff;
    border-radius: 8px;
    overflow: hidden;
    min-width: 600px;
}

.fnsku-detail-table thead {
    background-color: #1a252f;
    color: white;
}

.fnsku-detail-table thead th {
    padding: 14px 12px;
    text-align: left;
    font-weight: 700;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.fnsku-detail-table tbody td {
    padding: 10px 12px;
    border-bottom: 1px solid #dee2e6;
    color: #495057;
    font-size: 12px;
}

.fnsku-detail-table tbody tr:nth-child(even) {
    background-color: #f8f9fa;
}

.fnsku-detail-table tbody tr:hover {
    background-color: #e3f2fd;
}

.fnsku-code {
    font-family: 'Courier New', monospace;
    font-weight: 600;
    color: #007bff;
}

/* Mobile FNSKU styling */
.mobile-fnsku-list {
    margin-top: 10px;
}

.mobile-fnsku-item {
    background-color: #ffffff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 10px;
}

.mobile-fnsku-detail {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 0;
    border-bottom: 1px solid #e3f2fd;
}

.mobile-fnsku-detail:last-child {
    border-bottom: none;
}

.mobile-fnsku-label {
    font-weight: 600;
    color: #495057;
    font-size: 12px;
}

.mobile-fnsku-value {
    color: #6c757d;
    font-size: 12px;
    text-align: right;
}

.mobile-section h4 {
    background-color: #1a252f;
    color: white;
    padding: 10px 15px;
    margin: 0 0 10px 0;
    border-radius: 6px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* ASIN Details Modal Styling */
.asin-details-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.asin-details-content {
    background-color: white;
    border-radius: 12px;
    width: 95%;
    max-width: 1400px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.asin-details-header {
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #f8f9fa;
    border-radius: 12px 12px 0 0;
}

.header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.btn-edit {
    background-color: #17a2b8;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s ease;
}

.btn-edit:hover {
    background-color: #138496;
}

.btn-edit.active {
    background-color: #ffc107;
    color: #212529;
}

.asin-details-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6c757d;
}

.asin-details-body {
    padding: 20px;
}

.asin-details-layout {
    display: flex;
    gap: 25px;
}

.asin-details-left {
    flex: 0 0 380px;
    max-width: 380px;
}

.asin-details-right {
    flex: 1;
    min-width: 0;
    max-width: 100%;
}

/* Images Section - Horizontal Layout */
.images-section {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.image-container {
    flex: 1;
    text-align: center;
    min-width: 180px;
    position: relative;
}

.asin-details-image,
.instruction-card-image {
    margin-bottom: 8px;
}

.asin-details-thumbnail,
.instruction-card-thumbnail {
    width: 100%;
    max-width: 180px;
    height: auto;
    max-height: 200px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    cursor: pointer;
    transition: transform 0.3s ease;
}

.asin-details-thumbnail.enlarged {
    transform: scale(1.2);
}

.upload-overlay {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 25px;
    left: 0;
    background-color: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.image-container:hover .upload-overlay {
    opacity: 1;
}

.btn-upload-image {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}

.btn-upload-image:hover {
    background-color: #0056b3;
}

.image-label {
    font-size: 12px;
    font-weight: 600;
    color: #495057;
    text-align: center;
    padding: 4px 8px;
    background-color: #f8f9fa;
    border-radius: 4px;
    border: 1px solid #dee2e6;
}

.upload-status {
    font-size: 10px;
    margin-left: 5px;
}

.upload-status.success {
    color: #28a745;
}

/* ASIN Details Info */
.asin-details-info {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
}

.asin-details-title {
    margin-bottom: 15px;
    color: #495057;
    font-size: 18px;
    word-wrap: break-word;
}

.asin-details-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}

.asin-details-row:last-child {
    border-bottom: none;
}

.asin-details-label {
    font-weight: 600;
    color: #495057;
    min-width: 80px;
}

.asin-details-value {
    color: #6c757d;
    text-align: right;
    flex: 1;
    margin-left: 10px;
    word-wrap: break-word;
}

/* Input styling for details */
.details-input {
    width: 120px;
    padding: 4px 8px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 12px;
}

.details-input:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

/* ASIN Details Actions */
.asin-details-actions {
    margin-top: 15px;
    text-align: center;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.btn-save-asin-details {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    margin-bottom: 10px;
}

.btn-save-asin-details:hover {
    background-color: #0056b3;
}

.btn-save-asin-details:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
}

.asin-details-stores-section {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.asin-details-stores-section h4 {
    margin-bottom: 10px;
    color: #495057;
    font-size: 14px;
}

.stores-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.store-item {
    background-color: #007bff;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Related ASINs Section */
.asin-details-related-section {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.asin-details-related-section h4 {
    margin-bottom: 10px;
    color: #495057;
    font-size: 14px;
}

.related-asins-details {
    background-color: #ffffff;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #dee2e6;
}

.related-asin-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 0;
    border-bottom: 1px solid #f8f9fa;
}

.related-asin-item:last-child {
    border-bottom: none;
}

.related-asin-label {
    font-weight: 600;
    color: #495057;
    font-size: 12px;
}

.related-asin-value {
    color: #007bff;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    text-align: right;
}

.related-asin-input {
    width: 150px;
    padding: 4px 8px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 12px;
    font-family: 'Courier New', monospace;
}

.related-asin-input:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.related-asins-actions {
    margin-top: 15px;
    text-align: center;
}

.btn-save-related {
    background-color: #28a745;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}

.btn-save-related:hover {
    background-color: #218838;
}

.btn-save-related:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
}

/* FNSKU Table Section */
.asin-details-section h4 {
    margin-bottom: 15px;
    color: #495057;
    font-size: 16px;
}

.responsive-table-container {
    width: 100%;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.asin-details-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    table-layout: fixed;
}

.asin-details-table thead {
    background-color: #1a252f !important;
    color: white !important;
}

.asin-details-table thead th {
    padding: 12px 8px !important;
    text-align: left !important;
    font-weight: 700 !important;
    font-size: 12px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    color: white !important;
    background-color: #1a252f !important;
}

.asin-details-table thead th:nth-child(1) {
    width: 35%;
}

.asin-details-table thead th:nth-child(2) {
    width: 30%;
}

.asin-details-table thead th:nth-child(3) {
    width: 20%;
}

.asin-details-table thead th:nth-child(4) {
    width: 15%;
}

.asin-details-table tbody td {
    padding: 10px 8px;
    border-bottom: 1px solid #dee2e6;
    color: #495057;
    font-size: 12px;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.asin-details-table .units-cell {
    text-align: center;
    font-weight: 600;
    color: #007bff;
}

.asin-details-table tbody tr:nth-child(even) {
    background-color: #f8f9fa;
}

.asin-details-table tbody tr:hover {
    background-color: #e9ecef;
}

/* Grade Display Styles */
.grade-cell {
    text-align: center;
    padding: 8px !important;
}

.grade-display {
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: bold;
    font-size: 12px;
    min-width: 60px;
    text-align: center;
    display: inline-block;
}

/* Grade Color Classes */
.grade-a-plus, .grade-a {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.grade-b-plus, .grade-b {
    background-color: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.grade-c-plus, .grade-c {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.grade-d, .grade-f {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Footer */
.asin-details-footer {
    padding: 20px;
    border-top: 1px solid #dee2e6;
    text-align: right;
    background-color: #f8f9fa;
}

.btn-close-details {
    background-color: #6c757d;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}
</style>