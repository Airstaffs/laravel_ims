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
            <div v-if="response" class="response-box">
                <h3>API Response:</h3>
                <pre>{{ response }}</pre>
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
                        <tr v-for="(item, index) in combinedPackingItems" :key="index">
                            <td>{{ item.msku }}</td>
                            <td>{{ item.quantity }}</td>
                            <td>{{ item.fnsku }}</td>
                            <td>{{ item.asin }}</td>
                            <td>
                                <select v-model="item.selectedBoxType">
                                    <option value="retail_box">Retail Box</option>
                                    <option value="white_box">White Box</option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
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
                    @click="productPage++; fetchProducts()">Next ➡</button>
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
            },
            stores: [],
            showStoreModal: false,
            selectedStore: '',
            response: null,
            packingResponse: null,
            listpackingResponse: null,
            listitemspackingResponse: null,
            confirmpackingResponse: null,
            showAddItemModal: false,
            productList: [],
            productSearch: '',
            productPerPage: 20,
            productPage: 1,
            productPagination: {},
            Donefetchingandconstructedthetableinput: false
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
        // Add this method to your Vue methods
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

                // Merge dimensions with items from listitemspackingResponse
                const items = this.listitemspackingResponse?.data?.items || [];

                this.combinedPackingItems = items.map(item => {
                    const match = dimensionData.find(d => d.asin === item.asin);
                    return {
                        ...item,
                        dimensionInfo: match || {
                            retail_box: {},
                            white_box: {},
                            asin: item.asin,
                            shipmentID: this.form.shipmentID
                        },
                        selectedBoxType: 'retail_box' // default selection
                    };
                });

                this.Donefetchingandconstructedthetableinput = true;
                console.log("✅ Process completed successfully!");
            } catch (error) {
                console.error("Error fetching and combining package dimensions:", error);
            }
        },
        async proceedToStep3PackingInfo() {
            try {
                const payload = this.combinedPackingItems.map(item => {
                    return {
                        msku: item.msku,
                        quantity: item.quantity,
                        fnsku: item.fnsku,
                        asin: item.asin,
                        ...item.dimensionInfo[item.selectedBoxType], // Extract dimensions from selected box type
                        box_type: item.selectedBoxType
                    };
                });

                const response = await axios.get('/amzn/fba-shipment/step3/packing_information', {
                    params: {
                        data: JSON.stringify(payload),
                        // Add other necessary fields if needed
                    }
                });

                console.log('Step 3 response:', response.data);
            } catch (error) {
                console.error('Error sending to Step 3:', error);
            }
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
</style>