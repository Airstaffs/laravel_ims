<template>
    <div class="fba-container">
        <h1 class="page-title">📦 FBA Inbound Shipment</h1>
        <!-- Only show toggle if NOT in View 2 -->
        <div class="btn-toggle-container" v-if="!selectedShipment">
            <button @click="toggleView" class="btn toggle-btn">
                <span v-if="showCartMode">📦 View Shipments</span>
                <span v-else>🛒 View Cart</span>
            </button>
        </div>

        <!-- Show Cart View -->
        <div class="cart-container" v-if="showCartMode">
            <h2>🛒 Draft Cart</h2>

            <!-- Button to open the Add Item modal -->
            <button class="btn btn-addItem" @click="openAddItemModal()">
                ➕ Add an Item to Cart
            </button>

            <!-- Cart Table -->
            <div class="cart-table d-none d-md-block">
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
                        <tr v-for="item in cartItems" :key="item.ProdID">
                            <td>{{ item.ProdID }}</td>
                            <td>{{ item.ProductTitle }}</td>
                            <td>{{ item.FNSKUviewer }}</td>
                            <td>{{ item.MSKUviewer }}</td>
                            <td>{{ item.ASINviewer }}</td>
                            <td>{{ item.serialnumber }}</td>
                            <td>
                                <button
                                    class="btn btn-remove"
                                    @click="removeCartItem(item.ProdID)"
                                >
                                    🗑️ Remove
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="cart-table-mobile d-block d-md-none w-100">
                <div
                    class="card mb-3"
                    v-for="item in cartItems"
                    :key="item.ProdID"
                >
                    <div class="card-body">
                        <div class="mb-2">
                            <p>
                                <strong>ProductID:</strong>
                                {{ item.ProdID }}
                            </p>
                            <p>
                                <strong>Title:</strong>
                                {{ item.ProductTitle }}
                            </p>
                            <p>
                                <strong>FNSKU:</strong>
                                {{ item.FNSKUviewer }}
                            </p>
                            <p>
                                <strong>MSKU:</strong>
                                {{ item.MSKUviewer }}
                            </p>
                            <p>
                                <strong>ASIN:</strong>
                                {{ item.ASINviewer }}
                            </p>
                            <p>
                                <strong>Serial #:</strong>
                                {{ item.serialnumber }}
                            </p>
                        </div>

                        <hr />

                        <div class="card-table-actions">
                            <button
                                class="btn btn-remove"
                                @click="removeCartItem(item.ProdID)"
                            >
                                🗑️ Remove
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cart action buttons -->
            <div class="cart-button-container">
                <button class="btn btn-itemCheck">🔍 Item Check</button>
                <button
                    class="btn btn-commitCart"
                    @click="openStoreSelectModal"
                >
                    ✅ Commit Cart
                </button>
            </div>
        </div>

        <!-- View 1: List of Existing Shipments -->
        <div
            class="exist-shipments-container d-none d-md-block"
            v-if="!selectedShipment && !showCartMode"
        >
            <h2>Select a Shipment</h2>

            <div
                v-for="shipment in shipments"
                :key="shipment.shipmentID"
                class="shipment-block"
            >
                <div class="shipment-header">
                    <p>
                        <strong>{{ shipment.shipmentID }}</strong> -
                        <span>
                            {{ shipment.store }}
                            ( {{ shipment.item_count }}
                            items )
                        </span>
                    </p>

                    <div class="header-button-container">
                        <button
                            class="btn btn-toggle"
                            @click="printShipmentLabel(shipment.shipmentID)"
                            :disabled="
                                printingShipmentId === shipment.shipmentID
                            "
                        >
                            <span
                                v-if="
                                    printingShipmentId === shipment.shipmentID
                                "
                                >⏳ Printing…</span
                            >
                            <span v-else>Download Label</span>
                        </button>
                        <button
                            class="btn btn-toggle"
                            @click="toggleVisibility(shipment.shipmentID)"
                        >
                            {{
                                visibleShipments[shipment.shipmentID]
                                    ? "Hide Items"
                                    : "Show Items"
                            }}
                        </button>
                        <button
                            class="btn btn-showToAmazon"
                            @click="selectShipment(shipment)"
                        >
                            ➡️ Ship to Amazon
                        </button>
                    </div>
                </div>

                <table
                    class="shipment-table"
                    v-show="visibleShipments[shipment.shipmentID]"
                >
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
                            <td>
                                <img
                                    :src="'https://via.placeholder.com/50'"
                                    width="50"
                                />
                            </td>
                            <td>
                                <ul class="list-unstyled m-0">
                                    <li>
                                        <strong>Title:</strong>
                                        <span>{{ item.ProductName }}</span>
                                    </li>
                                    <li>
                                        <strong>ASIN:</strong>
                                        <span>{{ item.ASIN }}</span>
                                    </li>
                                    <li>
                                        <strong>MSKU:</strong>
                                        <span>{{ item.MSKU }}</span>
                                    </li>
                                    <li>
                                        <strong>FNSKU:</strong>
                                        <span>{{ item.FNSKU }}</span>
                                    </li>
                                    <li>
                                        <strong>Serial #:</strong>
                                        <span>{{ item.serialnumber }}</span>
                                    </li>
                                </ul>
                            </td>
                            <td>1</td>
                            <td>
                                <button
                                    class="btn btn-delete"
                                    @click="deleteItem(item.ID)"
                                >
                                    🗑️ Delete
                                </button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4">
                                <button
                                    class="btn btn-addItem"
                                    @click="
                                        openAddItemModal(shipment.shipmentID)
                                    "
                                >
                                    ➕ Add Item
                                </button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div
            class="exist-shipments-container-mobile d-block d-md-none"
            v-if="!selectedShipment && !showCartMode"
        >
            <h4>Select a Shipment</h4>

            <div class="card mb-3">
                <div
                    v-for="shipment in shipments"
                    :key="shipment.shipmentID"
                    class="card-body"
                >
                    <p class="mb-1">
                        <strong>{{ shipment.shipmentID }}</strong> -
                        <span>
                            {{ shipment.store }}
                            ( {{ shipment.item_count }} items )
                        </span>
                    </p>

                    <hr />

                    <div class="shipment-actions d-flex gap-2 overflow-auto">
                        <button
                            class="btn"
                            @click="printShipmentLabel(shipment.shipmentID)"
                            :disabled="
                                printingShipmentId === shipment.shipmentID
                            "
                        >
                            <span
                                v-if="
                                    printingShipmentId === shipment.shipmentID
                                "
                                >⏳ Printing…</span
                            >
                            <span v-else>Download Label</span>
                        </button>
                        <button
                            class="btn"
                            @click="toggleVisibility(shipment.shipmentID)"
                        >
                            {{
                                visibleShipments[shipment.shipmentID]
                                    ? "Hide Items"
                                    : "Show Items"
                            }}
                        </button>
                        <button
                            class="btn btn-showToAmazon"
                            @click="selectShipment(shipment)"
                        >
                            ➡️ Ship to Amazon
                        </button>
                    </div>

                    <hr v-show="visibleShipments[shipment.shipmentID]" />

                    <div
                        class="mb-2"
                        v-show="visibleShipments[shipment.shipmentID]"
                    >
                        <ul
                            class="list-unstyled p-4 border rounded-sm"
                            v-for="item in shipment.items"
                            :key="item.FNSKU"
                        >
                            <li>
                                <img
                                    :src="'https://via.placeholder.com/50'"
                                    width="50"
                                />
                            </li>
                            <li>
                                <ul class="list-unstyled mb-0">
                                    <li>
                                        <strong>Title:</strong>
                                        <span>{{ item.ProductName }}</span>
                                    </li>
                                    <li>
                                        <strong>ASIN:</strong>
                                        <span>{{ item.ASIN }}</span>
                                    </li>
                                    <li>
                                        <strong>MSKU:</strong>
                                        <span>{{ item.MSKU }}</span>
                                    </li>
                                    <li>
                                        <strong>FNSKU:</strong>
                                        <span>{{ item.FNSKU }}</span>
                                    </li>
                                    <li>
                                        <strong>Serial #:</strong>
                                        <span>{{ item.serialnumber }}</span>
                                    </li>
                                    <li>
                                        <hr />
                                        <div class="list-actions d-flex gap-2">
                                            <button
                                                class="btn btn-delete"
                                                @click="deleteItem(item.ID)"
                                            >
                                                🗑️ Delete
                                            </button>
                                            <button
                                                class="btn btn-addItem"
                                                @click="
                                                    openAddItemModal(
                                                        shipment.shipmentID
                                                    )
                                                "
                                            >
                                                ➕ Add Item
                                            </button>
                                        </div>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- View 2: Create Inbound Plan (Step 1) -->
        <div
            class="create-inbound-container"
            v-if="selectedShipment && !showCartMode"
        >
            <button class="btn btn-back" @click="selectedShipment = null">
                🔙 Back to Shipments
            </button>
            <p class="step-title">
                <strong>Step 1:</strong>
                <span> Create/Manage/Cancel Inbound Shipments </span>
            </p>
            <form @submit.prevent="createShipment" class="shipment-form">
                <fieldset class="form-group">
                    <label>
                        <strong>Store:</strong>
                    </label>
                    <input class="form-control" v-model="form.store" />
                </fieldset>

                <fieldset class="form-group">
                    <label>
                        <strong>Destination Marketplace:</strong>
                    </label>
                    <input
                        class="form-control"
                        v-model="form.destinationMarketplace"
                    />
                </fieldset>

                <fieldset class="form-group">
                    <label>
                        <strong>Shipment ID:</strong>
                    </label>
                    <input
                        class="form-control"
                        v-model="form.shipmentID"
                        disabled
                    />
                </fieldset>

                <fieldset class="form-group">
                    <label></label>
                    <button type="submit" class="btn btn-createInbound">
                        🚀 Create Inbound Plan
                    </button>
                </fieldset>
            </form>

            <!-- API Response -->
            <div v-if="response" class="alert alert-success w-100 m-0">
                <strong>Created Inboundplanid successfully.</strong>
            </div>

            <button class="btn btn-viewInbound w-100" @click="viewInboundPlans">
                📦 View Inbound Plans
            </button>

            <!-- Step 2A: Generate Packing Options -->
            <hr />

            <p class="step-title">
                <strong>Step 2:</strong>
                <span> Item Check & Verify Package Details </span>
            </p>

            <div v-if="packingResponse" class="alert alert-success w-100 m-0">
                <strong>{{ packingResponse.message }}</strong>
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
                        <template
                            v-for="(item, index) in combinedPackingItems"
                            :key="index"
                        >
                            <!-- Main Item Row -->
                            <tr>
                                <td>{{ item.msku }}</td>
                                <td>{{ item.quantity }}</td>
                                <td>{{ item.fnsku }}</td>
                                <td>{{ item.asin }}</td>
                                <td>
                                    <select
                                        v-model="item.selectedBoxType"
                                        @change="onBoxTypeChange(item)"
                                    >
                                        <option value="retail_box">
                                            Retail Box
                                        </option>
                                        <option value="white_box">
                                            White Box
                                        </option>
                                    </select>
                                </td>
                            </tr>

                            <!-- Dimension Row -->
                            <tr>
                                <td
                                    colspan="5"
                                    style="font-weight: bold; font-size: 0.9em"
                                >
                                    <template
                                        v-if="
                                            item.selectedBoxType ===
                                            'retail_box'
                                        "
                                    >
                                        Retail Box:
                                        {{
                                            item.dimensionInfo.retail_box
                                                .retail_length || "N/A"
                                        }}
                                        x
                                        {{
                                            item.dimensionInfo.retail_box
                                                .retail_width || "N/A"
                                        }}
                                        x
                                        {{
                                            item.dimensionInfo.retail_box
                                                .retail_height || "N/A"
                                        }}
                                        inches —
                                        {{
                                            item.dimensionInfo.retail_box
                                                .retail_lbs || "N/A"
                                        }}
                                        lbs
                                    </template>

                                    <template
                                        v-else-if="
                                            item.selectedBoxType === 'white_box'
                                        "
                                    >
                                        White Box:
                                        {{
                                            item.dimensionInfo.white_box
                                                .white_length || "N/A"
                                        }}
                                        x
                                        {{
                                            item.dimensionInfo.white_box
                                                .white_width || "N/A"
                                        }}
                                        x
                                        {{
                                            item.dimensionInfo.white_box
                                                .white_height || "N/A"
                                        }}
                                        inches —
                                        {{
                                            item.dimensionInfo.white_box
                                                .white_lbs || "N/A"
                                        }}
                                        lbs
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <!-- 📦 Global Package Dimensions Input -->
                <div class="form-group" style="margin-top: 24px">
                    <table
                        class="shipment-table"
                        style="width: auto; text-align: left"
                    >
                        <thead>
                            <tr>
                                <td colspan="4">
                                    <h3>
                                        📦 Package Dimensions for Entire
                                        Shipment
                                    </h3>
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
                                <td>
                                    <input
                                        type="number"
                                        v-model="form.packageLength"
                                        step="0.01"
                                        min="0"
                                        placeholder="e.g. 24.5"
                                    />
                                </td>
                                <td>
                                    <input
                                        type="number"
                                        v-model="form.packageWidth"
                                        step="0.01"
                                        min="0"
                                        placeholder="e.g. 12.25"
                                    />
                                </td>
                                <td>
                                    <input
                                        type="number"
                                        v-model="form.packageHeight"
                                        step="0.01"
                                        min="0"
                                        placeholder="e.g. 18.75"
                                    />
                                </td>
                                <td>
                                    <input
                                        type="number"
                                        v-model="form.packageWeight"
                                        step="0.01"
                                        min="0"
                                        placeholder="e.g. 48.6"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Submit -->
                <button
                    @click="proceedToStep3PackingInfo"
                    class="btn btn-primary"
                    style="margin-top: 16px"
                >
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
                        <tr
                            v-for="(option, index) in enrichedPlacementOptions"
                            :key="index"
                        >
                            <td>{{ option.placementOptionId }}</td>
                            <td>{{ option.shipmentId }}</td>
                            <td>{{ option.description }}</td>
                            <td>{{ option.destinationAddress }}</td>
                            <td>{{ option.fee }}</td>
                            <td>{{ option.warehouseId }}</td>
                            <td>{{ option.destinationType }}</td>
                            <td>{{ option.status }}</td>
                            <td>
                                <button
                                    :class="{
                                        'selected-btn':
                                            selectedPlacementOptionId ===
                                            option.placementOptionId,
                                    }"
                                    @click="selectPlacement(option)"
                                >
                                    {{
                                        selectedPlacementOptionId ===
                                        option.placementOptionId
                                            ? "✅ Selected"
                                            : "Select"
                                    }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Show shipDate and declaredValue if selection made -->
                <div
                    v-if="form.placementOptionId && form.shipmentidfromapi"
                    class="shipment-extra-fields"
                >
                    <h3 style="margin-top: 16px">📝 Shipment Details</h3>

                    <label>Ship Date:</label>
                    <input type="datetime-local" v-model="form.shipDate" />

                    <label style="margin-left: 16px"
                        >Total Declared Value (USD):</label
                    >
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        v-model="form.totalDeclaredValue"
                        placeholder="e.g. 250.00"
                    />

                    <button
                        class="btn btn-primary"
                        style="margin-left: 16px"
                        @click="submitTransportationOptions"
                    >
                        🚚 Submit Transportation Options
                    </button>
                </div>
            </div>

            <div v-if="deliveryOptionsResponse">
                <p>{{ deliveryOptionsResponse.message }}</p>
            </div>

            <div v-if="generateDeliveryOptionsResponse">
                <p>{{ generateDeliveryOptionsResponse.message }}</p>

                <table
                    v-if="
                        generateDeliveryOptionsResponse.data
                            ?.transportationOptions?.length
                    "
                >
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
                        <tr
                            v-for="(
                                option, i
                            ) in generateDeliveryOptionsResponse.data
                                .transportationOptions"
                            :key="i"
                        >
                            <td>{{ option.carrier.alphaCode }}</td>
                            <td>{{ option.carrier.name }}</td>
                            <td>{{ option.shippingMode }}</td>
                            <td>{{ option.shippingSolution }}</td>
                            <td>
                                <button
                                    @click="selectTransportationOption(option)"
                                >
                                    🚚 Choose
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="
                    generateDeliveryOptionsResponse?.data?.transportationOptions
                        ?.length
                "
            >
                <button
                    @click="showPreviousDeliveryOptionsPage"
                    :disabled="!canGoBack"
                    class="btn btn-secondary"
                >
                    ⬅️ Previous Page
                </button>

                <button
                    @click="showNextDeliveryOptionsPage"
                    :disabled="!canGoForward"
                    class="btn btn-primary"
                    style="margin-left: 8px"
                >
                    Next Page ➡️
                </button>

                <p style="margin-top: 8px">
                    Page {{ deliveryOptionsPages.length }}
                </p>
            </div>

            <div
                v-if="
                    deliveryWindowOptionsResponse?.data?.deliveryWindowOptions
                        ?.length
                "
            >
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
                        <tr
                            v-for="(
                                option, index
                            ) in deliveryWindowOptionsResponse.data
                                .deliveryWindowOptions"
                            :key="index"
                        >
                            <td>{{ option.availabilityType }}</td>
                            <td>{{ formatDate(option.startDate) }}</td>
                            <td>{{ formatDate(option.endDate) }}</td>
                            <td>{{ formatDate(option.validUntil) }}</td>
                            <td>
                                <button @click="selectDeliveryWindow(option)">
                                    {{
                                        form.deliveryWindowOptionId ===
                                        option.deliveryWindowOptionId
                                            ? "✅ Selected"
                                            : "Select"
                                    }}
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

            <hr />
            <h2>Step 3: Destination & Transportation</h2>
            <hr />
            <h2>Step 4: Shipment Details</h2>
            <hr />
            <h2>Step 5: Print Label</h2>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div v-if="showAddItemModal" class="modal modal-addItem">
        <div class="modal-overlay" @click="closeAddItemModal"></div>

        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add Items to Cart</h2>
                <button class="btn modal-close" @click="closeAddItemModal">
                    &times;
                </button>
            </div>

            <div class="modal-body">
                <div class="filter-container">
                    <input
                        class="form-control"
                        v-model="productSearch"
                        @input="fetchProducts"
                        placeholder="Search products..."
                    />

                    <fieldset>
                        <label><span>Per Page</span></label>
                        <select
                            class="form-control"
                            v-model="productPerPage"
                            @change="fetchProducts"
                        >
                            <option>20</option>
                            <option>50</option>
                            <option>100</option>
                        </select>
                    </fieldset>
                </div>

                <!-- Product list -->
                <div class="product-table d-none d-md-block">
                    <table class="product-table w-100">
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
                            <tr
                                v-for="product in productList"
                                :key="product.ProductID"
                            >
                                <td>{{ product.ProductID }}</td>
                                <td>{{ product.ProductTitle }}</td>
                                <td>{{ product.FNSKUviewer }}</td>
                                <td>{{ product.MSKUviewer }}</td>
                                <td>{{ product.ASINviewer }}</td>
                                <td>{{ product.serialnumber }}</td>
                                <td>
                                    <button
                                        class="btn btn-add"
                                        @click="toggleProductSelection(product)"
                                    >
                                        {{
                                            isSelected(product.ProductID)
                                                ? "✅ Selected"
                                                : "➕ Select"
                                        }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="product-table-mobile d-block d-md-none">
                    <div
                        class="card mb-3"
                        v-for="product in productList"
                        :key="product.ProductID"
                    >
                        <div class="card-body">
                            <input
                                type="checkbox"
                                :checked="isSelected(product.ProductID)"
                                @change="toggleProductSelection(product)"
                            />
                            <p class="mb-1">
                                <strong>ProductID:</strong>
                                {{ product.ProductID }}
                            </p>
                            <p class="mb-1">
                                <strong>Title:</strong>
                                {{ product.ProductTitle }}
                            </p>
                            <p class="mb-1">
                                <strong>FNSKU:</strong>
                                {{ product.FNSKUviewer }}
                            </p>
                            <p class="mb-1">
                                <strong>MSKU:</strong>
                                {{ product.MSKUviewer }}
                            </p>
                            <p class="mb-1">
                                <strong>ASIN:</strong>
                                {{ product.ASINviewer }}
                            </p>
                            <p class="mb-1">
                                <strong>Serial #:</strong>
                                {{ product.serialnumber }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- View Selected -->
                <div class="selected-panel">
                    <div class="d-flex items-center gap-2 mb-4">
                        <h3 style="margin: 0">
                            Selected Items ({{ selectedProducts.length }})
                        </h3>
                        <button
                            class="btn btn-secondary"
                            @click="showSelectedPanel = !showSelectedPanel"
                            style="padding: 4px 8px"
                        >
                            {{ showSelectedPanel ? "Hide" : "View" }}
                        </button>
                        <button
                            class="btn btn-remove"
                            @click="clearSelection"
                            :disabled="!selectedProducts.length"
                            style="padding: 4px 8px; margin-left: auto"
                        >
                            ✖ Clear All
                        </button>
                    </div>

                    <div
                        class="product-table d-none d-md-block"
                        v-if="showSelectedPanel"
                    >
                        <table>
                            <thead>
                                <tr>
                                    <th>ProductID</th>
                                    <th>Title</th>
                                    <th>FNSKU</th>
                                    <th>MSKU</th>
                                    <th>ASIN</th>
                                    <th>Serial #</th>
                                    <th>Remove</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!selectedProducts.length">
                                    <td
                                        colspan="7"
                                        style="text-align: center; opacity: 0.7"
                                    >
                                        No items selected yet.
                                    </td>
                                </tr>
                                <tr
                                    v-for="sp in selectedProducts"
                                    :key="'sel-' + sp.ProductID"
                                >
                                    <td>{{ sp.ProductID }}</td>
                                    <td>{{ sp.ProductTitle }}</td>
                                    <td>{{ sp.FNSKUviewer }}</td>
                                    <td>{{ sp.MSKUviewer }}</td>
                                    <td>{{ sp.ASINviewer }}</td>
                                    <td>{{ sp.serialnumber }}</td>
                                    <td>
                                        <button
                                            class="btn btn-delete"
                                            @click="
                                                removeFromSelection(
                                                    sp.ProductID
                                                )
                                            "
                                        >
                                            🗑 Remove
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        class="product-table-mobile d-block d-md-none"
                        v-if="showSelectedPanel"
                    >
                        <div
                            class="card mb-3"
                            v-for="sp in selectedProducts"
                            :key="'sel-' + sp.ProductID"
                        >
                            <div class="card-body">
                                <p class="mb-1" v-if="!selectedProducts.length">
                                    No items selected yet.
                                </p>

                                <p class="mb-1">
                                    <strong>ProductID:</strong>
                                    {{ sp.ProductID }}
                                </p>
                                <p class="mb-1">
                                    <strong>Title:</strong>
                                    {{ sp.ProductTitle }}
                                </p>
                                <p class="mb-1">
                                    <strong>FNSKU:</strong>
                                    {{ sp.FNSKUviewer }}
                                </p>
                                <p class="mb-1">
                                    <strong>MSKU:</strong>
                                    {{ sp.MSKUviewer }}
                                </p>
                                <p class="mb-1">
                                    <strong>ASIN:</strong>
                                    {{ sp.ASINviewer }}
                                </p>
                                <p class="mb-1">
                                    <strong>Serial #:</strong>
                                    {{ sp.serialnumber }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="product-table-pagination mt-4">
                        <button
                            class="btn btn-prev"
                            :disabled="productPage <= 1"
                            @click="
                                productPage--;
                                fetchProducts();
                            "
                        >
                            ⬅ Prev
                        </button>
                        <button
                            class="btn btn-next"
                            :disabled="
                                productPage >= productPagination.last_page
                            "
                            @click="
                                productPage++;
                                fetchProducts();
                            "
                        >
                            Next ➡
                        </button>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer">
                <button
                    class="btn btn-commit"
                    :disabled="!selectedProducts.length || isBulkAdding"
                    @click="addSelectedNow"
                >
                    {{
                        isBulkAdding
                            ? "⏳ Adding…"
                            : showCartMode
                            ? "Add Selected to Cart"
                            : "Add Selected to Shipment"
                    }}
                </button>
            </div>
        </div>
    </div>

    <!-- Store Selection Modal -->
    <div v-if="showStoreModal" class="modal modal-showStore">
        <div class="modal-overlay" @click="closeStoreSelectModal"></div>

        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    Select Store before creating Shipment
                </h5>
            </div>

            <div class="modal-body">
                <select class="form-control" v-model="selectedStore">
                    <option disabled value="">-- Choose a Store --</option>
                    <option
                        v-for="store in stores"
                        :key="store.store_id"
                        :value="store.storename"
                    >
                        {{ store.storename }}
                    </option>
                </select>
            </div>

            <div class="modal-footer">
                <button class="btn btn-commit" @click="commitCart">
                    🚀 Confirm Shipment Cart
                </button>
                <button class="btn btn-cancel" @click="closeStoreSelectModal">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- View Inboundplans Modal -->
    <div v-if="showInboundPlansModal" class="modal modal-inbound">
        <div class="modal-overlay" @click="hideInboundPlan"></div>

        <div class="modal-content">
            <div class="modal-header">
                <h3>📦 Inbound Plans for Shipment: {{ form.shipmentID }}</h3>
                <button class="btn modal-close" @click="hideInboundPlan">
                    &times;
                </button>
            </div>

            <div class="modal-body">
                <p>{{ inboundPlansMessage }}</p>

                <table class="placement-table">
                    <thead>
                        <tr>
                            <th>InboundPlanID</th>
                            <th>PlacementOptionID</th>
                            <th>PackingGroupID</th>
                            <th>Created On</th>
                            <th>Last Updated On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(plan, index) in inboundPlansResponse"
                            :key="index"
                        >
                            <td>{{ plan.inboundplanid }}</td>
                            <td>{{ plan.placementoptionid }}</td>
                            <td>{{ plan.packinggroupid }}</td>
                            <td>{{ formatDateTime(plan.created_time) }}</td>
                            <td>{{ formatDateTime(plan.updated_time) }}</td>
                            <td>
                                <button @click="selectInboundPlan(plan)">
                                    📦 Choose Inbound Plan
                                </button>
                                <button @click="cancelInboundPlan(plan)">
                                    📦 Cancel Inbound Plan
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
import fba_inbound_shipment from "./fba_inbound_shipment.js";
export default fba_inbound_shipment;
</script>

<style scoped src="./fba_inbound_shipment.css"></style>
