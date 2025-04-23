<template>
    <div class="container">
        <h1>📦 FBA Inbound Shipment</h1>
        <!-- Only show toggle if NOT in View 2 -->
        <div v-if="!selectedShipment">
            <button @click="toggleView" class="toggle-btn">
                <span v-if="showCartMode">📦 View Shipments</span>
                <span v-else>🛒 View Cart</span>
            </button>
        </div>



        <!-- Show Cart View -->
        <div v-if="showCartMode">
            <h2>🛒 Draft Cart</h2>
            <button @click="openAddItemModal()">➕ Add an Item to Cart</button>
            <table class="shipment-table">
                <thead>
                    <tr>
                        <th>Product ID</th>
                        <th>Title</th>
                        <th>ASIN</th>
                        <th>FNSKU</th>
                        <th>MSKU</th>
                        <th>Serial #</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in cartItems" :key="item.ProdID">
                        <td>{{ item.ProdID }}</td>
                        <td>{{ item.ProductTitle }}</td>
                        <td>{{ item.ASINviewer }}</td>
                        <td>{{ item.FNSKUviewer }}</td>
                        <td>{{ item.MSKUviewer }}</td>
                        <td>{{ item.serialnumber }}</td>
                        <td><button @click="removeCartItem(item.ProdID)">🗑️ Remove</button></td>
                    </tr>
                </tbody>
            </table>
            <button>🔍 Item Check</button>
            <button @click="openStoreSelectModal">✅ Commit Cart</button>
        </div>

        <!-- View 1: List of Existing Shipments -->
        <div v-if="!selectedShipment && !showCartMode">
            <h2>Select a Shipment</h2>

            <div v-for="shipment in shipments" :key="shipment.shipmentID" class="shipment-block">
                <div class="shipment-header">
                    <strong>{{ shipment.shipmentID }}</strong> - {{ shipment.store }} ({{ shipment.item_count }} items)
                    <button @click="toggleVisibility(shipment.shipmentID)">
                        {{ visibleShipments[shipment.shipmentID] ? 'Hide Items' : 'Show Items' }}
                    </button>
                    <button @click="selectShipment(shipment)">
                        ➡️ Ship to Amazon
                    </button>
                </div>

                <div v-show="visibleShipments[shipment.shipmentID]">
                    <table class="shipment-table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Details</th>
                                <th>Qty</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in shipment.items" :key="item.FNSKU">
                                <td><img :src="'https://via.placeholder.com/50'" width="50" /></td>
                                <td>
                                    <div><strong>Title:</strong> {{ item.ProductName }}</div>
                                    <div><strong>ASIN:</strong> {{ item.ASIN }}</div>
                                    <div><strong>MSKU:</strong> {{ item.MSKU }}</div>
                                    <div><strong>FNSKU:</strong> {{ item.FNSKU }}</div>
                                    <div><strong>Serial#:</strong> {{ item.Serialnumber }}</div>
                                </td>
                                <td>1</td>
                                <td>
                                    <button @click="deleteItem(item.ID)">🗑️ Delete</button>
                                </td>

                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" style="text-align: right;">
                                    <button @click="openAddItemModal(shipment.shipmentID)">➕ Add Item</button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>


        <!-- View 2: Create Inbound Plan (Step 1) -->
        <div v-if="selectedShipment && !showCartMode">
            <button class="back-btn" @click="selectedShipment = null">🔙 Back to Shipments</button>
            <h2>Step 1: Create/Manage/Cancel Inbound Shipments</h2>
            <form @submit.prevent="createShipment" class="shipment-form">
                <div class="form-group">
                    <label>Store:</label>
                    <input v-model="form.store" />
                </div>

                <div class="form-group">
                    <label>Destination Marketplace:</label>
                    <input v-model="form.destinationMarketplace" />
                </div>

                <div class="form-group">
                    <label>Shipment ID:</label>
                    <input v-model="form.shipmentID" disabled />
                </div>

                <button type="submit">🚀 Create Inbound Plan</button>
            </form>

            <!-- API Response -->
            <div v-if="response">
                <br>
                <p>Created Inboundplanid successfully.</p>
            </div>

            <!-- Step 2A: Generate Packing Options -->
            <hr>
            <h2>Step 2: Item Check & Verify Package Details</h2>


            <div v-if="packingResponse">
                <!-- <h3>Packing Response:</h3> -->
                <p>{{ packingResponse.message }}</p>
                <!-- <p>Sheesh</p>
                 <pre>{{ packingResponse }}</pre> -->

            </div>

            <div v-if="listpackingResponse">
                <!-- <h3>List Packing Response:</h3> -->
                <p>{{ listpackingResponse.message }}</p>
                <!-- <pre>{{ listpackingResponse }}</pre> -->

            </div>

            <div v-if="listitemspackingResponse">
                <!-- <h3>List Items Packing Response:</h3> -->
                <p>{{ listitemspackingResponse.message }}</p>
                <!-- <p>Sheesh</p>
                 <pre>{{ listitemspackingResponse }}</pre> -->

            </div>

            <div v-if="confirmPackingResponse">
                <!-- <h3>Confirm Packing Response:</h3> -->
                <p>{{ confirmPackingResponse.message }}</p>
                <!-- <p>Sheesh</p>
                 <pre>{{ confirmpackingResponse }}</pre> -->
            </div>

            <div v-if="step3PackingResponse">
                <p>{{ step3PackingResponse.message }}</p>
            </div>

            <div v-if="placementOptionResponse">
                <p>{{ placementOptionResponse.message }}</p>
            </div>



            <div v-if="Donefetchingandconstructedthetableinput">
                <table>
                    <thead>
                        <tr>
                            <th>MSKU</th>
                            <th>Quantity</th>
                            <th>FNSKU</th>
                            <th>ASIN</th>
                            <th>Select Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(item, index) in combinedPackingItems" :key="index">
                            <!-- Main Item Row -->
                            <tr>
                                <td>{{ item.msku }}</td>
                                <td>{{ item.quantity }}</td>
                                <td>{{ item.fnsku }}</td>
                                <td>{{ item.asin }}</td>
                                <td>
                                    <select v-model="item.selectedBoxType" @change="onBoxTypeChange(item)">
                                        <option value="retail_box">Retail Box</option>
                                        <option value="white_box">White Box</option>
                                    </select>
                                </td>
                            </tr>

                            <!-- Dimension Row -->
                            <tr>
                                <td colspan="5" style="font-weight: bold; font-size: 0.9em;">
                                    <template v-if="item.selectedBoxType === 'retail_box'">
                                        Retail Box:
                                        {{ item.dimensionInfo.retail_box.retail_length || 'N/A' }} x
                                        {{ item.dimensionInfo.retail_box.retail_width || 'N/A' }} x
                                        {{ item.dimensionInfo.retail_box.retail_height || 'N/A' }} inches —
                                        {{ item.dimensionInfo.retail_box.retail_lbs || 'N/A' }} lbs
                                    </template>

                                    <template v-else-if="item.selectedBoxType === 'white_box'">
                                        White Box:
                                        {{ item.dimensionInfo.white_box.white_length || 'N/A' }} x
                                        {{ item.dimensionInfo.white_box.white_width || 'N/A' }} x
                                        {{ item.dimensionInfo.white_box.white_height || 'N/A' }} inches —
                                        {{ item.dimensionInfo.white_box.white_lbs || 'N/A' }} lbs
                                    </template>
                                </td>
                            </tr>

                        </template>
                    </tbody>
                </table>

                <!-- 📦 Global Package Dimensions Input -->
                <div class="form-group" style="margin-top: 24px;">

                    <table class="shipment-table" style="width: auto; text-align: left;">
                        <thead>
                            <tr>
                                <td colspan="4">
                                    <h3>📦 Package Dimensions for Entire Shipment</h3>
                                </td>
                            </tr>
                            <tr>
                                <th>Length (IN)</th>
                                <th>Width (IN)</th>
                                <th>Height (IN)</th>
                                <th>Weight (LB)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input type="number" v-model="form.packageLength" step="0.01" min="0"
                                        placeholder="e.g. 24.5" />
                                </td>
                                <td><input type="number" v-model="form.packageWidth" step="0.01" min="0"
                                        placeholder="e.g. 12.25" />
                                </td>
                                <td><input type="number" v-model="form.packageHeight" step="0.01" min="0"
                                        placeholder="e.g. 18.75" />
                                </td>
                                <td><input type="number" v-model="form.packageWeight" step="0.01" min="0"
                                        placeholder="e.g. 48.6" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Submit -->
                <button @click="proceedToStep3PackingInfo" class="btn btn-primary" style="margin-top: 16px;">
                    Proceed to Step 3
                </button>
            </div>

            <div v-if="listPlacementOptionsResponse">
                <h2>📦 Placement Options</h2>
                <table class="placement-table">
                    <thead>
                        <tr>
                            <th>Placement Option ID</th>
                            <th>Shipment ID</th>
                            <th>Description</th>
                            <th>Destination Address</th>
                            <th>Fee (USD)</th>
                            <th>Warehouse ID</th>
                            <th>Destination Type</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(option, index) in enrichedPlacementOptions" :key="index">
                            <td>{{ option.placementOptionId }}</td>
                            <td>{{ option.shipmentId }}</td>
                            <td>{{ option.description }}</td>
                            <td>{{ option.destinationAddress }}</td>
                            <td>{{ option.fee }}</td>
                            <td>{{ option.warehouseId }}</td>
                            <td>{{ option.destinationType }}</td>
                            <td>{{ option.status }}</td>
                            <td>
                                <button :class="{
                                    'selected-btn': selectedPlacementOptionId === option.placementOptionId
                                }" @click="selectPlacement(option)">
                                    {{ selectedPlacementOptionId === option.placementOptionId ? '✅ Selected' : 'Select'
                                    }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Show shipDate and declaredValue if selection made -->
                <div v-if="form.placementOptionId && form.shipmentidfromapi" class="shipment-extra-fields">
                    <h3 style="margin-top: 16px;">📝 Shipment Details</h3>

                    <label>Ship Date:</label>
                    <input type="datetime-local" v-model="form.shipDate" />

                    <label style="margin-left: 16px;">Total Declared Value (USD):</label>
                    <input type="number" step="0.01" min="0" v-model="form.totalDeclaredValue"
                        placeholder="e.g. 250.00" />

                    <button class="btn btn-primary" style="margin-left: 16px;" @click="submitTransportationOptions">
                        🚚 Submit Transportation Options
                    </button>
                </div>
            </div>

            <div v-if="deliveryOptionsResponse">
                <p>{{ deliveryOptionsResponse.message }}</p>
            </div>

            <div v-if="generateDeliveryOptionsResponse">
                <p>{{ generateDeliveryOptionsResponse.message }}</p>

                <table v-if="generateDeliveryOptionsResponse.data?.transportationOptions?.length">
                    <thead>
                        <tr>
                            <th>AlphaCode</th>
                            <th>Carrier</th>
                            <th>Shipping Mode</th>
                            <th>Solution</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(option, i) in generateDeliveryOptionsResponse.data.transportationOptions" :key="i">
                            <td>{{ option.carrier.alphaCode }}</td>
                            <td>{{ option.carrier.name }}</td>
                            <td>{{ option.shippingMode }}</td>
                            <td>{{ option.shippingSolution }}</td>
                            <td>
                                <button @click="selectTransportationOption(option)">
                                    🚚 Choose
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

            </div>

            <div v-if="generateDeliveryOptionsResponse?.data?.transportationOptions?.length">
                <button @click="showPreviousDeliveryOptionsPage" :disabled="!canGoBack" class="btn btn-secondary">
                    ⬅️ Previous Page
                </button>

                <button @click="showNextDeliveryOptionsPage" :disabled="!canGoForward" class="btn btn-primary"
                    style="margin-left: 8px;">
                    Next Page ➡️
                </button>

                <p style="margin-top: 8px;">
                    Page {{ deliveryOptionsPages.length }}
                </p>
            </div>

            <div v-if="deliveryWindowOptionsResponse?.data?.deliveryWindowOptions?.length">
                <h3>📆 Choose Delivery Window</h3>
                <table class="delivery-window-table">
                    <thead>
                        <tr>
                            <th>Availability</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Valid Until</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(option, index) in deliveryWindowOptionsResponse.data.deliveryWindowOptions"
                            :key="index">
                            <td>{{ option.availabilityType }}</td>
                            <td>{{ formatDate(option.startDate) }}</td>
                            <td>{{ formatDate(option.endDate) }}</td>
                            <td>{{ formatDate(option.validUntil) }}</td>
                            <td>
                                <button @click="selectDeliveryWindow(option)">
                                    {{ form.deliveryWindowOptionId === option.deliveryWindowOptionId ? '✅ Selected' :
                                        'Select' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="confirmPlacementOptionResponse?.message">
                <p>{{ confirmPlacementOptionResponse.message }}</p>
            </div>

            <div v-if="confirmDeliveryWindowResponse?.message">
                <p>{{ confirmDeliveryWindowResponse.message }}</p>
            </div>

            <div v-if="confirmTransportationOptionResponse?.message">
                <p>{{ confirmTransportationOptionResponse.message }}</p>
            </div>


            <hr>
            <h2>Step 3: Destination & Transportation</h2>
            <hr>
            <h2>Step 4: Print Label</h2>
            <hr>
            <h2>Step 5: Verification</h2>
        </div>


    </div>

    <!-- Add Item Modal -->
    <div v-if="showAddItemModal" class="modal-overlay">
        <div class="modal-content">
            <h2>Add Item to Shipment: {{ selectedShipmentID }}</h2>

            <input v-model="productSearch" @input="fetchProducts" placeholder="Search products..." />

            <label>Per Page:</label>
            <select v-model="productPerPage" @change="fetchProducts">
                <option>20</option>
                <option>50</option>
                <option>100</option>
            </select>

            <table>
                <thead>
                    <tr>
                        <th>ProductID</th>
                        <th>Title</th>
                        <th>FNSKU</th>
                        <th>MSKU</th>
                        <th>ASIN</th>
                        <th>Serial #</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="product in productList" :key="product.ProductID">
                        <td>{{ product.ProductID }}</td>
                        <td>{{ product.ProductTitle }}</td>
                        <td>{{ product.FNSKUviewer }}</td>
                        <td>{{ product.MSKUviewer }}</td>
                        <td>{{ product.ASINviewer }}</td>
                        <td>{{ product.serialnumber }}</td>
                        <button @click="handleAddItem(product)">➕ Add</button>
                    </tr>
                </tbody>
            </table>

            <div class="modal-footer">
                <button :disabled="productPage <= 1" @click="productPage--; fetchProducts()">⬅ Prev</button>
                <button :disabled="productPage >= productPagination.last_page"
                    @click="productPage++; fetchProducts()">Next
                    ➡</button>
                <button @click="showAddItemModal = false">Close</button>
            </div>
        </div>
    </div>

    <!-- Store Selection Modal -->
    <div v-if="showStoreModal" class="modal-overlay">
        <div class="modal-content">
            <h3>Select Store before creating Shipment</h3>
            <select v-model="selectedStore">
                <option disabled value="">-- Choose a Store --</option>
                <option v-for="store in stores" :key="store.store_id" :value="store.storename">
                    {{ store.storename }}
                </option>
            </select>
            <br />
            <button @click="commitCart">🚀 Confirm Shipment Cart</button>
            <button @click="showStoreModal = false">Cancel</button>
        </div>
    </div>
</template>

<script>
import shipmentService from '@/components/Stockroom/backend/fba_inbound_shipment_backend.js';
const API_BASE_URL = import.meta.env.VITE_API_URL;
export default {
    data() {
        return {
            showCartMode: false,
            cartItems: [], // This will hold the cart item list
            shipments: [],
            selectedShipment: null,
            visibleShipments: {},
            form: {
                store: '',
                destinationMarketplace: '',
                shipmentID: '',
                inboundplanid: '',
                packingGroupId: '',
                packingOptionId: '',
                packageWeight: '',
                packageLength: '',
                packageWidth: '',
                packageHeight: '',
                placementOptionId: '',
                shipmentidfromapi: '',
                shipmentId: '',
                shipDate: new Date().toISOString().slice(0, 16), // datetime-local default
                totalDeclaredValue: '',
                transportationOptionId: '',
                deliveryWindowOptionId: '',
            },
            stores: [],
            showStoreModal: false,
            selectedStore: '',
            response: null,
            packingResponse: null,
            listpackingResponse: null,
            listitemspackingResponse: null,
            confirmPackingResponse: null,
            placementOptionResponse: null,
            showAddItemModal: false,
            productList: [],
            productSearch: '',
            productPerPage: 20,
            productPage: 1,
            productPagination: {},
            Donefetchingandconstructedthetableinput: false,
            step3PackingResponse: null,
            sheeshables: false,
            listPlacementOptionsResponse: null,
            enrichedPlacementOptions: [],
            selectedPlacementOptionId: '',
            transportationOptionsResponse: null,
            deliveryOptionsResponse: null,
            generateDeliveryOptionsResponse: null,
            nextToken: null,
            deliveryWindowOptionsResponse: null,
            confirmPlacementOptionResponse: null,
            confirmDeliveryWindowResponse: null,
            confirmTransportationOptionResponse: null,
        };
    },
    created() {
        this.fetchShipments();
    },
    methods: {
        addItem(shipmentID) {
            console.log('Add item to', shipmentID);
            // Modal or form logic here
        },
        toggleVisibility(shipmentID) {
            this.visibleShipments[shipmentID] = !this.visibleShipments[shipmentID];
        },
        async openAddItemModal(shipmentID = null) {
            this.selectedShipmentID = shipmentID;
            this.showAddItemModal = true;
            this.productSearch = '';
            this.productPage = 1;

            this.fetchProducts();
        },
        async fetchProducts() {
            try {
                const res = await axios.get(`${API_BASE_URL}/products`, {
                    params: {
                        search: this.productSearch,
                        location: 'stockroom',
                        page: this.productPage,
                        per_page: this.productPerPage
                    }
                });

                this.productList = res.data.data;
                this.productPagination = {
                    total: res.data.total,
                    current_page: res.data.current_page,
                    last_page: res.data.last_page
                };
            } catch (error) {
                console.error("Error fetching products:", error);
            }
        },
        async handleAddItem(product) {
            try {
                if (this.showCartMode) {
                    await axios.post(`${API_BASE_URL}/amzn/fba-cart/add`, {
                        ProdID: product.ProductID,
                        processby: this.currentUser
                    });
                    alert('Item added to cart!');
                    this.fetchCartItems();
                } else {
                    await shipmentService.addItemToShipment(this.selectedShipmentID, product);
                    alert('Item added to shipment!');
                    this.fetchShipments();
                }

                this.showAddItemModal = false;

            } catch (error) {
                console.error("Add item error:", error);
                alert('❌ Error adding item.');
            }
        },
        async addProductToShipment(product) {
            try {
                const res = await shipmentService.addItemToShipment(this.selectedShipmentID, product);
                if (res.success) {
                    alert("Item added successfully!");
                    this.showAddItemModal = false;
                    this.fetchShipments(); // refresh the shipment list to reflect the new item
                }
            } catch (error) {
                console.error("Error adding item:", error);
                alert("Failed to add item.");
            }
        },
        toggleView() {
            this.showCartMode = !this.showCartMode;
            if (this.showCartMode) {
                this.fetchCartItems(); // Fetch cart items when toggled to cart mode
            } else {
                this.fetchShipments(); // Re-fetch shipments if needed
            }
        },
        async fetchCartItems() {
            try {
                const res = await axios.get(`${API_BASE_URL}/amzn/fba-cart/list`, {
                    params: { processby: this.currentUser } // replace with actual user variable
                });
                this.cartItems = res.data;
            } catch (error) {
                console.error("Error fetching cart items:", error);
            }
        },
        async deleteItem(itemID) {
            if (!itemID) return;

            if (!confirm(`Are you sure you want to delete this item (ID: ${itemID})?`)) return;

            try {
                const res = await shipmentService.deleteShipmentItem({ ID: itemID });
                alert('🗑️ Item deleted.');
                this.fetchShipments();
            } catch (error) {
                console.error("Failed to delete item:", error);
                alert('❌ Could not delete item.');
            }
        },
        async deleteShipmentItem(payload) {
            const res = await axios.delete(`${API_BASE_URL}/amzn/fba-shipment/delete-item`, {
                data: payload
            });
            return res.data;
        },
        async addToCart(prodID) {
            try {
                const res = await axios.post(`${API_BASE_URL}/amzn/fba-cart/add`, {
                    ProdID: prodID,
                    processby: 'Jundell'     // ✅ can be static for now
                });
                alert('Item added to cart ✅');
                this.fetchCartItems(); // refresh cart
            } catch (error) {
                if (error.response && error.response.status === 409) {
                    alert('⚠️ Item already in cart');
                } else {
                    console.error("Error adding to cart:", error);
                    alert('❌ Failed to add item.');
                }
            }
        },
        async removeCartItem(prodID) {
            try {
                await axios.delete(`${API_BASE_URL}/amzn/fba-cart/remove`, {
                    data: { ProdID: prodID }
                });
                alert('🗑️ Item removed from cart');
                this.fetchCartItems(); // refresh cart
            } catch (error) {
                console.error("Error removing cart item:", error);
                alert('❌ Failed to remove item');
            }
        },
        openStoreSelectModal() {
            this.selectedStore = '';
            this.showStoreModal = true;
            this.fetchStores();
        },
        async fetchStores() {
            try {
                const res = await axios.get(`${API_BASE_URL}/get-stores`);
                this.stores = res.data.stores;
            } catch (error) {
                console.error("Error fetching stores:", error);
                alert("⚠️ Failed to load stores.");
            }
        },
        async commitCart() {
            if (!this.selectedStore) {
                alert("Please select a store.");
                return;
            }

            try {
                const res = await axios.post(`${API_BASE_URL}/amzn/fba-cart/commit`, {
                    store: this.selectedStore
                });
                alert(`✅ Cart committed as Shipment: ${res.data.shipmentID}`);
                this.showStoreModal = false;
                this.fetchCartItems(); // Refresh cart after commit
                this.fetchShipments(); // Optional: refresh shipments view too
            } catch (error) {
                console.error("Error committing cart:", error);
                alert("❌ Failed to commit cart.");
            }
        },
        async fetchShipments() {
            try {
                const res = await shipmentService.getShipments();
                this.shipments = res;

                this.visibleShipments = {};
                res.forEach(shipment => {
                    this.visibleShipments[shipment.shipmentID] = false;
                });
            } catch (error) {
                console.error("Error fetching shipments:", error);
            }
        },
        selectShipment(shipment) {
            this.selectedShipment = shipment;
            console.log(shipment);
            this.form = {
                store: shipment.store || 'Renovar Tech',
                destinationMarketplace: 'ATVPDKIKX0DER',
                shipmentID: shipment.shipmentID,
                inboundplanid: '' // default empty
            };
        },
        async createShipment() {
            try {
                const res = await shipmentService.createShipment(this.form);
                if (!res.success || !res.data?.inboundPlanId) {
                    throw new Error("Failed to create shipment");
                }
                this.response = res;
                this.form.inboundplanid = res.data.inboundPlanId;

                // Delay 3 seconds before proceeding
                await new Promise(resolve => setTimeout(resolve, 3000));

                // Proceed to next step
                this.generatePacking();
            } catch (error) {
                console.error("Error creating shipment:", error);
                this.response = { error: error.message };
            }
        },


        async generatePacking() {
            try {
                if (!this.form.store || !this.form.destinationMarketplace || !this.form.shipmentID || !this.form.inboundplanid) {
                    console.error("Missing required fields:", this.form);
                    this.packingResponse = {
                        success: false,
                        message: "Error: Missing required fields.",
                        data: null
                    };
                    return;
                }

                console.log("Sending request with payload:", this.form);

                const response = await axios.get("/amzn/fba-shipment/step2/generate-packing", { params: this.form });

                if (response.data.success && response.data.operationStatus?.status === "SUCCESS") {
                    this.packingResponse = {
                        success: true,
                        message: "Packing generation started successfully.",
                        data: response.data
                    };
                    await this.listPackingOptions(); // Proceed to next step
                } else {
                    throw new Error("Packing request did not succeed.");
                }
            } catch (error) {
                console.error("Error generating packing:", error.response?.data || error.message);
                this.packingResponse = {
                    success: false,
                    message: "Error generating packing.",
                    data: error.response?.data || error.message
                };
            }
        },

        async listPackingOptions() {
            try {
                const res = await shipmentService.listPackingOptions(this.form);

                if (!res?.success || !Array.isArray(res?.data?.packingOptions)) {
                    throw new Error("Invalid response or no packing options available.");
                }

                const packingOption = res.data.packingOptions[0] || {};
                const packingGroupId = Array.isArray(packingOption.packingGroups) && packingOption.packingGroups.length > 0
                    ? packingOption.packingGroups[0]
                    : '';

                this.form.packingOptionId = packingOption.packingOptionId || '';
                this.form.packingGroupId = packingGroupId;

                this.listpackingResponse = {
                    success: true,
                    message: "Packing options listed successfully.",
                    data: res.data
                };

                await this.listItemsbyPackingOptions(); // Proceed to next step
            } catch (error) {
                console.error("Error listing packing options:", error);
                this.listpackingResponse = {
                    success: false,
                    message: "Error listing packing options.",
                    data: error.message
                };
            }
        },

        async listItemsbyPackingOptions() {
            try {
                const res = await shipmentService.listItemsbyPackingOptions(this.form);

                if (!res?.success) {
                    throw new Error("Failed to list items by packing options.");
                }

                this.listitemspackingResponse = {
                    success: true,
                    message: "Items listed successfully by packing options.",
                    data: res.data
                };

                await this.confirmPackingOptions(); // Proceed to next step
            } catch (error) {
                console.error("Error listing items by packing options:", error);
                this.listitemspackingResponse = {
                    success: false,
                    message: "Error listing items by packing options.",
                    data: error.message
                };
            }
        },

        async confirmPackingOptions() {
            try {
                const res = await shipmentService.confirmPackingOptions(this.form);

                if (!res?.success) {
                    throw new Error("Failed to confirm packing options.");
                }

                this.confirmPackingResponse = {
                    success: true,
                    message: "Packing options confirmed successfully.",
                    data: res.data
                };

                await this.fetchAndCombinePackageDimensions();

                console.log("✅ Process completed successfully!");
            } catch (error) {
                console.error("Error confirming packing options:", error);
                this.confirmPackingResponse = {
                    success: false,
                    message: "Error confirming packing options.",
                    data: error.message
                };
            }
        },
        async fetchAndCombinePackageDimensions() {
            try {
                const res = await axios.post("/amzn/fba-shipment/fetch_package_dimensions", {
                    store: this.form.store,
                    destinationMarketplace: this.form.destinationMarketplace,
                    shipmentID: this.form.shipmentID
                });

                if (!res.data.success || !Array.isArray(res.data.data)) {
                    throw new Error("Failed to fetch package dimensions");
                }

                const dimensionData = res.data.data;
                const items = this.listitemspackingResponse?.data?.items || [];

                this.combinedPackingItems = items.map((item) => {
                    const match = dimensionData.find((d) => d.asin === item.asin);

                    // Try to find previous selection
                    const existingItem = this.combinedPackingItems?.find(i => i.asin === item.asin);
                    const selectedBoxType = existingItem?.selectedBoxType || 'retail_box';

                    return {
                        ...item,
                        dimensionInfo: match || {
                            retail_box: {},
                            white_box: {},
                            asin: item.asin,
                            shipmentID: this.form.shipmentID
                        },
                        selectedBoxType
                    };
                });


                this.Donefetchingandconstructedthetableinput = true;
            } catch (error) {
                console.error("Error fetching and combining package dimensions:", error);
            }
        },
        async onBoxTypeChange(item) {
            try {
                const res = await axios.post("/amzn/fba-shipment/fetch_package_dimensions", {
                    store: this.form.store,
                    destinationMarketplace: this.form.destinationMarketplace,
                    shipmentID: this.form.shipmentID
                });

                const dimensionData = res.data.data;
                const updated = dimensionData.find(d => d.asin === item.asin);

                if (updated) {
                    // ✅ Force Vue to reassign the object to trigger reactivity
                    item.dimensionInfo = updated;
                    console.log(item);
                }
            } catch (error) {
                console.error("Failed to fetch new dimensions:", error);
            }
        },

        // Add this method to handle Step 3 submission
        async proceedToStep3PackingInfo() {
            const requiredFields = ['store', 'destinationMarketplace', 'shipmentID', 'inboundplanid', 'packingGroupId', 'packingOptionId'];
            const missing = requiredFields.filter(field => !this.form[field]);
            if (missing.length) {
                console.warn("Missing required form fields:", missing);
                return;
            }

            const payload = {
                items: this.combinedPackingItems.map(item => {
                    return {
                        msku: item.msku,
                        quantity: item.quantity,
                        fnsku: item.fnsku,
                        asin: item.asin,
                        ...item.dimensionInfo[item.selectedBoxType],
                        box_type: item.selectedBoxType
                    };
                }),
                package: {
                    weight: this.packageWeight,
                    dimensions: this.packageDimensions
                }
            };

            try {
                const response = await axios.get('/amzn/fba-shipment/step3/packing_information', {
                    params: {
                        data: JSON.stringify(payload),
                        ...this.form
                    }
                });

                this.step3PackingResponse = {
                    success: true,
                    message: "Packing info submitted successfully!",
                    data: response.data
                };

                this.Donefetchingandconstructedthetableinput = false;

                await this.step4PlacementOption();
            } catch (error) {
                console.error('Error sending to Step 3:', error);
                this.step3PackingResponse = {
                    success: false,
                    message: "❌ Failed to submit packing info.",
                    data: error.message
                };
            }
        },
        formatBoxDimensions(box) {
            if (!box) return 'N/A';
            const length = box.retail_length || box.white_length;
            const width = box.retail_width || box.white_width;
            const height = box.retail_height || box.white_height;
            const weight = box.lbs || box.white_lbs;

            if (!length || !width || !height) return 'N/A dimensions';

            return `${length} x ${width} x ${height} inches — ${weight ?? 'N/A'} lbs`;
        },
        async step4PlacementOption() {
            try {
                const res = await axios.get(`${API_BASE_URL}/amzn/fba-shipment/step4/placement_option`, {
                    params: {
                        ...this.form
                    }
                });

                this.placementOptionResponse = {
                    success: res.data.success,
                    message: res.data.message,
                    data: res.data.data
                };

                console.log("✅ Step 4 (Placement Option) completed!");
                await this.fetchPlacementOptions();
                console.log(this.listPlacementOptionsResponse);
                console.log(this.enrichedPlacementOptions);
            } catch (error) {
                console.error("❌ Error in Step 4:", error);
                this.placementOptionResponse = {
                    success: false,
                    message: "Failed to confirm placement option.",
                    data: error.message
                };
            }
        },
        async fetchPlacementOptions() {
            try {
                const res = await axios.get('/amzn/fba-shipment/step4/list_placement_option', {
                    params: { ...this.form }
                });
                if (res.data.success) {
                    this.listPlacementOptionsResponse = res.data.data;
                    await this.enrichPlacementOptions();
                }
            } catch (error) {
                console.error("Error fetching placement options:", error);
            }
        },

        async enrichPlacementOptions() {
            const options = this.listPlacementOptionsResponse.placementOptions;
            const enriched = [];

            console.log("PlacementOptions", this.listPlacementOptionsResponse);

            for (const option of options) {
                const shipmentIdFromAPI = option.shipmentIds[0]; // clearer name
                try {
                    const shipmentRes = await axios.get('/amzn/fba-shipment/step4/get_shipment', {
                        params: {
                            ...this.form,
                            shipmentidfromapi: shipmentIdFromAPI
                        }
                    });

                    const shipmentData = shipmentRes.data.data;
                    const address = shipmentData.destination?.address || {};
                    const fullAddress = `${address.name || '-'}, ${address.addressLine1 || '-'}, ${address.city || '-'}, ${address.stateOrProvinceCode || '-'} ${address.postalCode || '-'}, ${address.countryCode || '-'}`;

                    enriched.push({
                        placementOptionId: option.placementOptionId,
                        shipmentId: shipmentIdFromAPI,
                        description: option.fees[0]?.description || '-',
                        fee: option.fees[0]?.value.amount || '0.00',
                        warehouseId: shipmentData.destination?.warehouseId || '-',
                        destinationType: shipmentData.destination?.destinationType || '-',
                        destinationAddress: fullAddress,
                        status: shipmentData.status || '-'
                    });
                } catch (e) {
                    console.warn(`❌ Failed to enrich shipment ${shipmentIdFromAPI}:`, e);
                }
            }

            this.enrichedPlacementOptions = enriched;
        },

        selectShipmentOption(option) {
            this.form.placementOptionId = option.placementOptionId;
            this.form.shipmentidfromapi = option.shipmentId;
            this.form.shipDate = new Date().toISOString().slice(0, 16); // reset to now
        },

        selectPlacement(option) {
            this.selectedPlacementOptionId = option.placementOptionId;
            this.form.placementOptionId = option.placementOptionId;
            this.form.shipmentidfromapi = option.shipmentId;
            this.form.shipDate = new Date().toISOString().slice(0, 16); // defaults to now
        },

        async submitTransportationOptions() {
            try {
                const response = await axios.get(`${API_BASE_URL}/amzn/fba-shipment/step5/transportation_options`, {
                    params: { ...this.form }
                });

                if (response.data.success) {
                    this.transportationOptionsResponse = {
                        success: true,
                        message: "✅ Transportation options submitted successfully!"
                    };

                    await this.generateDeliveryOptions();
                } else {
                    this.transportationOptionsResponse = {
                        success: false,
                        message: "❌ Failed to submit transportation options."
                    };
                }
            } catch (error) {
                console.error("Error submitting transport options:", error);
                this.transportationOptionsResponse = {
                    success: false,
                    message: "❌ Something went wrong."
                };
            }
        },

        async generateDeliveryOptions() {
            try {
                const res = await axios.get(`${API_BASE_URL}/amzn/fba-shipment/step5/generate_delivery_options`, {
                    params: { ...this.form }
                });

                if (res.data.success) {
                    res.data.message = "✅ Delivery options generated successfully!";
                    this.deliveryOptionsResponse = res.data;
                    await this.transportation_options_view();
                } else {
                    res.data.message = "❌ Failed to generate delivery options.";
                    this.deliveryOptionsResponse = res.data;
                }
            } catch (error) {
                this.deliveryOptionsResponse = {
                    success: false,
                    message: "❌ Error occurred while generating delivery options.",
                    error: error.message
                };
                console.error("Error fetching delivery options:", error);
            }
        },

        async transportation_options_view(nextToken = null) {
            try {
                const params = {
                    ...this.form
                };
                if (nextToken) {
                    params.nextToken = nextToken;
                }

                const res = await axios.get(`${API_BASE_URL}/amzn/fba-shipment/step5/transportation_options_view`, {
                    params
                });

                if (res.data.success) {
                    res.data.message = "✅ Transportation options fetched successfully!";
                    this.generateDeliveryOptionsResponse = res.data;

                    // Track all pages
                    if (!this.deliveryOptionsPages) this.deliveryOptionsPages = [];
                    this.deliveryOptionsPages.push(res.data);
                } else {
                    res.data.message = "❌ Failed to fetch transportation options.";
                    this.generateDeliveryOptionsResponse = res.data;
                }
            } catch (error) {
                this.generateDeliveryOptionsResponse = {
                    success: false,
                    message: "❌ Error occurred while fetching transportation options.",
                    error: error.message
                };
                console.error("Error fetching transportation options:", error);
            }
        },

        async showNextDeliveryOptionsPage() {
            const nextToken = this.generateDeliveryOptionsResponse?.data?.pagination?.nextToken;
            if (nextToken) {
                await this.transportation_options_view(nextToken);
            }
        },

        async showPreviousDeliveryOptionsPage() {
            if (this.deliveryOptionsPages?.length > 1) {
                this.deliveryOptionsPages.pop();
                this.generateDeliveryOptionsResponse = this.deliveryOptionsPages[this.deliveryOptionsPages.length - 1];
            }
        },

        async selectTransportationOption(option) {
            try {
                this.form.transportationOptionId = option.transportationOptionId;
                const res = await axios.get(`${API_BASE_URL}/amzn/fba-shipment/step6/list_delivery_window_options`, {
                    params: { ...this.form }
                });

                if (res.data.success) {
                    res.data.message = "✅ Delivery window options listed successfully.";
                } else {
                    res.data.message = "❌ Failed to list delivery window options.";
                }

                this.deliveryWindowOptionsResponse = res.data;
            } catch (error) {
                this.deliveryWindowOptionsResponse = {
                    success: false,
                    message: "❌ Error occurred while listing delivery window options.",
                    error: error.message
                };
                console.error("Error listing delivery window options:", error);
            }
        },

        formatDate(isoDate) {
            const d = new Date(isoDate);
            return d.toLocaleDateString(undefined, {
                weekday: 'short',  // shows Mon, Tue, etc. (use 'long' for full name)
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        },

        async selectDeliveryWindow(option) {
            this.form.deliveryWindowOptionId = option.deliveryWindowOptionId;
            await this.confirmAllSteps();
        },

        async confirmAllSteps() {
            try {
                // Step 6b - Confirm Placement Option
                const res1 = await axios.get(`${API_BASE_URL}/amzn/fba-shipment/step6/confirm_placement_option`, {
                    params: { ...this.form }
                });
                this.confirmPlacementOptionResponse = res1.data;
                this.confirmPlacementOptionResponse.message = res1.data.success ? "✅ Placement option confirmed." : "❌ Failed to confirm placement option.";

                if (!res1.data.success) return;

                // Step 7a - Confirm Delivery Window Option
                const res2 = await axios.get(`${API_BASE_URL}/amzn/fba-shipment/step7/confirm_delivery_window_options`, {
                    params: { ...this.form }
                });
                this.confirmDeliveryWindowResponse = res2.data;
                this.confirmDeliveryWindowResponse.message = res2.data.success ? "✅ Delivery window confirmed." : "❌ Failed to confirm delivery window.";

                if (!res2.data.success) return;

                // Step 8a - Confirm Transportation Option
                const res3 = await axios.get(`${API_BASE_URL}/amzn/fba-shipment/step8/confirm_transportation_options`, {
                    params: { ...this.form }
                });
                this.confirmTransportationOptionResponse = res3.data;
                this.confirmTransportationOptionResponse.message = res3.data.success ? "✅ Transportation option confirmed." : "❌ Failed to confirm transportation option.";

            } catch (error) {
                console.error("❌ Error in confirming steps:", error);
            }
        }

    },
    computed: {
        canGoBack() {
            return this.deliveryOptionsPages && this.deliveryOptionsPages.length > 1;
        },
        canGoForward() {
            return (
                this.generateDeliveryOptionsResponse?.data?.pagination?.nextToken &&
                this.generateDeliveryOptionsResponse?.data?.pagination?.nextToken.length > 0
            );
        }
    }
};
</script>

<style scoped>
.container {
    padding: 20px;
    font-family: Arial, sans-serif;
}

.shipment-list {
    list-style: none;
    padding-left: 0;
}

.shipment-list li {
    margin: 10px 0;
}

.shipment-list button {
    padding: 10px 16px;
    background-color: #f0f0f0;
    border: 1px solid #ccc;
    cursor: pointer;
}

.shipment-form {
    margin-top: 20px;
    max-width: 400px;
}

.form-group {
    margin-bottom: 12px;
}

input {
    padding: 8px;
    width: 100%;
    box-sizing: border-box;
}

button {
    padding: 10px 18px;
    margin-top: 10px;
    cursor: pointer;
}

.response-box {
    background: #f9f9f9;
    border-left: 4px solid #4caf50;
    padding: 10px;
    margin-top: 20px;
}

.back-btn {
    margin-top: 20px;
    background: #ddd;
}

.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    padding: 20px;
    width: 90%;
    max-width: 900px;
    border-radius: 8px;
}

.modal-footer {
    margin-top: 10px;
    text-align: right;
}

.placement-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 16px;
}

.placement-table th,
.placement-table td {
    border: 1px solid #ccc;
    padding: 8px;
    text-align: left;
}

.placement-table th {
    background-color: #f2f2f2;
}

.selected-btn {
    background-color: #4CAF50;
    color: white;
    font-weight: bold;
}

.delivery-window-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 16px;
}

.delivery-window-table th,
.delivery-window-table td {
    padding: 8px;
    border: 1px solid #ccc;
    text-align: left;
}

.delivery-window-table th {
    background-color: #f9f9f9;
}
</style>