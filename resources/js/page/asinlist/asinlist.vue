.asin-details-section h4 {
    margin-bottom: 15px;
    color: #495057;
    font-size: 16px;
}<template>
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
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="fnsku in item.fnskus" :key="fnsku.FNSKU">
                                                        <td class="fnsku-code">{{ fnsku.FNSKU }}</td>
                                                        <td>{{ fnsku.MSKU || '-' }}</td>
                                                        <td>{{ fnsku.storename }}</td>
                                                        <td>{{ fnsku.Units || 0 }}</td>
                                                    </tr>
                                                    <tr v-if="!item.fnskus || item.fnskus.length === 0">
                                                        <td colspan="4" class="text-center">No FNSKUs found</td>
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
                    <button class="asin-details-close" @click="closeAsinDetailsModal">&times;</button>
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
                                    </div>
                                    <div class="image-label">Instruction Card</div>
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
                                    <span class="asin-details-value">{{ selectedAsin.EAN || '-' }}</span>
                                </div>
                                <div class="asin-details-row">
                                    <span class="asin-details-label">UPC:</span>
                                    <span class="asin-details-value">{{ selectedAsin.UPC || '-' }}</span>
                                </div>
                                <div class="asin-details-row">
                                    <span class="asin-details-label">Total FNSKUs:</span>
                                    <span class="asin-details-value">{{ selectedAsin.fnsku_count }}</span>
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
                                <div class="asin-details-related-section" v-if="selectedAsin.ParentAsin || selectedAsin.CousinASIN || selectedAsin.UpgradeASIN || selectedAsin.GrandASIN">
                                    <h4>Related ASINs</h4>
                                    <div class="related-asins-details">
                                        <div v-if="selectedAsin.ParentAsin" class="related-asin-item">
                                            <span class="related-asin-label">Parent ASIN:</span>
                                            <span class="related-asin-value">{{ selectedAsin.ParentAsin }}</span>
                                        </div>
                                        <div v-if="selectedAsin.CousinASIN" class="related-asin-item">
                                            <span class="related-asin-label">Cousin ASIN:</span>
                                            <span class="related-asin-value">{{ selectedAsin.CousinASIN }}</span>
                                        </div>
                                        <div v-if="selectedAsin.UpgradeASIN" class="related-asin-item">
                                            <span class="related-asin-label">Upgrade ASIN:</span>
                                            <span class="related-asin-value">{{ selectedAsin.UpgradeASIN }}</span>
                                        </div>
                                        <div v-if="selectedAsin.GrandASIN" class="related-asin-item">
                                            <span class="related-asin-label">Grand ASIN:</span>
                                            <span class="related-asin-value">{{ selectedAsin.GrandASIN }}</span>
                                        </div>
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
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="fnsku in selectedAsin.fnskus" :key="fnsku.FNSKU">
                                                    <td class="fnsku-code">{{ fnsku.FNSKU }}</td>
                                                    <td>{{ fnsku.MSKU || '-' }}</td>
                                                    <td class="units-cell">{{ fnsku.Units || 0 }}</td>
                                                </tr>
                                                <tr v-if="!selectedAsin.fnskus || selectedAsin.fnskus.length === 0">
                                                    <td colspan="3" class="text-center">No FNSKUs found</td>
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

/* Responsive table container */
.responsive-table-container {
    width: 100%;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

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

.asin-details-related-section {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.asin-details-related-section h4,
.asin-details-stores-section h4 {
    margin-bottom: 10px;
    color: #495057;
    font-size: 14px;
}

.asin-details-stores-section {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
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

.asin-details-section h4 {
    margin-bottom: 15px;
    color: #495057;
    font-size: 16px;
}

.asin-details-fnskus {
    width: 100%;
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
    width: 40%;
}

.asin-details-table thead th:nth-child(2) {
    width: 35%;
}

.asin-details-table thead th:nth-child(3) {
    width: 25%;
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

.asin-details-table tbody td {
    padding: 10px 12px;
    border-bottom: 1px solid #dee2e6;
    color: #495057;
    font-size: 12px;
}

.asin-details-table tbody tr:nth-child(even) {
    background-color: #f8f9fa;
}

.asin-details-table tbody tr:hover {
    background-color: #e9ecef;
}

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

.btn-close-details:hover {
    background-color: #5a6268;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .fnsku-detail-table {
        min-width: 500px;
    }
    
    .fnsku-detail-table thead th,
    .fnsku-detail-table tbody td {
        padding: 8px 6px;
        font-size: 11px;
    }
    
    .asin-details-layout {
        flex-direction: column;
    }
    
    .asin-details-left {
        flex: none;
        max-width: 100%;
    }
    
    .asin-details-content {
        width: 100%;
        height: 100vh;
        border-radius: 0;
        max-height: 100vh;
    }
    
    .images-section {
        flex-direction: column;
        gap: 15px;
    }
    
    .image-container {
        min-width: 100%;
    }
    
    .related-asins {
        max-width: 150px;
    }
    
    .product-container {
        max-width: 100%;
    }
    
    .responsive-table-container {
        margin: 0;
        overflow-x: auto;
    }
    
    .asin-details-table {
        min-width: 400px;
    }
}
</style>