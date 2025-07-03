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
                                                        <td>{{ fnsku.grading || '-' }}</td>
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
                                        <span class="mobile-fnsku-value">{{ fnsku.grading || '-' }}</span>
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
                            <!-- Images Section -->
                            <div class="images-section">
                                <!-- ASIN Images Container -->
                                <div class="image-container">
                                    <div class="asin-images-main clickable" @click="openAsinImageModal">
                                        <img :src="getMainAsinImagePath(selectedAsin.ASIN)"
                                            :alt="`ASIN images for ${selectedAsin.ASIN}`"
                                            class="asin-images-main-thumbnail" />
                                        
                                        <!-- Small thumbnails overlay -->
                                        <div class="asin-images-thumbnails">
                                            <div class="small-thumb asin-thumb" :class="{ 'has-image': getImagePath(selectedAsin.ASIN) !== defaultImagePath }">
                                                <img :src="getImagePath(selectedAsin.ASIN)"
                                                     class="small-thumb-img" />
                                                <span class="thumb-label">IMG</span>
                                            </div>
                                            <div class="small-thumb vector-thumb" :class="{ 'has-image': hasVectorImage(selectedAsin.ASIN) }">
                                                <img :src="getMainVectorImagePath(selectedAsin.ASIN)"
                                                     class="small-thumb-img" />
                                                <span class="thumb-label">VEC</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="image-label">
                                        ASIN Images
                                    </div>
                                </div>

                                <!-- Instruction Card Container -->
                                <div class="image-container">
                                    <div class="instruction-card-main clickable" @click="openInstructionCardModal">
                                        <img :src="getMainInstructionCardPath(selectedAsin.ASIN)"
                                            :alt="`Instruction cards for ${selectedAsin.ASIN}`"
                                            class="instruction-card-main-thumbnail" />
                                        
                                        <!-- Small thumbnails overlay -->
                                        <div class="instruction-card-thumbnails">
                                            <div class="small-thumb" :class="{ 'has-image': hasInstructionCard(selectedAsin.ASIN, 1) }">
                                                <img :src="getInstructionCardPath(selectedAsin.ASIN, 1)"
                                                     class="small-thumb-img" />
                                                <span class="thumb-number">1</span>
                                            </div>
                                            <div class="small-thumb" :class="{ 'has-image': hasInstructionCard(selectedAsin.ASIN, 2) }">
                                                <img :src="getInstructionCardPath(selectedAsin.ASIN, 2)"
                                                     class="small-thumb-img" />
                                                <span class="thumb-number">2</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="image-label">
                                        Instruction Cards
                                    </div>
                                </div>

                                <!-- User Manual Container -->
                                <div class="image-container">
                                    <div class="user-manual-container" :class="{ 'has-manual': hasUserManual(selectedAsin.ASIN) }">
                                        <div class="user-manual-icon" v-if="hasUserManual(selectedAsin.ASIN)">
                                            <a :href="getUserManualPath(selectedAsin.ASIN)" 
                                               target="_blank" 
                                               class="user-manual-link">
                                                <i class="fas fa-file-pdf"></i>
                                                <span>View Manual</span>
                                            </a>
                                        </div>
                                        <div class="user-manual-icon no-manual" v-else>
                                            <i class="fas fa-file-pdf"></i>
                                            <span>No Manual</span>
                                        </div>
                                        
                                        <!-- Upload section for edit mode -->
                                        <div v-if="editMode" class="user-manual-upload">
                                            <input type="file" 
                                                   ref="userManualUpload"
                                                   @change="handleUserManualUpload"
                                                   accept="application/pdf"
                                                   style="display: none" />
                                            <button class="btn-upload-manual" 
                                                    @click="$refs.userManualUpload.click()" 
                                                    :disabled="userManualUploading">
                                                <i class="fas fa-upload"></i> 
                                                {{ userManualUploading ? 'Uploading...' : 'Upload PDF' }}
                                            </button>
                                        </div>
                                    </div>
                                    <div class="image-label">
                                        User Manual
                                    </div>
                                </div>
                            </div>
                            
                            <div class="asin-details-info">
                                <h3 class="asin-details-title">{{ selectedAsin.AStitle }}</h3>
                                
                                <!-- Basic Information Section -->
                                <div class="details-section">
                                    <h4 class="section-title">Basic Information</h4>
                                    <div class="asin-details-row">
                                        <span class="asin-details-label">ASIN:</span>
                                        <span class="asin-details-value">{{ selectedAsin.ASIN }}</span>
                                    </div>
                                    <div class="asin-details-row">
                                        <span class="asin-details-label">Meta Keyword:</span>
                                        <textarea v-if="editMode" 
                                                v-model="editedAsin.metakeyword"
                                                class="details-textarea"
                                                placeholder="Enter meta keywords"
                                                rows="2"></textarea>
                                        <span v-else class="asin-details-value">{{ selectedAsin.metakeyword || '-' }}</span>
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
                                        <span class="asin-details-label">Instruction Link:</span>
                                        <input v-if="editMode" 
                                               v-model="editedAsin.instructionlink"
                                               class="details-input instruction-link-input"
                                               placeholder="Enter instruction link URL"
                                               type="text" />
                                        <span v-else class="asin-details-value">
                                            <a v-if="selectedAsin.instructionlink" 
                                               :href="selectedAsin.instructionlink" 
                                               target="_blank" 
                                               class="instruction-link">
                                                <i class="fas fa-external-link-alt"></i> View Instructions
                                            </a>
                                            <span v-else>-</span>
                                        </span>
                                    </div>
                                    <div class="asin-details-row">
                                        <span class="asin-details-label">Transparency QR:</span>
                                        <textarea v-if="editMode" 
                                                v-model="editedAsin.TRANSPARENCY_QR_STATUS"
                                                class="details-textarea"
                                                placeholder="Enter transparency QR status"
                                                rows="3"></textarea>
                                        <span v-else class="asin-details-value">{{ selectedAsin.TRANSPARENCY_QR_STATUS || '-' }}</span>
                                    </div>
                                    <div class="asin-details-row">
                                        <span class="asin-details-label">User Manual:</span>
                                        <span class="asin-details-value">
                                            <a v-if="hasUserManual(selectedAsin.ASIN)" 
                                               :href="getUserManualPath(selectedAsin.ASIN)" 
                                               target="_blank" 
                                               class="user-manual-link-text">
                                                <i class="fas fa-file-pdf"></i> View PDF Manual
                                            </a>
                                            <span v-else>-</span>
                                        </span>
                                    </div>
                                    <div class="asin-details-row">
                                        <span class="asin-details-label">Total FNSKUs:</span>
                                        <span class="asin-details-value">{{ selectedAsin.fnsku_count }}</span>
                                    </div>
                                    
                                    <!-- Save button for ASIN details -->
                                    <div v-if="editMode" class="asin-details-actions">
                                        <button class="btn-save-asin-details" @click="saveAsinDetails" :disabled="savingAsinDetails">
                                            <i class="fas fa-save"></i> 
                                            {{ savingAsinDetails ? 'Saving...' : 'Save Basic Details' }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Amazon Dimensions Section (Read-only) -->
                                <div class="details-section amazon-dimensions">
                                    <h4 class="section-title">Amazon Dimensions (Read-only)</h4>
                                    <div class="dimensions-grid">
                                        <div class="dimension-item">
                                            <div class="dimension-label">AMZN Length:</div>
                                            <div class="dimension-value">{{ selectedAsin.dimension_length || '-' }}</div>
                                        </div>
                                        <div class="dimension-item">
                                            <div class="dimension-label">AMZN Width:</div>
                                            <div class="dimension-value">{{ selectedAsin.dimension_width || '-' }}</div>
                                        </div>
                                        <div class="dimension-item">
                                            <div class="dimension-label">AMZN Height:</div>
                                            <div class="dimension-value">{{ selectedAsin.dimension_height || '-' }}</div>
                                        </div>
                                        <div class="dimension-item">
                                            <div class="dimension-label">AMZN Weight:</div>
                                            <div class="dimension-value">
                                                {{ selectedAsin.weight_value ? `${selectedAsin.weight_value} ${selectedAsin.weight_unit || ''}` : '-' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Default Dimensions Section (Editable) -->
                                <div class="details-section default-dimensions">
                                    <h4 class="section-title">Default Dimensions (Editable)</h4>
                                    <div class="dimensions-grid">
                                        <div class="dimension-item">
                                            <div class="dimension-label">Def Length:</div>
                                            <div class="dimension-value">
                                                <input v-if="editMode" 
                                                       v-model="editedAsin.def_length"
                                                       class="dimension-input"
                                                       type="number"
                                                       step="0.01"
                                                       min="0"
                                                       placeholder="0.00" />
                                                <span v-else>{{ selectedAsin.white_length || '-' }}</span>
                                            </div>
                                        </div>
                                        <div class="dimension-item">
                                            <div class="dimension-label">Def Width:</div>
                                            <div class="dimension-value">
                                                <input v-if="editMode" 
                                                       v-model="editedAsin.def_width"
                                                       class="dimension-input"
                                                       type="number"
                                                       step="0.01"
                                                       min="0"
                                                       placeholder="0.00" />
                                                <span v-else>{{ selectedAsin.white_width || '-' }}</span>
                                            </div>
                                        </div>
                                        <div class="dimension-item">
                                            <div class="dimension-label">Def Height:</div>
                                            <div class="dimension-value">
                                                <input v-if="editMode" 
                                                       v-model="editedAsin.def_height"
                                                       class="dimension-input"
                                                       type="number"
                                                       step="0.01"
                                                       min="0"
                                                       placeholder="0.00" />
                                                <span v-else>{{ selectedAsin.white_height || '-' }}</span>
                                            </div>
                                        </div>
                                        <div class="dimension-item">
                                            <div class="dimension-label">Def Weight:</div>
                                            <div class="dimension-value">
                                                <div v-if="editMode" class="weight-input-group">
                                                    <input v-model="editedAsin.def_weight"
                                                           class="dimension-input weight-value"
                                                           type="number"
                                                           step="0.01"
                                                           min="0"
                                                           placeholder="0.00" />
                                                    <select v-model="editedAsin.def_weight_unit" class="weight-unit-select">
                                                        <option value="">Unit</option>
                                                        <option value="kg">kg</option>
                                                        <option value="lbs">lbs</option>
                                                        <option value="g">g</option>
                                                        <option value="oz">oz</option>
                                                    </select>
                                                </div>
                                                <span v-else>
                                                    {{ selectedAsin.white_value ? `${selectedAsin.white_value} ${selectedAsin.white_unit || ''}` : '-' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Save button for default dimensions -->
                                    <div v-if="editMode" class="dimensions-actions">
                                        <button class="btn-save-dimensions" @click="saveDefaultDimensions" :disabled="savingDefaultDimensions">
                                            <i class="fas fa-save"></i> 
                                            {{ savingDefaultDimensions ? 'Saving...' : 'Save Dimensions' }}
                                        </button>
                                    </div>
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
                                
                                <!-- Related ASINs Section -->
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

                        <!-- Right Column: FNSKU Details -->
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
                                                    <td class="grade-cell">{{ fnsku.grading || '-' }}</td>
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

        <!-- ASIN Image Management Modal -->
        <div v-if="showAsinImageModal" class="asin-image-modal">
            <div class="asin-image-modal-content">
                <div class="asin-image-modal-header">
                    <h3>Manage ASIN Images - {{ selectedAsin?.ASIN }}</h3>
                    <button class="modal-close" @click="closeAsinImageModal">&times;</button>
                </div>
                
                <div class="asin-image-modal-body">
                    <div class="image-management-layout">
                        <!-- ASIN Image -->
                        <div class="image-slot">
                            <div class="image-slot-header">
                                <h4>Main ASIN Image</h4>
                            </div>
                            <div class="image-slot-image">
                                <img :src="getImagePath(selectedAsin?.ASIN)"
                                     :alt="`ASIN image for ${selectedAsin?.ASIN}`"
                                     class="image-slot-thumbnail"
                                     @error="handleImageError($event, null)" />
                            </div>
                            <div class="image-slot-actions">
                                <input type="file" 
                                       ref="asinImageUpload"
                                       @change="handleAsinImageUpload"
                                       accept="image/*"
                                       style="display: none" />
                                <button class="btn-upload-image" @click="$refs.asinImageUpload.click()" :disabled="asinImageUploading">
                                    <i class="fas fa-upload"></i> 
                                    {{ asinImageUploading ? 'Uploading...' : 'Upload/Update' }}
                                </button>
                            </div>
                        </div>

                        <div class="image-slot">
                            <div class="image-slot-header">
                                <h4>Vector Image</h4>
                            </div>
                            <div class="image-slot-image">
                                <img :src="hasVectorImage(selectedAsin?.ASIN) ? getVectorImagePath(selectedAsin?.ASIN) : createDefaultVectorSVG()"
                                     :alt="`Vector image for ${selectedAsin?.ASIN}`"
                                     class="image-slot-thumbnail"
                                     @error="handleImageError($event, null)" />
                            </div>
                            <div class="image-slot-actions">
                                <input type="file" 
                                       ref="vectorImageUpload"
                                       @change="handleVectorImageUpload"
                                       accept="image/png,image/jpg,image/jpeg"
                                       style="display: none" />
                                <button class="btn-upload-vector" @click="$refs.vectorImageUpload.click()" :disabled="vectorImageUploading">
                                    <i class="fas fa-upload"></i> 
                                    {{ vectorImageUploading ? 'Uploading...' : 'Upload/Update' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="asin-image-modal-footer">
                    <button class="btn-close-modal" @click="closeAsinImageModal">Close</button>
                </div>
            </div>
        </div>

        <!-- Instruction Card Management Modal -->
        <div v-if="showInstructionCardModal" class="instruction-card-modal">
            <div class="instruction-card-modal-content">
                <div class="instruction-card-modal-header">
                    <h3>Manage Instruction Cards - {{ selectedAsin?.ASIN }}</h3>
                    <button class="modal-close" @click="closeInstructionCardModal">&times;</button>
                </div>
                
                <div class="instruction-card-modal-body">
                    <div class="card-management-layout">
                        <!-- Card 1 -->
                        <div class="card-slot">
                            <div class="card-slot-header">
                                <h4>Instruction Card 1</h4>
                            </div>
                            <div class="card-slot-image">
                                <img :src="getInstructionCardPath(selectedAsin?.ASIN, 1)"
                                     :alt="`Instruction card 1 for ${selectedAsin?.ASIN}`"
                                     class="card-slot-thumbnail"
                                     @error="handleInstructionCardError($event, 1)" />
                            </div>
                            <div class="card-slot-actions">
                                <input type="file" 
                                       :ref="`cardUpload1`"
                                       @change="(e) => handleInstructionCardUpload(e, 1)"
                                       accept="image/*"
                                       style="display: none" />
                                <button class="btn-upload-card" @click="$refs.cardUpload1.click()" :disabled="instructionCardUploading === 1">
                                    <i class="fas fa-upload"></i> 
                                    {{ instructionCardUploading === 1 ? 'Uploading...' : 'Upload/Update' }}
                                </button>
                            </div>
                        </div>

                        <!-- Card 2 -->
                        <div class="card-slot">
                            <div class="card-slot-header">
                                <h4>Instruction Card 2</h4>
                            </div>
                            <div class="card-slot-image">
                                <img :src="getInstructionCardPath(selectedAsin?.ASIN, 2)"
                                     :alt="`Instruction card 2 for ${selectedAsin?.ASIN}`"
                                     class="card-slot-thumbnail"
                                     @error="handleInstructionCardError($event, 2)" />
                            </div>
                            <div class="card-slot-actions">
                                <input type="file" 
                                       :ref="`cardUpload2`"
                                       @change="(e) => handleInstructionCardUpload(e, 2)"
                                       accept="image/*"
                                       style="display: none" />
                                <button class="btn-upload-card" @click="$refs.cardUpload2.click()" :disabled="instructionCardUploading === 2">
                                    <i class="fas fa-upload"></i> 
                                    {{ instructionCardUploading === 2 ? 'Uploading...' : 'Upload/Update' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="instruction-card-modal-footer">
                    <button class="btn-close-modal" @click="closeInstructionCardModal">Close</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import asinlist from "./asinlist.js";
export default asinlist;
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

/* Images Section - Updated for uniform sizing */
.images-section {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.image-container {
    flex: 1;
    text-align: center;
    min-width: 120px;
    max-width: 180px;
    position: relative;
}

.asin-details-image {
    margin-bottom: 8px;
}

.asin-details-thumbnail {
    width: 100%;
    max-width: 180px;
    height: 200px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    cursor: pointer;
    transition: transform 0.3s ease;
}

.asin-details-thumbnail.enlarged {
    transform: scale(1.2);
}

/* Shared styles for image containers with overlays */
.instruction-card-main,
.asin-images-main {
    position: relative;
    margin-bottom: 8px;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s ease;
}

.instruction-card-main:hover,
.asin-images-main:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.instruction-card-main-thumbnail,
.asin-images-main-thumbnail {
    width: 100%;
    max-width: 180px;
    height: 200px;
    object-fit: cover;
    border: 1px solid #dee2e6;
    display: block;
    border-radius: 8px;
}

/* Small thumbnails overlay - shared styles */
.instruction-card-thumbnails,
.asin-images-thumbnails {
    position: absolute;
    bottom: 5px;
    right: 5px;
    display: flex;
    gap: 3px;
}

.small-thumb {
    width: 30px;
    height: 30px;
    border-radius: 4px;
    border: 2px solid #fff;
    position: relative;
    overflow: hidden;
    background-color: #f8f9fa;
}

.small-thumb.has-image {
    border-color: #28a745;
}

/* Specific styling for ASIN image thumbnails */
.asin-images-thumbnails .asin-thumb.has-image {
    border-color: #28a745;
}

.asin-images-thumbnails .vector-thumb.has-image {
    border-color: #6f42c1;
}

.small-thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 0.9;
}

/* Thumbnail labels and numbers */
.thumb-number,
.thumb-label {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: rgba(0,0,0,0.7);
    color: white;
    font-weight: bold;
}

.thumb-number {
    font-size: 10px;
}

.thumb-label {
    font-size: 8px;
}

.small-thumb.has-image .thumb-number,
.small-thumb.has-image .thumb-label {
    display: none;
}

/* User Manual Container Styles */
.user-manual-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 200px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background-color: #f8f9fa;
    transition: all 0.3s ease;
}

.user-manual-container.has-manual {
    background-color: #e8f5e8;
    border-color: #28a745;
}

.user-manual-icon {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 20px;
}

.user-manual-icon i {
    font-size: 48px;
    color: #dc3545;
    transition: color 0.3s ease;
}

.user-manual-container.has-manual .user-manual-icon i {
    color: #28a745;
}

.user-manual-icon.no-manual i {
    color: #6c757d;
    opacity: 0.5;
}

.user-manual-icon span {
    font-size: 12px;
    font-weight: 600;
    color: #495057;
    text-align: center;
}

.user-manual-link {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    color: inherit;
    transition: all 0.3s ease;
    padding: 10px;
    border-radius: 6px;
}

.user-manual-link:hover {
    background-color: rgba(40, 167, 69, 0.1);
    transform: translateY(-2px);
    text-decoration: none;
    color: inherit;
}

.user-manual-upload {
    margin-top: 15px;
    width: 100%;
    display: flex;
    justify-content: center;
}

.btn-upload-manual {
    background-color: #dc3545;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-upload-manual:hover:not(:disabled) {
    background-color: #c82333;
    transform: translateY(-1px);
}

.btn-upload-manual:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
    transform: none;
}

.image-label {
    font-size: 11px;
    font-weight: 600;
    color: #495057;
    text-align: center;
    padding: 4px 6px;
    background-color: #f8f9fa;
    border-radius: 4px;
    border: 1px solid #dee2e6;
    margin-top: 5px;
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

/* Details Sections Styling */
.details-section {
    margin-bottom: 25px;
    padding: 15px;
    background-color: #ffffff;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.section-title {
    margin: 0 0 15px 0;
    color: #495057;
    font-size: 16px;
    font-weight: 600;
    border-bottom: 2px solid #007bff;
    padding-bottom: 8px;
}

.amazon-dimensions .section-title {
    border-bottom-color: #28a745;
}

.default-dimensions .section-title {
    border-bottom-color: #ffc107;
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
    min-width: 120px;
    font-size: 13px;
}

.asin-details-value {
    color: #6c757d;
    text-align: right;
    flex: 1;
    margin-left: 10px;
    word-wrap: break-word;
    font-size: 13px;
}

/* Input styling for details */
.details-input {
    width: 120px;
    padding: 6px 10px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 12px;
    transition: border-color 0.3s ease;
}

.details-input:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.details-textarea {
    width: 200px;
    padding: 6px 10px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 12px;
    resize: vertical;
    min-height: 60px;
    font-family: inherit;
    transition: border-color 0.3s ease;
}

.details-textarea:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.instruction-link-input {
    width: 200px !important;
    font-size: 11px;
}

/* Dimensions Grid Styling */
.dimensions-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.dimension-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.dimension-label {
    font-weight: 600;
    color: #495057;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.dimension-value {
    color: #6c757d;
    font-size: 13px;
    font-weight: 500;
}

.dimension-input {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 12px;
    transition: border-color 0.3s ease;
}

.dimension-input:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.weight-input-group {
    display: flex;
    gap: 8px;
    align-items: center;
}

.weight-value {
    flex: 2;
}

.weight-unit-select {
    flex: 1;
    padding: 6px 8px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 12px;
    background-color: white;
    transition: border-color 0.3s ease;
}

.weight-unit-select:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

/* Action buttons styling */
.dimensions-actions {
    text-align: center;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.btn-save-dimensions {
    background-color: #ffc107;
    color: #212529;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-save-dimensions:hover:not(:disabled) {
    background-color: #e0a800;
    transform: translateY(-1px);
}

.btn-save-dimensions:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
    transform: none;
    color: white;
}

/* Link styling for details */
.instruction-link,
.user-manual-link-text,
.vector-image-link-text {
    text-decoration: none;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: color 0.3s ease;
}

.instruction-link {
    color: #007bff;
}

.instruction-link:hover {
    color: #0056b3;
    text-decoration: underline;
}

.user-manual-link-text {
    color: #dc3545;
}

.user-manual-link-text:hover {
    color: #c82333;
    text-decoration: underline;
}

.vector-image-link-text {
    color: #6f42c1;
}

.vector-image-link-text:hover {
    color: #5a32a3;
    text-decoration: underline;
}

.instruction-link i,
.user-manual-link-text i,
.vector-image-link-text i {
    font-size: 10px;
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
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-save-asin-details:hover:not(:disabled) {
    background-color: #0056b3;
    transform: translateY(-1px);
}

.btn-save-asin-details:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
    transform: none;
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
    padding: 8px 0;
    border-bottom: 1px solid #f8f9fa;
}

.related-asin-item:last-child {
    border-bottom: none;
}

.related-asin-label {
    font-weight: 600;
    color: #495057;
    font-size: 12px;
    min-width: 100px;
}

.related-asin-value {
    color: #007bff;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    text-align: right;
}

.related-asin-input {
    width: 150px;
    padding: 6px 10px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 12px;
    font-family: 'Courier New', monospace;
    transition: border-color 0.3s ease;
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
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-save-related:hover:not(:disabled) {
    background-color: #218838;
    transform: translateY(-1px);
}

.btn-save-related:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
    transform: none;
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
    overflow-x: auto;
}

.asin-details-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    table-layout: fixed;
    min-width: 600px;
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
    white-space: nowrap;
}

.asin-details-table thead th:nth-child(1) {
    width: 40%;
}

.asin-details-table thead th:nth-child(2) {
    width: 30%;
}

.asin-details-table thead th:nth-child(3) {
    width: 15%;
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

.grade-cell {
    text-align: center;
    padding: 8px !important;
    font-weight: 600;
    color: #28a745;
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
    padding: 12px 24px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s ease;
}

.btn-close-details:hover {
    background-color: #5a6268;
}

/* Modal Shared Styles */
.instruction-card-modal,
.asin-image-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1100;
}

.instruction-card-modal-content,
.asin-image-modal-content {
    background-color: white;
    border-radius: 12px;
    width: 90%;
    max-width: 800px;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.instruction-card-modal-header,
.asin-image-modal-header {
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #f8f9fa;
    border-radius: 12px 12px 0 0;
}

.instruction-card-modal-header h3,
.asin-image-modal-header h3 {
    margin: 0;
    color: #495057;
    font-size: 18px;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6c757d;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.3s ease;
}

.modal-close:hover {
    color: #495057;
}

.instruction-card-modal-body,
.asin-image-modal-body {
    padding: 30px;
}

.card-management-layout,
.image-management-layout {
    display: flex;
    gap: 30px;
    justify-content: center;
}

.card-slot,
.image-slot {
    flex: 1;
    max-width: 300px;
    text-align: center;
}

.card-slot-header,
.image-slot-header {
    margin-bottom: 15px;
}

.card-slot-header h4,
.image-slot-header h4 {
    margin: 0;
    color: #495057;
    font-size: 16px;
    font-weight: 600;
}

.card-slot-image,
.image-slot-image {
    margin-bottom: 20px;
    border-radius: 8px;
    overflow: hidden;
    border: 2px solid #dee2e6;
    background-color: #f8f9fa;
}

.card-slot-thumbnail,
.image-slot-thumbnail {
    width: 100%;
    height: 250px;
    object-fit: cover;
    display: block;
}

.card-slot-actions,
.image-slot-actions {
    text-align: center;
}

/* Upload button styles */
.btn-upload-card,
.btn-upload-image,
.btn-upload-vector {
    border: none;
    padding: 12px 24px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    min-width: 150px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin: 0 auto;
    color: white;
}

.btn-upload-card {
    background-color: #007bff;
}

.btn-upload-card:hover:not(:disabled) {
    background-color: #0056b3;
    transform: translateY(-1px);
}

.btn-upload-image {
    background-color: #28a745;
}

.btn-upload-image:hover:not(:disabled) {
    background-color: #218838;
    transform: translateY(-1px);
}

.btn-upload-vector {
    background-color: #6f42c1;
}

.btn-upload-vector:hover:not(:disabled) {
    background-color: #5a32a3;
    transform: translateY(-1px);
}

.btn-upload-card:disabled,
.btn-upload-image:disabled,
.btn-upload-vector:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
    transform: none;
}

.instruction-card-modal-footer,
.asin-image-modal-footer {
    padding: 20px;
    border-top: 1px solid #dee2e6;
    text-align: right;
    background-color: #f8f9fa;
    border-radius: 0 0 12px 12px;
}

.btn-close-modal {
    background-color: #6c757d;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s ease;
}

.btn-close-modal:hover {
    background-color: #5a6268;
}

/* Error and success states */
.instruction-card-thumbnail[style*="opacity: 0.5"],
.card-slot-thumbnail[style*="opacity: 0.5"] {
    filter: grayscale(100%);
    border-style: dashed;
}

.instruction-card-thumbnail.uploaded,
.card-slot-thumbnail.uploaded {
    border-color: #28a745;
    box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.25);
}

/* Mobile Responsive Updates */
@media (max-width: 768px) {
    .images-section {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    
    .image-container {
        max-width: 100%;
        margin-bottom: 0;
    }
    
    .user-manual-container {
        height: 150px;
    }
    
    .user-manual-icon i {
        font-size: 36px;
    }
    
    .user-manual-icon span {
        font-size: 11px;
    }
    
    .instruction-link-input {
        width: 150px !important;
    }

    .details-textarea {
        width: 150px !important;
    }

    .card-management-layout,
    .image-management-layout {
        flex-direction: column;
        gap: 20px;
    }

    .card-slot,
    .image-slot {
        max-width: 100%;
    }

    .instruction-card-modal-content,
    .asin-image-modal-content {
        width: 95%;
        margin: 10px;
    }

    .asin-details-layout {
        flex-direction: column;
    }

    .asin-details-left {
        flex: none;
        max-width: 100%;
        margin-bottom: 20px;
    }

    .dimensions-grid {
        grid-template-columns: 1fr;
    }

    .weight-input-group {
        flex-direction: column;
        gap: 5px;
    }

    .weight-value {
        flex: none;
    }

    .weight-unit-select {
        flex: none;
    }

    .asin-details-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }

    .asin-details-label {
        min-width: auto;
    }

    .asin-details-value {
        text-align: left;
        margin-left: 0;
    }

    .details-input,
    .related-asin-input {
        width: 100%;
    }
}
</style>