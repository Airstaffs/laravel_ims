<template>
    <div class="vue-container">
        <h1 class="vue-title">Unreceived Module</h1>

        <!-- Scanner Component Here -->
        <scanner-component scanner-title="Unreceived Scanner" storage-prefix="unreceived" :enable-camera="true"
            :display-fields="['Trackingnumber', 'RPN', 'PRD']" :api-endpoint="'/api/unreceived/process-scan'"
            @process-scan="handleScanProcess" @hardware-scan="handleHardwareScan" @scanner-opened="handleScannerOpened"
            @scanner-closed="handleScannerClosed" @scanner-reset="handleScannerReset" @mode-changed="handleModeChange"
            ref="scanner">
            <!-- Define custom input fields for Unreceived module -->
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

                <!-- Step 2: RPN Field (shown after tracking verification) -->
                <div class="input-group" v-if="currentStep === 2">
                    <div class="tracking-verified">
                        <div class="success-banner">Tracking found for {{ trackingNumber }}</div>
                    </div>
                    <label>RPN:</label>
                    <input type="text" v-model="rpnNumber" placeholder="RPN Number" readonly class="readonly-input" />
                    <button @click="goToNextStep" class="next-button">Next</button>
                </div>

                <!-- Step 3: PRD Field (shown after RPN) -->
                <div class="input-group" v-if="currentStep === 3">
                    <label>PRD:</label>
                    <div class="date-input-container">
                        <input type="date" v-model="prdDate" @change="handlePrdDateChange" :min="todayDate"
                            class="date-input" />
                        <button class="calendar-icon">
                            <i class="fas fa-calendar"></i>
                        </button>
                    </div>
                    <button @click="handleTodayButtonClick" class="today-button">Today</button>
                    <!-- Only show Submit button in Manual mode -->
                    <button v-if="showManualInput" @click="submitScan" class="submit-scan-button">Submit</button>
                </div>
            </template>
        </scanner-component>

        <!-- table display -->
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

<script>
    import Unreceived from "./unreceived.js";
    export default Unreceived;
</script>
