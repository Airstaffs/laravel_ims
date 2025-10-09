import axios from 'axios';

import shipmentService from "../backend/fba_inbound_shipment_backend.js";
const API_BASE_URL = import.meta.env.VITE_API_URL;
axios.defaults.headers.common['X-CSRF-TOKEN'] =
    document.querySelector('meta[name="csrf-token"]').getAttribute('content');
export default {
    data() {
        return {
            showCartMode: false,
            cartItems: [], // This will hold the cart item list
            shipments: [],
            selectedShipment: null,
            visibleShipments: {},
            form: {
                store: "",
                destinationMarketplace: "",
                shipmentID: "",
                inboundplanid: "",
                packingGroupId: "",
                packingOptionId: "",
                packageWeight: "",
                packageLength: "",
                packageWidth: "",
                packageHeight: "",
                placementOptionId: "",
                shipmentidfromapi: "",
                shipmentId: "",
                shipDate: new Date().toISOString().slice(0, 16), // datetime-local default
                totalDeclaredValue: "",
                transportationOptionId: "",
                deliveryWindowOptionId: "",
            },
            stores: [],
            showStoreModal: false,
            selectedStore: "",
            response: null,
            packingResponse: null,
            listpackingResponse: null,
            listitemspackingResponse: null,
            confirmPackingResponse: null,
            placementOptionResponse: null,
            showAddItemModal: false,
            productList: [],
            productSearch: "",
            productPerPage: 20,
            productPage: 1,
            productPagination: {},
            Donefetchingandconstructedthetableinput: false,
            step3PackingResponse: null,
            sheeshables: false,
            listPlacementOptionsResponse: null,
            enrichedPlacementOptions: [],
            selectedPlacementOptionId: "",
            transportationOptionsResponse: null,
            deliveryOptionsResponse: null,
            generateDeliveryOptionsResponse: null,
            nextToken: null,
            deliveryWindowOptionsResponse: null,
            confirmPlacementOptionResponse: null,
            confirmDeliveryWindowResponse: null,
            confirmTransportationOptionResponse: null,
            inboundPlansResponse: [],
            inboundPlansMessage: "",
            showInboundPlansModal: false,
            printingShipmentId: null,
            selectedProducts: [],          // array of selected product objects
            selectedProductIds: new Set(), // for O(1) contains/removal by ProductID
            showSelectedPanel: true,       // toggle the "View Selected" panel
            isBulkAdding: false,           // disables buttons during submit
        };
    },
    created() {
        this.fetchShipments();
    },
    methods: {
        addItem(shipmentID) {
            console.log("Add item to", shipmentID);
            // Modal or form logic here
        },
        toggleVisibility(shipmentID) {
            this.visibleShipments[shipmentID] =
                !this.visibleShipments[shipmentID];
        },
        async openAddItemModal(shipmentID = null) {
            this.selectedShipmentID = shipmentID;
            this.showAddItemModal = true;
            this.productSearch = "";
            this.productPage = 1;
            this.clearSelection();

            this.fetchProducts();
        },
        closeAddItemModal() {
            this.showAddItemModal = false;
            this.clearSelection();
        },
        async fetchProducts() {
            try {
                const res = await axios.get(`${API_BASE_URL}/products`, {
                    params: {
                        search: this.productSearch,
                        location: "stockroom",
                        page: this.productPage,
                        per_page: this.productPerPage,
                    },
                });

                this.productList = res.data.data;
                this.productPagination = {
                    total: res.data.total,
                    current_page: res.data.current_page,
                    last_page: res.data.last_page,
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
                        processby: this.currentUser,
                    });
                    alert("Item added to cart!");
                    this.fetchCartItems();
                } else {
                    await shipmentService.addItemToShipment(
                        this.selectedShipmentID,
                        product
                    );
                    alert("Item added to shipment!");
                    this.fetchShipments();
                }

                this.showAddItemModal = false;
            } catch (error) {
                console.error("Add item error:", error);
                alert("❌ Error adding item.");
            }
        },
        async addProductToShipment(product) {
            try {
                const res = await shipmentService.addItemToShipment(
                    this.selectedShipmentID,
                    product
                );
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
                const res = await axios.get(
                    `${API_BASE_URL}/amzn/fba-cart/list`,
                    {
                        params: { processby: this.currentUser }, // replace with actual user variable
                    }
                );
                this.cartItems = res.data;
            } catch (error) {
                console.error("Error fetching cart items:", error);
            }
        },
        async deleteItem(itemID) {
            if (!itemID) return;

            if (
                !confirm(
                    `Are you sure you want to delete this item (ID: ${itemID})?`
                )
            )
                return;

            try {
                const res = await shipmentService.deleteShipmentItem({
                    ID: itemID,
                });
                alert("🗑️ Item deleted.");
                this.fetchShipments();
            } catch (error) {
                console.error("Failed to delete item:", error);
                alert("❌ Could not delete item.");
            }
        },
        async deleteShipmentItem(payload) {
            const res = await axios.delete(
                `${API_BASE_URL}/amzn/fba-shipment/delete-item`,
                {
                    data: payload,
                }
            );
            return res.data;
        },
        async addToCart(prodID) {
            try {
                const res = await axios.post(
                    `${API_BASE_URL}/amzn/fba-cart/add`,
                    {
                        ProdID: prodID,
                        processby: "Jundell", // ✅ can be static for now
                    }
                );
                alert("Item added to cart ✅");
                this.fetchCartItems(); // refresh cart
            } catch (error) {
                if (error.response && error.response.status === 409) {
                    alert("⚠️ Item already in cart");
                } else {
                    console.error("Error adding to cart:", error);
                    alert("❌ Failed to add item.");
                }
            }
        },
        async removeCartItem(prodID) {
            try {
                await axios.delete(`${API_BASE_URL}/amzn/fba-cart/remove`, {
                    data: { ProdID: prodID },
                });
                alert("🗑️ Item removed from cart");
                this.fetchCartItems(); // refresh cart
            } catch (error) {
                console.error("Error removing cart item:", error);
                alert("❌ Failed to remove item");
            }
        },
        openStoreSelectModal() {
            this.selectedStore = "";
            this.showStoreModal = true;
            this.fetchStores();
        },
        closeStoreSelectModal() {
            this.showStoreModal = false;
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
                const res = await axios.post(
                    `${API_BASE_URL}/amzn/fba-cart/commit`,
                    {
                        store: this.selectedStore,
                    }
                );
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
                res.forEach((shipment) => {
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
                store: shipment.store || "Renovar Tech",
                destinationMarketplace: "ATVPDKIKX0DER",
                shipmentID: shipment.shipmentID,
                inboundplanid: "", // default empty
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
                await new Promise((resolve) => setTimeout(resolve, 3000));

                // Proceed to next step
                this.generatePacking();
            } catch (error) {
                console.error("Error creating shipment:", error);
                this.response = { error: error.message };
            }
        },

        async generatePacking() {
            try {
                if (
                    !this.form.store ||
                    !this.form.destinationMarketplace ||
                    !this.form.shipmentID ||
                    !this.form.inboundplanid
                ) {
                    console.error("Missing required fields:", this.form);
                    this.packingResponse = {
                        success: false,
                        message: "Error: Missing required fields.",
                        data: null,
                    };
                    return;
                }

                console.log("Sending request with payload:", this.form);

                const response = await axios.get(
                    "/amzn/fba-shipment/step2/generate-packing",
                    { params: this.form }
                );

                if (
                    response.data.success &&
                    response.data.operationStatus?.status === "SUCCESS"
                ) {
                    this.packingResponse = {
                        success: true,
                        message: "Packing generation started successfully.",
                        data: response.data,
                    };
                    await this.listPackingOptions(); // Proceed to next step
                } else {
                    throw new Error("Packing request did not succeed.");
                }
            } catch (error) {
                console.error(
                    "Error generating packing:",
                    error.response?.data || error.message
                );
                this.packingResponse = {
                    success: false,
                    message: "Error generating packing.",
                    data: error.response?.data || error.message,
                };
            }
        },

        async listPackingOptions() {
            try {
                const res = await shipmentService.listPackingOptions(this.form);

                if (
                    !res?.success ||
                    !Array.isArray(res?.data?.packingOptions)
                ) {
                    throw new Error(
                        "Invalid response or no packing options available."
                    );
                }

                const packingOption = res.data.packingOptions[0] || {};
                const packingGroupId =
                    Array.isArray(packingOption.packingGroups) &&
                        packingOption.packingGroups.length > 0
                        ? packingOption.packingGroups[0]
                        : "";

                this.form.packingOptionId = packingOption.packingOptionId || "";
                this.form.packingGroupId = packingGroupId;

                this.listpackingResponse = {
                    success: true,
                    message: "Packing options listed successfully.",
                    data: res.data,
                };

                await this.listItemsbyPackingOptions(); // Proceed to next step
            } catch (error) {
                console.error("Error listing packing options:", error);
                this.listpackingResponse = {
                    success: false,
                    message: "Error listing packing options.",
                    data: error.message,
                };
            }
        },

        async listItemsbyPackingOptions() {
            try {
                const res = await shipmentService.listItemsbyPackingOptions(
                    this.form
                );

                if (!res?.success) {
                    throw new Error("Failed to list items by packing options.");
                }

                this.listitemspackingResponse = {
                    success: true,
                    message: "Items listed successfully by packing options.",
                    data: res.data,
                };

                await this.confirmPackingOptions(); // Proceed to next step
            } catch (error) {
                console.error("Error listing items by packing options:", error);
                this.listitemspackingResponse = {
                    success: false,
                    message: "Error listing items by packing options.",
                    data: error.message,
                };
            }
        },

        async confirmPackingOptions() {
            try {
                const res = await shipmentService.confirmPackingOptions(
                    this.form
                );

                if (!res?.success) {
                    throw new Error("Failed to confirm packing options.");
                }

                this.confirmPackingResponse = {
                    success: true,
                    message: "Packing options confirmed successfully.",
                    data: res.data,
                };

                await this.fetchAndCombinePackageDimensions();

                console.log("✅ Process completed successfully!");
            } catch (error) {
                console.error("Error confirming packing options:", error);
                this.confirmPackingResponse = {
                    success: false,
                    message: "Error confirming packing options.",
                    data: error.message,
                };
            }
        },
        async fetchAndCombinePackageDimensions() {
            try {
                const res = await axios.post(
                    "/amzn/fba-shipment/fetch_package_dimensions",
                    {
                        store: this.form.store,
                        destinationMarketplace:
                            this.form.destinationMarketplace,
                        shipmentID: this.form.shipmentID,
                    }
                );

                if (!res.data.success || !Array.isArray(res.data.data)) {
                    throw new Error("Failed to fetch package dimensions");
                }

                const dimensionData = res.data.data;
                const items = this.listitemspackingResponse?.data?.items || [];

                this.combinedPackingItems = items.map((item) => {
                    const match = dimensionData.find(
                        (d) => d.asin === item.asin
                    );

                    // Try to find previous selection
                    const existingItem = this.combinedPackingItems?.find(
                        (i) => i.asin === item.asin
                    );
                    const selectedBoxType =
                        existingItem?.selectedBoxType || "retail_box";

                    return {
                        ...item,
                        dimensionInfo: match || {
                            retail_box: {},
                            white_box: {},
                            asin: item.asin,
                            shipmentID: this.form.shipmentID,
                        },
                        selectedBoxType,
                    };
                });

                this.Donefetchingandconstructedthetableinput = true;
            } catch (error) {
                console.error(
                    "Error fetching and combining package dimensions:",
                    error
                );
            }
        },
        async onBoxTypeChange(item) {
            try {
                const res = await axios.post(
                    "/amzn/fba-shipment/fetch_package_dimensions",
                    {
                        store: this.form.store,
                        destinationMarketplace:
                            this.form.destinationMarketplace,
                        shipmentID: this.form.shipmentID,
                    }
                );

                const dimensionData = res.data.data;
                const updated = dimensionData.find((d) => d.asin === item.asin);

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
            const requiredFields = [
                "store",
                "destinationMarketplace",
                "shipmentID",
                "inboundplanid",
                "packingGroupId",
                "packingOptionId",
            ];
            const missing = requiredFields.filter((field) => !this.form[field]);
            if (missing.length) {
                console.warn("Missing required form fields:", missing);
                return;
            }

            const payload = {
                items: this.combinedPackingItems.map((item) => {
                    return {
                        msku: item.msku,
                        quantity: item.quantity,
                        fnsku: item.fnsku,
                        asin: item.asin,
                        ...item.dimensionInfo[item.selectedBoxType],
                        box_type: item.selectedBoxType,
                    };
                }),
                package: {
                    weight: this.packageWeight,
                    dimensions: this.packageDimensions,
                },
            };

            try {
                const response = await axios.get(
                    "/amzn/fba-shipment/step3/packing_information",
                    {
                        params: {
                            data: JSON.stringify(payload),
                            ...this.form,
                        },
                    }
                );

                this.step3PackingResponse = {
                    success: true,
                    message: "Packing info submitted successfully!",
                    data: response.data,
                };

                this.Donefetchingandconstructedthetableinput = false;

                await this.step4PlacementOption();
            } catch (error) {
                console.error("Error sending to Step 3:", error);
                this.step3PackingResponse = {
                    success: false,
                    message: "❌ Failed to submit packing info.",
                    data: error.message,
                };
            }
        },
        formatBoxDimensions(box) {
            if (!box) return "N/A";
            const length = box.retail_length || box.white_length;
            const width = box.retail_width || box.white_width;
            const height = box.retail_height || box.white_height;
            const weight = box.lbs || box.white_lbs;

            if (!length || !width || !height) return "N/A dimensions";

            return `${length} x ${width} x ${height} inches — ${weight ?? "N/A"
                } lbs`;
        },
        async step4PlacementOption() {
            try {
                const res = await axios.get(
                    `${API_BASE_URL}/amzn/fba-shipment/step4/placement_option`,
                    {
                        params: {
                            ...this.form,
                        },
                    }
                );

                this.placementOptionResponse = {
                    success: res.data.success,
                    message: res.data.message,
                    data: res.data.data,
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
                    data: error.message,
                };
            }
        },
        async fetchPlacementOptions() {
            try {
                const res = await axios.get(
                    "/amzn/fba-shipment/step4/list_placement_option",
                    {
                        params: { ...this.form },
                    }
                );
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
                    const shipmentRes = await axios.get(
                        "/amzn/fba-shipment/step4/get_shipment",
                        {
                            params: {
                                ...this.form,
                                shipmentidfromapi: shipmentIdFromAPI,
                            },
                        }
                    );

                    const shipmentData = shipmentRes.data.data;
                    const address = shipmentData.destination?.address || {};
                    const fullAddress = `${address.name || "-"}, ${address.addressLine1 || "-"
                        }, ${address.city || "-"}, ${address.stateOrProvinceCode || "-"
                        } ${address.postalCode || "-"}, ${address.countryCode || "-"
                        }`;

                    enriched.push({
                        placementOptionId: option.placementOptionId,
                        shipmentId: shipmentIdFromAPI,
                        description: option.fees[0]?.description || "-",
                        fee: option.fees[0]?.value.amount || "0.00",
                        warehouseId:
                            shipmentData.destination?.warehouseId || "-",
                        destinationType:
                            shipmentData.destination?.destinationType || "-",
                        destinationAddress: fullAddress,
                        status: shipmentData.status || "-",
                    });
                } catch (e) {
                    console.warn(
                        `❌ Failed to enrich shipment ${shipmentIdFromAPI}:`,
                        e
                    );
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
                const response = await axios.get(
                    `${API_BASE_URL}/amzn/fba-shipment/step5/transportation_options`,
                    {
                        params: { ...this.form },
                    }
                );

                if (response.data.success) {
                    this.transportationOptionsResponse = {
                        success: true,
                        message:
                            "✅ Transportation options submitted successfully!",
                    };

                    await this.generateDeliveryOptions();
                } else {
                    this.transportationOptionsResponse = {
                        success: false,
                        message: "❌ Failed to submit transportation options.",
                    };
                }
            } catch (error) {
                console.error("Error submitting transport options:", error);
                this.transportationOptionsResponse = {
                    success: false,
                    message: "❌ Something went wrong.",
                };
            }
        },

        async generateDeliveryOptions() {
            try {
                const res = await axios.get(
                    `${API_BASE_URL}/amzn/fba-shipment/step5/generate_delivery_options`,
                    {
                        params: { ...this.form },
                    }
                );

                if (res.data.success) {
                    res.data.message =
                        "✅ Delivery options generated successfully!";
                    this.deliveryOptionsResponse = res.data;
                    await this.transportation_options_view();
                } else {
                    res.data.message =
                        "❌ Failed to generate delivery options.";
                    this.deliveryOptionsResponse = res.data;
                }
            } catch (error) {
                this.deliveryOptionsResponse = {
                    success: false,
                    message:
                        "❌ Error occurred while generating delivery options.",
                    error: error.message,
                };
                console.error("Error fetching delivery options:", error);
            }
        },

        async transportation_options_view(nextToken = null) {
            try {
                const params = {
                    ...this.form,
                };
                if (nextToken) {
                    params.nextToken = nextToken;
                }

                const res = await axios.get(
                    `${API_BASE_URL}/amzn/fba-shipment/step5/transportation_options_view`,
                    {
                        params,
                    }
                );

                if (res.data.success) {
                    res.data.message =
                        "✅ Transportation options fetched successfully!";
                    this.generateDeliveryOptionsResponse = res.data;

                    // Track all pages
                    if (!this.deliveryOptionsPages)
                        this.deliveryOptionsPages = [];
                    this.deliveryOptionsPages.push(res.data);
                } else {
                    res.data.message =
                        "❌ Failed to fetch transportation options.";
                    this.generateDeliveryOptionsResponse = res.data;
                }
            } catch (error) {
                this.generateDeliveryOptionsResponse = {
                    success: false,
                    message:
                        "❌ Error occurred while fetching transportation options.",
                    error: error.message,
                };
                console.error("Error fetching transportation options:", error);
            }
        },

        async showNextDeliveryOptionsPage() {
            const nextToken =
                this.generateDeliveryOptionsResponse?.data?.pagination
                    ?.nextToken;
            if (nextToken) {
                await this.transportation_options_view(nextToken);
            }
        },

        async showPreviousDeliveryOptionsPage() {
            if (this.deliveryOptionsPages?.length > 1) {
                this.deliveryOptionsPages.pop();
                this.generateDeliveryOptionsResponse =
                    this.deliveryOptionsPages[
                    this.deliveryOptionsPages.length - 1
                    ];
            }
        },

        async selectTransportationOption(option) {
            try {
                this.form.transportationOptionId =
                    option.transportationOptionId;
                const res = await axios.get(
                    `${API_BASE_URL}/amzn/fba-shipment/step6/list_delivery_window_options`,
                    {
                        params: { ...this.form },
                    }
                );

                if (res.data.success) {
                    res.data.message =
                        "✅ Delivery window options listed successfully.";
                } else {
                    res.data.message =
                        "❌ Failed to list delivery window options.";
                }

                this.deliveryWindowOptionsResponse = res.data;
            } catch (error) {
                this.deliveryWindowOptionsResponse = {
                    success: false,
                    message:
                        "❌ Error occurred while listing delivery window options.",
                    error: error.message,
                };
                console.error("Error listing delivery window options:", error);
            }
        },

        formatDate(isoDate) {
            const d = new Date(isoDate);
            return d.toLocaleDateString(undefined, {
                weekday: "short", // shows Mon, Tue, etc. (use 'long' for full name)
                year: "numeric",
                month: "short",
                day: "numeric",
            });
        },
        formatDateTime(datetime) {
            const date = new Date(datetime);
            const pad = (n) => n.toString().padStart(2, "0");
            return `${pad(date.getMonth() + 1)}-${pad(
                date.getDate()
            )}-${date.getFullYear()} ${pad(date.getHours())}:${pad(
                date.getMinutes()
            )}:${pad(date.getSeconds())}`;
        },
        async selectDeliveryWindow(option) {
            this.form.deliveryWindowOptionId = option.deliveryWindowOptionId;
            await this.confirmAllSteps();
        },

        async confirmAllSteps() {
            try {
                // Step 6b - Confirm Placement Option
                const res1 = await axios.get(
                    `${API_BASE_URL}/amzn/fba-shipment/step6/confirm_placement_option`,
                    {
                        params: { ...this.form },
                    }
                );
                this.confirmPlacementOptionResponse = res1.data;
                this.confirmPlacementOptionResponse.message = res1.data.success
                    ? "✅ Placement option confirmed."
                    : "❌ Failed to confirm placement option.";

                if (!res1.data.success) return;

                // Step 7a - Confirm Delivery Window Option
                const res2 = await axios.get(
                    `${API_BASE_URL}/amzn/fba-shipment/step7/confirm_delivery_window_options`,
                    {
                        params: { ...this.form },
                    }
                );
                this.confirmDeliveryWindowResponse = res2.data;
                this.confirmDeliveryWindowResponse.message = res2.data.success
                    ? "✅ Delivery window confirmed."
                    : "❌ Failed to confirm delivery window.";

                if (!res2.data.success) return;

                // Step 8a - Confirm Transportation Option
                const res3 = await axios.get(
                    `${API_BASE_URL}/amzn/fba-shipment/step8/confirm_transportation_options`,
                    {
                        params: { ...this.form },
                    }
                );
                this.confirmTransportationOptionResponse = res3.data;
                this.confirmTransportationOptionResponse.message = res3.data
                    .success
                    ? "✅ Transportation option confirmed."
                    : "❌ Failed to confirm transportation option.";
            } catch (error) {
                console.error("❌ Error in confirming steps:", error);
            }
        },
        async viewInboundPlans() {
            try {
                const res = await axios.get(
                    `${API_BASE_URL}/amzn/fba-shipment/get_inbound_plans`,
                    {
                        params: { shipmentID: this.form.shipmentID },
                    }
                );

                if (res.data.success) {
                    this.inboundPlansResponse = res.data.data;
                    this.inboundPlansMessage = res.data.message;
                    this.showInboundPlansModal = true;
                } else {
                    this.inboundPlansMessage =
                        "❌ Failed to fetch inbound plans.";
                }
            } catch (error) {
                console.error("Error fetching inbound plans:", error);
                this.inboundPlansMessage =
                    "❌ Unexpected error fetching inbound plans.";
            }
        },
        hideInboundPlan() {
            this.showInboundPlansModal = false;
        },
        selectInboundPlan(plan) {
            // Merge the plan data into the form, keeping existing values if not overwritten
            this.form = {
                ...this.form, // preserve existing values not in plan
                ...plan, // overwrite with values from selected plan
                shipDate:
                    this.form.shipDate || new Date().toISOString().slice(0, 16), // fallback for shipDate
            };
            this.showInboundPlansModal = false;

            console.log("Selected Inbound Plan:", plan);
            console.log("Updated Form:", this.form);
        },
        async cancelInboundPlan(plan) {
            try {
                const response = await fetch(
                    `/amzn/fba-shipment/step1/cancel-shipment?inboundplanid=${encodeURIComponent(
                        plan.inboundplanid
                    )}`
                );
                const data = await response.json();

                this.cancelInboundResponse = data;

                if (data.success) {
                    // Optional: remove the plan from the list
                    this.inboundPlansResponse =
                        this.inboundPlansResponse.filter(
                            (p) => p.inboundplanid !== plan.inboundplanid
                        );
                    alert("Inbound plan cancelled successfully.");
                } else {
                    alert("Failed to cancel inbound plan.");
                }
            } catch (error) {
                console.error("Error cancelling inbound plan:", error);
                alert("An error occurred while cancelling the plan.");
            }
        },
        async printShipmentLabel(shipmentID) {
            this.printingShipmentId = shipmentID;   // show spinner/disable button

            try {
                const resp = await axios.get('/amzn/fba-shipment/step10/print_label', {
                    params: {
                        shipmentID,
                        // pass optional comment if you support it server-side:
                        // printComment: this.printComment || ''
                    },
                });

                const { success, label_url, message } = resp.data || {};

                if (!success || !label_url) {
                    throw new Error(message || 'Failed to generate label.');
                }

                // Option A: open in a new tab (simple)
                window.open(label_url, '_blank', 'noopener');

                // Option B (optional): force a download automatically
                // const a = document.createElement('a');
                // a.href = label_url;
                // a.download = '';              // let browser pick filename, or set one
                // document.body.appendChild(a);
                // a.click();
                // a.remove();

            } catch (err) {
                const msg = err?.response?.data?.message || err.message || 'Unexpected error.';
                // show however you do notifications:
                // this.$toast?.error(msg);
                console.error('printShipmentLabel error:', msg);
            } finally {
                this.printingShipmentId = null;
            }
        },

        isSelected(productId) {
            return this.selectedProductIds.has(productId);
        },
        toggleProductSelection(product) {
            const id = product.ProductID;
            if (this.selectedProductIds.has(id)) {
                // deselect
                this.selectedProductIds.delete(id);
                this.selectedProducts = this.selectedProducts.filter(p => p.ProductID !== id);
            } else {
                // select
                this.selectedProductIds.add(id);
                this.selectedProducts.push(product);
            }
        },
        removeFromSelection(productId) {
            if (!this.selectedProductIds.has(productId)) return;
            this.selectedProductIds.delete(productId);
            this.selectedProducts = this.selectedProducts.filter(p => p.ProductID !== productId);
        },
        clearSelection() {
            this.selectedProductIds.clear();
            this.selectedProducts = [];
        },
        async addSelectedNow() {
            if (!this.selectedProducts.length) return;

            this.isBulkAdding = true;

            try {
                if (this.showCartMode) {
                    // bulk add to cart
                    const reqs = this.selectedProducts.map(p =>
                        axios.post(`${API_BASE_URL}/amzn/fba-cart/add`, {
                            ProdID: p.ProductID,
                            processby: this.currentUser, // or static for now
                        })
                            .catch(err => ({ __error: err })) // capture per-item error without failing all
                    );
                    const results = await Promise.all(reqs);

                    const errors = results.filter(r => r && r.__error);
                    if (errors.length) {
                        alert(`Added with some errors: ${this.selectedProducts.length - errors.length} OK, ${errors.length} failed.`);
                    } else {
                        alert(`✅ Added ${this.selectedProducts.length} item(s) to cart.`);
                    }

                    await this.fetchCartItems();
                } else {
                    // bulk add to shipment
                    if (!this.selectedShipmentID) {
                        alert('No shipment selected.');
                        return;
                    }

                    const reqs = this.selectedProducts.map(p =>
                        shipmentService.addItemToShipment(this.selectedShipmentID, p)
                            .catch(err => ({ __error: err }))
                    );
                    const results = await Promise.all(reqs);

                    const errors = results.filter(r => r && r.__error);
                    if (errors.length) {
                        alert(`Added with some errors: ${this.selectedProducts.length - errors.length} OK, ${errors.length} failed.`);
                    } else {
                        alert(`✅ Added ${this.selectedProducts.length} item(s) to shipment.`);
                    }

                    await this.fetchShipments();
                }

                // keep modal open (per your request to add multiple in one open)
                // but clear the selection to allow new picks
                this.clearSelection();
            } catch (e) {
                console.error('Bulk add error:', e);
                alert('❌ Failed to add selected items.');
            } finally {
                this.isBulkAdding = false;
            }
        },
    },
    computed: {
        canGoBack() {
            return (
                this.deliveryOptionsPages &&
                this.deliveryOptionsPages.length > 1
            );
        },
        canGoForward() {
            return (
                this.generateDeliveryOptionsResponse?.data?.pagination
                    ?.nextToken &&
                this.generateDeliveryOptionsResponse?.data?.pagination
                    ?.nextToken.length > 0
            );
        },
    },
};
