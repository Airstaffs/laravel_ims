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
                                <td><button @click="deleteItem(shipment.shipmentID, item)">🗑️ Delete</button></td>
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
            <h2>Step 1: Create Inbound Plan for Shipment</h2>
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

            <button class="back-btn" @click="selectedShipment = null">🔙 Back to Shipments</button>
        </div>

        <!-- Step 2A: Generate Packing Options -->
        <div v-if="response && !packingResponse">
            <h2>Step 2A: Generate Packing</h2>
            <form @submit.prevent="generatePacking">
                <div class="form-group">
                    <label>Inbound Plan ID:</label>
                    <input v-model="form.inboundplanid" placeholder="Enter Inbound Plan ID" />
                </div>
                <button type="submit">📦 Generate Packing</button>
            </form>
        </div>

        <div v-if="packingResponse">
            <h3>Packing Response:</h3>
            <pre>{{ packingResponse }}</pre>
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
</template>

<script>
import shipmentService from '@/components/Stockroom/backend/fba_inbound_shipment_backend.js';
const API_BASE_URL = import.meta.env.VITE_API_URL;
export default {
    data() {
        return {
            showCartMode: false,
            cartID: null,
            cartItems: [], // This will hold the cart item list
            shipments: [],
            selectedShipment: null,
            visibleShipments: {},
            form: {
                store: '',
                destinationMarketplace: '',
                shipmentID: '',
                inboundplanid: '' // include this so it's bound in Step 2A
            },
            response: null,
            packingResponse: null,
            showAddItemModal: false,
            productList: [],
            productSearch: '',
            productPerPage: 20,
            productPage: 1,
            productPagination: {}

        };
    },
    created() {
        this.fetchShipments();
    },
    methods: {
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
                this.response = res;
            } catch (error) {
                console.error("Error creating shipment:", error);
                this.response = { error: error.message || "Failed to create shipment" };
            }
        },

        async generatePacking() {
            try {
                const res = await shipmentService.generatePacking(this.form);
                this.packingResponse = res;
            } catch (error) {
                console.error("Error generating packing:", error);
                this.packingResponse = { error: error.message || "Failed to generate packing" };
            }
        },
        addItem(shipmentID) {
            console.log('Add item to', shipmentID);
            // Modal or form logic here
        },
        deleteItem(shipmentID, item) {
            console.log('Delete', shipmentID, item);
            // API call or local remove
        },
        toggleVisibility(shipmentID) {
            this.visibleShipments[shipmentID] = !this.visibleShipments[shipmentID];
        },
        async openAddItemModal(shipmentID = null) {
            this.selectedShipmentID = shipmentID;
            this.showAddItemModal = true;
            this.productSearch = '';
            this.productPage = 1;

            if (this.showCartMode) {
                const res = await axios.get(`${API_BASE_URL}/amzn/fba-cart/get-or-create-cart`, {
                    params: { processby: this.currentUser }
                });
                this.cartID = res.data.CartID;
            }

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
                        CartID: this.cartID,
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
        async addToCart(prodID) {
            try {
                const res = await axios.post(`${API_BASE_URL}/amzn/fba-cart/add`, {
                    ProdID: prodID,
                    CartID: this.cartID,            // ✅ make sure cartID exists from getOrCreateCart
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