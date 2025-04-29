<template>
    <div class="vue-container">
        <h1 class="vue-title">Received Module</h1>

        <!-- Scanner Component -->
        <scanner-component scanner-title="Received Scanner" storage-prefix="received" :enable-camera="true"
            :display-fields="['Trackingnumber', 'FirstSN', 'SecondSN', 'PCN', 'Basket']"
            :api-endpoint="'/api/received/process-scan'" @process-scan="handleScanProcess"
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

        <!-- Table Container -->
        <div class="table-container">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" @click="toggleAll" v-model="selectAll" />
                            <a style="color:black" @click="sortBy('AStitle')" class="sortable">
                                Product Name
                                <span v-if="sortColumn === 'AStitle'">
                                    {{ sortOrder === 'asc' ? '▲' : '▼' }}
                                </span>
                            </a>
                            <span style="margin-right: 20px;"></span>
                            <a style="color:black" @click="sortBy('rtcounter')" class="sortable">
                                RT counter
                                <span v-if="sortColumn === 'rtcounter'">
                                    {{ sortOrder === 'asc' ? '▲' : '▼' }}
                                </span> </a>

                            <span style="margin-right: 20px;"></span>

                            <button class="Desktop" style="border: solid 1px black; background-color: aliceblue;"
                                @click="toggleDetailsVisibility">{{ showDetails ? 'Hide extra columns' : 'Show extra columns' }}</button>
                        </th>
                        <th class="Desktop">Location</th>
                        <th class="Desktop">Added date</th>
                        <th class="Desktop">Updated date</th>
                        <th class="Desktop">Fnsku</th>
                        <th class="Desktop">Msku</th>
                        <th class="Desktop">Asin</th>
                        <th class="Desktop" style="background-color: antiquewhite;" v-if="showDetails">FBM</th>
                        <th class="Desktop" style="background-color: antiquewhite;" v-if="showDetails">FBA</th>
                        <th class="Desktop" style="background-color: antiquewhite;" v-if="showDetails">Outbound</th>
                        <th class="Desktop" style="background-color: antiquewhite;" v-if="showDetails">Inbound</th>
                        <th class="Desktop" style="background-color: antiquewhite;" v-if="showDetails">Unfulfillable
                        </th>
                        <th class="Desktop" style="background-color: antiquewhite;" v-if="showDetails">Reserved</th>
                        <th class="Desktop">Fulfillment</th>
                        <th class="Desktop">Status</th>
                        <th class="Desktop">Serialnumber</th>
                        <th class="Desktop">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(item, index) in sortedInventory" :key="item.id">
                        <tr>
                            <td class="vue-details">
                                <div class="checkbox-container">
                                    <input type="checkbox" v-model="item.checked" />
                                    <span class="placeholder-date">{{ item.shipBy || '' }}</span>
                                </div>
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
                                        <p class="product-name">RT# : {{ item.rtcounter }}</p>
                                        <p class="product-name">{{ item.ProductTitle }}</p>

                                        <p class="Mobile">Location : {{ item.warehouselocation }}</p>
                                        <p class="Mobile">Added date : {{ item.datedelivered }}</p>
                                        <p class="Mobile">Updated date : {{ item.lastDateUpdate }}</p>
                                        <p class="Mobile">Fnsku : {{ item.FNSKUviewer }}</p>
                                        <p class="Mobile">Msku : {{ item.MSKUviewer }}</p>
                                        <p class="Mobile">Asin : {{ item.ASINviewer }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="Desktop">
                                <span><strong></strong> {{ item.warehouselocation }}</span>
                            </td>

                            <td class="Desktop">
                                <span><strong></strong> {{ item.datedelivered }}</span>
                            </td>

                            <td class="Desktop">
                                <span><strong></strong> {{ item.lastDateUpdate }}</span>
                            </td>

                            <td class="Desktop">
                                <span><strong></strong> {{ item.FNSKUviewer }}</span>
                            </td>

                            <td class="Desktop">
                                <span><strong></strong> {{ item.MSKUviewer }}</span>
                            </td>

                            <td class="Desktop">
                                <span><strong></strong> {{ item.ASINviewer }}</span>
                            </td>

                            <!-- Hidden --> <!-- Hidden --> <!-- Hidden -->
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
                            <!-- Hidden --> <!-- Hidden --> <!-- Hidden -->

                            <td class="Desktop">
                                <span><strong></strong> {{ item.Fulfilledby }}</span>
                            </td>

                            <td class="Desktop">
                                <span><strong></strong> {{ item.Status }}</span>
                            </td>

                            <td class="Desktop">
                                <span><strong></strong> {{ item.serialnumber }}</span>
                            </td>

                            <!-- Button for more details -->
                            <td class="Desktop">
                                {{ item.totalquantity }}
                                <button class="btn-moredetails" @click="toggleDetails(index)">
                                    {{ expandedRows[index] ? 'Less Details' : 'More Details' }}
                                </button>
                                <br>
                                <button class="btn-moredetails">example</button><br>
                                <button class="btn-moredetails">example</button><br>
                                <button class="btn-moredetails">example</button><br>
                            </td>
                        </tr>
                        <!-- More details results -->
                        <tr v-if="expandedRows[index]">
                            <td colspan="11">
                                <div class="expanded-content p-3 border rounded">
                                    <div class="Mobile">
                                        <button class="btn-moredetails">sample button</button>
                                        <button class="btn-moredetails">sample button</button>
                                        <button class="btn-moredetails">sample button</button>
                                    </div>
                                    <strong>Product Name:</strong> {{ item.AStitle }}
                                </div>
                            </td>
                        </tr>

                        <!-- Button for more details (Mobile) -->
                        <td class="Mobile">
                            {{ item.totalquantity }}
                            <button style="width: 100%; border-bottom: 2px solid black; padding:0px"
                                @click="toggleDetails(index)">
                                {{ expandedRows[index] ? 'Less Details ▲ ' : 'More Details ▼ ' }}
                            </button>
                        </td>
                    </template>
                </tbody>
            </table>
            <!-- Pagination -->
            <div class="pagination">
                <button @click="prevPage" :disabled="currentPage === 1" class="pagination-button">Previous</button>
                <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
                <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button">Next</button>
            </div>
        </div>
    </div>
</template>

