<template>
    <div class="vue-container receiving-module">
        <div class="top-header">
            <h1 class="module-title">Receiving Module</h1>

            <div class="header-buttons">
                <button class="btn scan-button" @click="openScannerModal">
                    <i class="fas fa-barcode"></i> Scan Items
                </button>
            </div>
        </div>

        <!-- Scanner Component -->
        <scanner-component scanner-title="Received Scanner" storage-prefix="received" :enable-camera="true"
            :display-fields="['Trackingnumber', 'FirstSN', 'SecondSN', 'PCN', 'Basket']"
            :api-endpoint="'/api/received/process-scan'" :hide-button="true" @process-scan="handleScanProcess"
            @hardware-scan="handleHardwareScan" @scanner-opened="handleScannerOpened"
            @scanner-closed="handleScannerClosed" @scanner-reset="handleScannerReset" @mode-changed="handleModeChange"
            ref="scanner">
            <!-- Define custom input fields for Received module -->
            <template #input-fields>
                <!-- Step 1: Tracking Number Input -->
                <div class="input-group" v-if="currentStep === 1">
                    <label>Tracking Number:</label>
                    <input type="text" v-model="trackingNumber" placeholder="Enter Tracking Number..."
                        @input="handleTrackingInput" @keyup.enter="verifyTrackingNumber" ref="trackingInput" />
                    <!-- Only show Verify Tracking button in Manual mode -->
                    <button v-if="showManualInput" @click="verifyTrackingNumber" class="verify-button">Verify
                        Tracking</button>
                </div>

                <!-- Step 2: Pass/Fail Buttons (shown after tracking verification) -->
                <div class="input-group" v-if="currentStep === 2">
                    <div class="tracking-verified">
                        <div class="success-banner">Tracking found for {{ trackingNumber }}</div>
                    </div>
                    <div class="pass-fail-buttons">
                        <button @click="passItem" class="pass-button">
                            <i class="fas fa-check"></i> Pass
                        </button>
                        <button @click="failItem" class="fail-button">
                            <i class="fas fa-times"></i> Fail
                        </button>
                    </div>
                </div>

                <!-- Step 3: First Serial Number Input -->
                <div class="input-group" v-if="currentStep === 3">
                    <label>First Serial Number:</label>
                    <input type="text" v-model="firstSerialNumber" placeholder="Scan First Serial Number..."
                        @input="handleFirstSerialInput" @keyup.enter="processFirstSerial" ref="firstSerialInput" />
                    <button v-if="showManualInput" @click="processFirstSerial" class="scan-button">Scan</button>
                </div>

                <!-- Step 4: Second Serial Number Input (with Skip option) -->
                <div class="input-group" v-if="currentStep === 4">
                    <label>Second Serial Number:</label>
                    <input type="text" v-model="secondSerialNumber" placeholder="Scan Second Serial Number (or Skip)..."
                        @input="handleSecondSerialInput" @keyup.enter="processSecondSerial" ref="secondSerialInput" />
                    <div class="button-group">
                        <button v-if="showManualInput" @click="processSecondSerial" class="scan-button">Scan</button>
                        <button @click="skipSecondSerial" class="skip-button">Skip</button>
                    </div>
                </div>

                <!-- Step 5: PCN Input  -->
                <div class="input-group" v-if="currentStep === 5">
                    <label>PCN (Product Control Number):</label>
                    <input type="text" v-model="pcnNumber" placeholder="Scan PCN Number..." @input="handlePcnInput"
                        @keyup.enter="processPcnNumber" ref="pcnInput" />
                    <div class="container-type-hint">Enter PCN format: PCN followed by numbers (e.g., PCN12345)</div>
                    <button v-if="showManualInput" @click="processPcnNumber" class="scan-button">Scan</button>
                </div>

                <!-- Step 6: Basket Number Input (now step 6) -->
                <div class="input-group" v-if="currentStep === 6">
                    <label>Basket/Container Number:</label>
                    <input type="text" v-model="basketNumber" placeholder="Enter BKT/SH/ENV + numbers..."
                        @input="handleBasketInput" @keyup.enter="processBasketNumber" ref="basketInput" />
                    <div class="container-type-hint">Enter numbers with prefix: BKT (Basket), SH (Shelf), or ENV
                        (Envelope)</div>
                    <button v-if="showManualInput" @click="processBasketNumber" class="scan-button">Submit</button>
                </div>
            </template>
        </scanner-component>

        <!-- Pagination with centered layout -->
        <div class="pagination-container">
            <div class="pagination-wrapper">
                <div class="pagination">
                    <button @click="prevPage" :disabled="currentPage === 1" class="pagination-button">
                        <i class="fas fa-chevron-left"></i> Back
                    </button>
                    <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
                    <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button">
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>

                <div class="per-page-selector">
                    <select v-model="perPage" @change="changePerPage" class="per-page-select">
                        <option v-for="option in [10, 15, 20, 50, 100]" :key="option" :value="option">
                            {{ option }} per page
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Desktop Table Container -->
        <div class="table-container desktop-view">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th class="check-column">
                            <div class="th-content">
                                <input type="checkbox" @click="toggleAll" v-model="selectAll" />
                            </div>
                        </th>
                        <th class="product-name">
                            <div class="th-content">
                                <span class="sortable" @click="sortBy('AStitle')">
                                    Product Name
                                    <i v-if="sortColumn === 'AStitle'"
                                        :class="sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                                </span>

                                <button class="btn-showDetails"
                                    @click="toggleDetailsVisibility">{{ showDetails ? 'Hide extra columns' : 'Show extra columns' }}
                                </button>
                            </div>
                        </th>
                        <th class="">Location</th>
                        <th class="">Added date</th>
                        <th class="">Updated date</th>
                        <th class="">Fnsku</th>
                        <th class="">Msku</th>
                        <th class="">Asin</th>
                        <th class="bg-warning-subtle" style="background-color: antiquewhite;" v-if="showDetails">FBM
                        </th>
                        <th class="bg-warning-subtle" style="background-color: antiquewhite;" v-if="showDetails">FBA
                        </th>
                        <th class="bg-warning-subtle" style="background-color: antiquewhite;" v-if="showDetails">
                            Outbound</th>
                        <th class="bg-warning-subtle" style="background-color: antiquewhite;" v-if="showDetails">Inbound
                        </th>
                        <th class="bg-warning-subtle" style="background-color: antiquewhite;" v-if="showDetails">
                            Unfulfillable</th>
                        <th class="bg-warning-subtle" style="background-color: antiquewhite;" v-if="showDetails">
                            Reserved</th>
                        <th class="">Fulfillment</th>
                        <th class="">Status</th>
                        <th class="">Serialnumber</th>
                        <th class="">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(item, index) in sortedInventory" :key="item.id">
                        <tr>
                            <td>
                                <input type="checkbox" v-model="item.checked" />
                                <span class="placeholder-date">{{ item.shipBy || '' }}</span>
                            </td>
                            <td class="product-details">
                                <div class="product-container">
                                    <div class="product-image-container" @click="openImageModal(item)">
                                        <!-- Use the actual file path for the main image -->
                                        <img :src="'/images/thumbnails/' + item.img1"
                                            :alt="item.ProductTitle || 'Product'"
                                            class="product-thumbnail clickable-image"
                                            @error="handleImageError($event)" />
                                        <div class="image-count-badge" v-if="countAdditionalImages(item) > 0">
                                            +{{ countAdditionalImages(item) }}
                                        </div>
                                    </div>
                                    <div class="product-info">
                                        <p>RT# : {{ item.rtcounter }}</p>
                                        <p>{{ item.ProductTitle }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span><strong></strong> {{ item.warehouselocation }}</span>
                            </td>

                            <td>
                                <span><strong></strong> {{ item.datedelivered }}</span>
                            </td>

                            <td>
                                <span><strong></strong> {{ item.lastDateUpdate }}</span>
                            </td>

                            <td>
                                <span><strong></strong> {{ item.FNSKUviewer }}</span>
                            </td>

                            <td>
                                <span><strong></strong> {{ item.MSKUviewer }}</span>
                            </td>
                            <td>
                                <span><strong></strong> {{ item.ASINviewer }}</span>
                            </td>
                            <!-- Hidden -->
                            <td v-if="showDetails">
                                <span><strong></strong> {{ item.FBMAvailable }}</span>
                            </td>
                            <td v-if="showDetails">
                                <span><strong></strong> {{ item.FbaAvailable }}</span>
                            </td>
                            <td v-if="showDetails">
                                <span><strong></strong> {{ item.Outbound }}</span>
                            </td>
                            <td v-if="showDetails">
                                <span><strong></strong> {{ item.Inbound }}</span>
                            </td>
                            <td v-if="showDetails">
                                <span><strong></strong> {{ item.Reserved }}</span>
                            </td>
                            <td v-if="showDetails">
                                <span><strong></strong> {{ item.Unfulfillable }}</span>
                            </td>
                            <!-- End Hidden -->
                            <td>
                                <span><strong></strong> {{ item.Fulfilledby }}</span>
                            </td>

                            <td>
                                <span><strong></strong> {{ item.Status }}</span>
                            </td>

                            <td>
                                <span><strong></strong> {{ item.serialnumber }}</span>
                            </td>

                            <!-- Button for more details -->
                            <td>
                                <div class="action-buttons">
                                    {{ item.totalquantity }}
                                    <button class="btn-expand" @click="toggleDetails(index)">
                                        {{ expandedRows[index] ? 'Less Details' : 'More Details' }}
                                    </button>
                                    <button class="btn-details">example</button>
                                    <button class="btn-details">example</button>
                                    <button class="btn-details">example</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="expandedRows[index]">
                            <td :colspan="showDetails ? 18 : 12">
                                <div class="expanded-content p-3 border rounded">
                                    <p><strong>Expanded Rows Here</strong></p>
                                    <p><strong>Product Name:</strong> {{ item.AStitle }}</p>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards View -->
        <div class="mobile-view">
            <div class="mobile-showDetails-container">
                <button class="btn-showDetailsM"
                    @click="toggleDetailsVisibility">{{ showDetails ? 'Hide extra columns' : 'Show extra columns' }}
                </button>
            </div>
            <div class="mobile-cards">
                <div class="mobile-card" v-for="(item, index) in sortedInventory" :key="item.id">
                    <div class="mobile-card-header">
                        <div class="mobile-checkbox">
                            <input type="checkbox" v-model="item.checked" />
                        </div>
                        <div class="mobile-product-image clickable">
                            <img :src="'/images/thumbnails/' + item.img1" :alt="item.ProductTitle || 'Product'"
                                class="product-thumbnail clickable-image" @error="handleImageError($event)" />
                            <div class="image-count-badge" v-if="countAdditionalImages(item) > 0">
                                +{{ countAdditionalImages(item) }}
                            </div>
                        </div>
                        <div class="mobile-product-info">
                            <h3 class="mobile-product-name clickable">
                                <p>RT# : {{ item.rtcounter }}</p>
                                <p>{{ item.ProductTitle }}</p>
                            </h3>
                        </div>
                    </div>

                    <div class="mobile-card-details">
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Location:</span>
                            <span class="mobile-detal-value"> {{ item.warehouselocation }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Added date:</span>
                            <span class="mobile-detal-value"> {{ item.datedelivered }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Updated date:</span>
                            <span class="mobile-detal-value"> {{ item.lastDateUpdate }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">FNSKU:</span>
                            <span class="mobile-detal-value"> {{ item.FNSKUviewer }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">MSKU:</span>
                            <span class="mobile-detal-value"> {{ item.MSKUviewer }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">ASIN:</span>
                            <span class="mobile-detal-value"> {{ item.ASINviewer }}</span>
                        </div>
                        <!-- Insert Hidden Here -->
                        <div class="mobile-detail-row" v-if="showDetails">
                            <span class="mobile-detail-label">FBM:</span>
                            <span class="mobile-detal-value"> {{ item.FBMAvailable }}</span>
                        </div>
                        <div class="mobile-detail-row" v-if="showDetails">
                            <span class="mobile-detail-label">FBA:</span>
                            <span class="mobile-detal-value"> {{ item.FbaAvailable }}</span>
                        </div>
                        <div class="mobile-detail-row" v-if="showDetails">
                            <span class="mobile-detail-label">Outbound:</span>
                            <span class="mobile-detal-value"> {{ item.Outbound }}</span>
                        </div>
                        <div class="mobile-detail-row" v-if="showDetails">
                            <span class="mobile-detail-label">Inbound:</span>
                            <span class="mobile-detal-value"> {{ item.Inbound }}</span>
                        </div>
                        <div class="mobile-detail-row" v-if="showDetails">
                            <span class="mobile-detail-label">Unfulfillable:</span>
                            <span class="mobile-detal-value"> {{ item.Unfulfillable }}</span>
                        </div>
                        <div class="mobile-detail-row" v-if="showDetails">
                            <span class="mobile-detail-label">Reserved:</span>
                            <span class="mobile-detal-value"> {{ item.Reserved }}</span>
                        </div>
                        <!--  -->
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Fullfilment:</span>
                            <span class="mobile-detal-value"> {{ item.Fulfilledby }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Status:</span>
                            <span class="mobile-detal-value"> {{ item.status }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Serial Number:</span>
                            <span class="mobile-detal-value"> {{ item.serialnumber }}</span>
                        </div>
                    </div>

                    <div class="mobile-card-actions">
                        <button class="mobile-btn mobile-btn-details" @click="toggleDetails(index)">
                            {{ expandedRows[index] ? 'Less Details' : 'More Details' }}
                        </button>
                        <button class="mobile-btn">
                            Example
                        </button>
                        <button class="mobile-btn">
                            Example
                        </button>
                        <button class="mobile-btn">
                            Example
                        </button>
                    </div>

                    <div v-if="expandedRows[index]" class="mobile-expanded-content">
                        <p><strong>Expanded Rows Here</strong></p>
                        <p><strong>Product Name:</strong> {{ item.AStitle }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom pagination (also centered) -->
        <div class="pagination-container">
            <div class="pagination-wrapper">
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
    </div>
</template>
