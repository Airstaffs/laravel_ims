import { eventBus } from "../../components/eventbus";
import "../../../css/modules.css";
import "./fbmOrders.css";
const API_BASE_URL = import.meta.env.VITE_API_URL;

import ScrollFab from "../../components/ScrollFab.vue";
import PrintInvoiceModal from "./modals/printinvoice.vue";
import ManualShipmentLabelModal from "./modals/manualshipmentlabel.vue";
import ManualDispenseModal from "./modals/manualdispense.vue";
import CarrierModal from "./modals/selectcarrier.vue";
import Swal from "sweetalert2";
import PrintDocumentsModal from "./modals/PrintCenterModal.vue";
import ShipmentLabelHistory from "./modals/shipmentlabelhistory.vue";
import ScannerComponent from "../../components/Scanner.vue";
import { SoundService } from "../../components/Sound_service";

export default {
    name: "FbmOrderModule",
    components: {
        // REMOVED ALL COMPONENT REFERENCES - USING INLINE MODALS ONLY
        PrintInvoiceModal,
        ScrollFab,
        ManualShipmentLabelModal,
        ManualDispenseModal,
        CarrierModal,
        PrintDocumentsModal,
        ShipmentLabelHistory,
        ScannerComponent,
        SoundService
    },
    data() {
        return {
            apiBaseUrl: window.location.origin,
            orders: [],
            loading: true,
            currentPage: 1,
            totalPages: 1,
            perPage: 10,
            selectAll: false,

            // For sorting and filtering
            sortColumn: "purchase_date",
            sortOrder: "desc",

            // Store filter
            stores: [],
            selectedStore: "",
            statusFilter: "",

            showFilters: true,
            workHistoryFilters: {
                sortBy: "",
                startDate: "",
                endDate: "",
                userId: "all",
                lateOrders: "",
                searchQuery: "",
            },
            workHistoryStats: {
                totalOrders: 0,
            },

            // For order details modal
            showOrderDetailsModal: false,
            selectedOrder: null,

            // For process modal
            showProcessModal: false,
            currentProcessOrder: null,
            selectedItems: [],
            processData: {
                shipmentType: "Standard",
                trackingNumber: "",
                notes: "",
            },
            isProcessing: false,

            // For auto dispense (standalone and in process modal)
            showAutoDispenseModal: false,
            autoDispenseOrder: null,
            dispenseProducts: [],
            selectedDispenseProducts: {},
            loadingDispenseProducts: false,
            processingAutoDispense: false,

            // Manual Dispense State
            showManualDispenseModal: false,
            currentManualDispenseItem: null,

            // For persistent order selection across pagination
            persistentSelectedOrderIds: [],
            dispenseItemsSelected: [],

            // for shipment-label modal
            rateFetchAttemptedByOrderId: {},
            showShipmentLabelModal: false,
            selectedShipmentData: null,
            rateResultsByOrderId: {}, // { [platform_order_id]: ShippingServiceList[] }
            selectedCarriers: {}, // { [platform_order_id]: "serviceId" }
            showCarrierModal: false,
            carrierModalOrder: null,
            carrierModalTab: "eligible", // "eligible" or "rejected"
            rejectedRatesByOrderId: {},
            selectedCarrierRateByOrderId: {},

            // for get rates
            forms: {}, // holds forms[orderId]
            rateResults: [], // results of getRates

            // for purchase shipping label
            purchasingLabelByOrderId: {}, // { [orderId]: true/false }
            purchaseResultsByOrderId: {}, // store API results per order

            // for workHistory modal
            showWorkHistoryModal: false,
            workHistory: null,
            error: null,

            // Enhanced work history filters and stats (from old code)
            workHistoryFilters: {
                sortBy: "purchase_date",
                startDate: "2024-05-20T05:49",
                endDate: "2025-06-03T05:49",
                userId: "all",
                lateOrders: "",
                searchQuery: "",
                carrierFilter: "",
                storeFilter: "",
            },
            workHistoryStats: {
                totalOrders: 0,
            },

            // Work History Pagination
            workHistoryPagination: {
                currentPage: 1,
                perPage: 20,
                totalRecords: 0,
                totalPages: 1,
                from: 0,
                to: 0,
            },
            quickJumpPage: 1,

            // for printing invoice
            printInvoiceVisible: false,
            selectedOrder: null,

            // for manualshipmentlabel
            manualShipmentLabelVisible: false,
            suppressDispenseSelectionSync: false,

            // for printcentermodal
            showPrintDocumentsModal: false,
            selectedPlatformOrderIdsForPrint: [],

            printCenterDefaults: {
                labelAction: "PrintShipmentLabel",
                invoiceAction: "PrintInvoice",
                invoiceSettings: {
                    displayPrice: false,
                    signatureRequired: false,
                    testPrint: false,
                },
            },

            // shipmentlabelhistory modal
            showShipmentLabelHistory: false,

            //list of serials and tracking to be matched
            serialsAndTracking: [],

            //match serial scanner
            showManualInput: false,

            //scanner input
            scanInput: "",

            autoVerifyTimeout: null,

            // For validation
            scanInputValid: true,

            //scan serial or tracking
            scanMode: 'serial'

        };
    },
    computed: {
        // For global search
        searchQuery() {
            return eventBus.searchQuery || "";
        },

        // Check if any orders are selected
        hasSelectedOrders() {
            return this.persistentSelectedOrderIds.length > 0;
        },

        selectedCountAcrossAllPages() {
            // what you show beside "order selected across all pages"
            return this.persistentSelectedOrderIds?.length || 0;
        },

        allOrdersHaveCarrier() {
            const orders = this.selectedShipmentData || [];
            if (!orders.length) return false;

            return orders.every((o) => {
                const oid = o.platform_order_id;
                const selected = this.selectedCarriers?.[oid];
                return !!(selected && selected.ShippingServiceId); // must exist
            });
        },

        canBuyShipment() {
            return this.validateOrdersBeforePurchase().ok;
        },
        buyShipmentDisabledReason() {
            const r = this.validateOrdersBeforePurchase();
            return r.ok ? "" : r.msg;
        },

        hasAnySelection() {
            return (
                (this.persistentSelectedOrderIds?.length || 0) > 0 ||
                (this.dispenseItemsSelected?.length || 0) > 0
            );
        },

        // Form validation for processing
        isProcessFormValid() {
            return (
                this.selectedItems.length > 0 &&
                this.processData.trackingNumber.trim() !== "" &&
                this.processData.shipmentType !== ""
            );
        },

        // Check if we can confirm auto dispense
        canConfirmDispense() {
            return Object.keys(this.selectedDispenseProducts).length > 0;
        },

        // Check if current order has items without product_id assigned
        currentOrderHasUnassignedItems() {
            if (!this.currentProcessOrder || !this.currentProcessOrder.items)
                return false;
            return this.currentProcessOrder.items.some(
                (item) => !this.isItemDispensed(item),
            );
        },

        // NEW: Check if current order has any dispensed items (for Cancel Dispense button)
        currentOrderHasDispensedItems() {
            if (!this.currentProcessOrder || !this.currentProcessOrder.items)
                return false;
            return this.currentProcessOrder.items.some((item) =>
                this.isItemDispensed(item),
            );
        },

        // Get only valid dispensed items
        validDispenseItems() {
            return this.dispenseItemsSelected.filter((itemId) => {
                let foundItem = null;
                this.orders.forEach((order) => {
                    if (order.items) {
                        const item = order.items.find(
                            (i) => i.outboundorderitemid === itemId,
                        );
                        if (item) {
                            foundItem = item;
                        }
                    }
                });

                return foundItem && this.isItemDispensed(foundItem);
            });
        },

        visibleWorkHistoryPages() {
            const total = this.workHistoryPagination.totalPages;
            const current = this.workHistoryPagination.currentPage;
            const maxVisible = 5;

            if (total <= maxVisible) {
                return Array.from({ length: total }, (_, i) => i + 1);
            }

            let start = 1;
            let end = maxVisible;

            // Start shifting window from page 5
            if (current >= 5) {
                start = current - 3;
                end = current + 1;

                // Prevent overflow beyond last page
                if (end > total) {
                    end = total;
                    start = end - maxVisible + 1;
                }
            }

            return Array.from({ length: end - start + 1 }, (_, i) => start + i);
        },
    },
    methods: {
        handleChangeScanMode() {
            this.scanMode = this.scanMode === 'serial' ? 'tracking' : 'serial'
            this.$nextTick(() => {
                    this.$refs.scanInputRef?.focus();
                });
        },
        handleHardwareScan(scannedCode) {
            this.scanInput = scannedCode;
            this.processMatchSerialNumber();
        },
        openMatchSerialScannerModal(order, index) {
             this.$refs.scanner.openScannerModal();

            const productInfo = order.dispensed_products[index]

            // Get serials as an array
            this.serialsAndTracking = Object.entries(productInfo)
                .filter(([key]) => key.startsWith('serialNumber'))
                .map(([, value]) => ({ serial: value }))
                .filter(item => item.serial);

                if(order.tracking_number) {
                    this.serialsAndTracking.push({tracking: order.tracking_number})
                }

            console.log( this.serialsAndTracking, "productInfo")
            
              this.$nextTick(() => {
                    this.$refs.scanInputRef?.focus();
                });
        },

        handleMatchSerialScannerOpened() {
             console.log("Scanner openedss");
             this.showManualInput = this.$refs.scanner.showManualInput;
            // this.resetScannerState();
        },
        handleMatchSerialScannerClosed() {
            //clear data
            this.serialsAndTracking = []

            //reset mode
            this.scanMode = 'serial'
        },
        getTrackingNumber(order) {
            if (!order || !order.items || !Array.isArray(order.items)) {
                return 'N/A';
            }
            const itemWithTracking = order.items.find(item => item.tracking_number);
            return itemWithTracking ? itemWithTracking.tracking_number : 'N/A';
        },
        handleSerialInput() {
             this.validateSerialOrTracking();

            // Auto verify after short delay when typing
            if (this.scanInputValid && this.scanInput.length >= 5) {
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }

                this.autoVerifyTimeout = setTimeout(() => {
                    this.processMatchSerialNumber();
                }, 500);
            }
        },
        validateSerialOrTracking() {
            this.scanInputValid = this.scanInput.trim() !== "";
            if (!this.scanInputValid) {
                SoundService.error();
            }
            return this.scanInput;
        },
        processMatchSerialNumber() {
            this.validateSerialOrTracking();
            if (!this.scanInputValid) {
                this.$refs.scanner.showScanError("Please enter a valid serial number");
                SoundService.error();
                return;
            }

            // Mode-specific text
            const isSerial = this.scanMode === 'serial';
            const modeLabel = isSerial ? 'Serial' : 'Tracking';
            const property = isSerial ? 'serial' : 'tracking';
            
            // Start loading animation
            this.$refs.scanner.startLoading("Matching serial number...");
            
            this.$nextTick(() => {
                    this.$refs.scanInputRef?.focus();
            });
            // Simulate slight delay of matching process
            setTimeout(() => {
                const exists = this.serialsAndTracking.some(
                    item => item[property] === this.scanInput
                );

                if (exists) {
                    this.$refs.scanner.showScanSuccess(`${modeLabel} number matched!`);
                    this.$refs.scanner.addSuccessScan({
                        Message: `${modeLabel} Number: ${this.scanInput}`,
                        Status: `${modeLabel} Number Matched`
                    });
                    SoundService.Matched();
                } else {
                    this.$refs.scanner.showScanError(`${modeLabel} number not found in this order`);
                    this.$refs.scanner.addErrorScan({
                        Message: `${modeLabel}: ${this.scanInput}`,
                        Status: `${modeLabel} Number Not Matched`
                    });
                    SoundService.NotMatched();
                }

                this.scanInput = ""
                this.$refs.scanner.stopLoading();
            }, 500);
        },
         handleModeChange(event) {
            this.showManualInput = event.manual;

            this.$nextTick(() => {
                this.$refs.scanInputRef?.focus();
            });
        },

        handleScannerReset() {
            setTimeout(() => {
                this.$refs.scanInputRef?.focus();
            }, 500)
        },

        /**
         * Get tracking status from order items
         */
        getTrackingStatusFromItems(order) {
            if (!order || !order.items || !Array.isArray(order.items)) {
                return 'N/A';
            }
            const itemWithTracking = order.items.find(item => item.tracking_status);
            return itemWithTracking ? itemWithTracking.tracking_status : 'N/A';
        },

        /**
         * Get carrier info (carrier + carrier_description)
         */
        getCarrierInfo(order) {
            if (!order || !order.items || !Array.isArray(order.items)) {
                return 'N/A';
            }
            
            const itemWithCarrier = order.items.find(item => 
                item.carrier || item.carrier_description
            );
            
            if (!itemWithCarrier) return 'N/A';
            
            const carrier = itemWithCarrier.carrier || '';
            const description = itemWithCarrier.carrier_description || '';
            
            // Concatenate with space if both exist
            if (carrier && description) {
                return `${carrier} ${description}`;
            }
            
            return carrier || description || 'N/A';
        },

        canSelectOrder(order) {
            return this.hasDispensedItems(order);
        },

        toggleFilters() {
            this.showFilters = !this.showFilters;
        },
        // for shipment-label modal
        //________________________________________________________________________________
        PurchaseShippingLabel() {
            if (this.dispenseItemsSelected.length === 0) {
                alert("Please select items first.");
                return;
            }

            this.forms = {};
            this.rateResults = [];
            this.selectedCarriers = {};

            const itemIds = this.dispenseItemsSelected.join(",");
            console.log("Items");
            console.log(itemIds);
            axios
                .get("api/fbm-orders/shipping-label-selected-items", {
                    params: { itemIds },
                })
                .then((response) => {
                    this.selectedShipmentData = response.data; // Store result
                    this.openShipmentLabelModal();
                })
                .catch((error) => {
                    alert("Failed to fetch shipment info.");
                    console.error(error);
                });
        },

        openShipmentLabelModal() {
            this.showShipmentLabelModal = true;

            if (!this.forms) this.forms = {};
            if (!this.selectedCarriers) this.selectedCarriers = {};
            if (!this.rateResultsByOrderId) this.rateResultsByOrderId = {};
            if (!this.rejectedRatesByOrderId) this.rejectedRatesByOrderId = {};
            if (!this.rateFetchAttemptedByOrderId)
                this.rateFetchAttemptedByOrderId = {}; // ✅ NEW

            this.selectedCarriers = {};
            this.rateResultsByOrderId = {};
            this.rejectedRatesByOrderId = {};
            this.selectedCarrierRateByOrderId = {};
            this.rateFetchAttemptedByOrderId = {};

            (this.selectedShipmentData || []).forEach((order) => {
                const orderId = order.platform_order_id;
                if (!orderId) return;

    if (!this.forms[orderId]) {
        const defaults = this.getDefaultPackageFromItems(order.items || []);

        this.forms[orderId] = {
            deliveryExperience: "DeliveryConfirmationWithoutSignature",
            length: defaults?.length ?? "",
            width: defaults?.width ?? "",
            height: defaults?.height ?? "",
            dimensionUnit: defaults?.dimensionUnit ?? "inches",
            weight: defaults?.weight ?? "",
            weightUnit: defaults?.weightUnit ?? "pound",
            carrier_description: "",
            shipBy: new Date().toISOString(),
        };
    }

                if (this.selectedCarriers[orderId] === undefined) {
                    this.selectedCarriers[orderId] = "";
                }

                if (!this.rateResultsByOrderId[orderId]) {
                    this.rateResultsByOrderId[orderId] = [];
                }

                if (!this.rejectedRatesByOrderId[orderId]) {
                    // ✅ NEW (safe default)
                    this.rejectedRatesByOrderId[orderId] = [];
                }

                if (this.rateFetchAttemptedByOrderId[orderId] === undefined) {
                    // ✅ NEW
                    this.rateFetchAttemptedByOrderId[orderId] = false;
                }
            });
        },

        closeShipmentLabelModal() {
            this.showShipmentLabelModal = false;
        },

        getDefaultPackageFromItems(items = []) {
    // pick first non-null as baseline
    const first = items.find(it =>
        it.white_length || it.white_width || it.white_height || it.white_value
    );

    if (!first) return null;

    const same = (k) => items.every(it => {
        const a = it[k];
        const b = first[k];
        // treat null/undefined/"" as null
        return (a ?? null) === (b ?? null);
    });

    const keys = ['white_length','white_width','white_height','white_value','white_unit'];
    const allSame = keys.every(k => same(k));

    if (!allSame) return null;

    return {
        length: first.white_length ?? '',
        width: first.white_width ?? '',
        height: first.white_height ?? '',
        weight: first.white_value ?? '',
        weightUnit: (first.white_unit ?? '').toLowerCase() || 'pound',
        // dimension unit isn’t stored in tblasin from your screenshot
        dimensionUnit: 'inches',
    };
},

        async getRates() {
            try {
                console.log(this.selectedShipmentData);
                if (!this.selectedShipmentData?.length) {
                    Swal.fire(
                        "No orders selected",
                        "Please select at least one order.",
                        "warning",
                    );
                    return;
                }

                // ✅ mark "attempted" BEFORE validation returns
                (this.selectedShipmentData || []).forEach((o) => {
                    const oid = o.platform_order_id;
                    if (oid) this.rateFetchAttemptedByOrderId[oid] = true;
                });

                // ✅ validate forms
                for (const order of this.selectedShipmentData) {
                    const id = order.platform_order_id;
                    const f = this.forms[id];
                    const required = [
                        "length",
                        "width",
                        "height",
                        "dimensionUnit",
                        "weight",
                        "weightUnit",
                        "deliveryExperience",
                    ];

                    for (const k of required) {
                        if (!f?.[k]) {
                            Swal.fire(
                                "Missing Information",
                                `Please fill <b>${k}</b> for order <b>${id}</b>.`,
                                "warning",
                            );
                            return;
                        }
                    }
                }

                const payload = {
                    orders: this.selectedShipmentData,
                    forms: this.forms,
                    destinationMarketplace: "ATVPDKIKX0DER",
                    nextToken: null,
                };

                // ✅ Swal loading BEFORE request
                Swal.fire({
                    title: "Getting shipping rates…",
                    html:
                        '<div class="progress" style="height: 20px;">' +
                        '<div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>' +
                        "</div>",
                    didOpen: () => Swal.showLoading(),
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                });

                const res = await axios.post(
                    "/amzn/fbm-orders/purchase-label/rates",
                    payload,
                );

                // ✅ normalize into maps
                const results = res.data?.results || [];
                const eligibleMap = {};
                const rejectedMap = {};

                results.forEach((row) => {
                    const oid = row.platform_order_id;
                    const payload = row?.rates?.payload || row?.rates || {};

                    eligibleMap[oid] = payload.ShippingServiceList || [];
                    rejectedMap[oid] =
                        payload.RejectedShippingServiceList || [];
                });

                this.rateResultsByOrderId = eligibleMap;
                this.rejectedRatesByOrderId = rejectedMap;

                // reset carrier selections (rates changed)
                this.selectedCarriers = {};
                (this.selectedShipmentData || []).forEach((o) => {
                    if (o.platform_order_id)
                        this.selectedCarriers[o.platform_order_id] = "";
                });

                Swal.fire(
                    "Success",
                    "Shipping rates retrieved successfully.",
                    "success",
                );
            } catch (err) {
                console.error(err);
                Swal.fire(
                    "Error",
                    "Failed to retrieve shipping rates. Please check the console or network.",
                    "error",
                );
            } finally {
                Swal.close(); // ✅ always closes loading
            }
        },

        // select carrier modal
        openCarrierModal(order) {
            this.carrierModalOrder = order;
            this.carrierModalTab = "eligible";
            this.showCarrierModal = true;

            // quick debug
            const id = order.platform_order_id;
            // console.log("OPEN CARRIER MODAL:", id, {
            //     eligible: this.getEligibleRatesForOrder(id),
            //     rejected: this.getRejectedRatesForOrder(id),
            // });
        },

        getEligibleRatesForOrder(orderId) {
            return this.rateResultsByOrderId?.[orderId] || [];
        },

        getRejectedRatesForOrder(orderId) {
            return this.rejectedRatesByOrderId?.[orderId] || [];
        },

        getRateAmount(rate) {
            if (!rate) return "N/A";

            // Most common shape
            const amt = rate?.Rate?.Amount;

            // Some APIs/carriers return other shapes (optional fallback)
            const fallback =
                rate?.ShippingServiceCost?.Amount ||
                rate?.TotalCharge?.Amount ||
                rate?.rate?.amount;

            const value = amt ?? fallback;

            return value != null && value !== "" ? value : "N/A";
        },

        hasEligibleRates(orderId) {
            return (this.rateResultsByOrderId?.[orderId] || []).length > 0;
        },
        hasAnyRates(orderId) {
            return (
                (this.rateResultsByOrderId?.[orderId] || []).length > 0 ||
                (this.rejectedRatesByOrderId?.[orderId] || []).length > 0
            );
        },

        closeCarrierModal() {
            this.showCarrierModal = false;
            this.carrierModalOrder = null;
        },

        async handleCarrierSelected(rate) {
            const orderId = this.carrierModalOrder?.platform_order_id;
            if (!orderId) return;

            // Save selected carrier (full rate object)
            this.selectedCarriers = {
                ...this.selectedCarriers,
                [orderId]: rate,
            };

            this.selectedCarrierRateByOrderId = {
                ...this.selectedCarrierRateByOrderId,
                [orderId]: rate,
            };

            this.closeCarrierModal();

            // ✅ Auto buy shipment label for THIS order
            // await this.buyShipmentLabelForOrder(orderId);
        },

        hasAnyRateData(orderId) {
            return (
                (this.rateResultsByOrderId?.[orderId] || []).length > 0 ||
                (this.rejectedRatesByOrderId?.[orderId] || []).length > 0
            );
        },

        formatDatetext(date) {
            if (!date) return "N/A";

            try {
                return new Date(date).toLocaleString(undefined, {
                    year: "numeric",
                    month: "short",
                    day: "2-digit",
                    hour: "2-digit",
                    minute: "2-digit",
                });
            } catch (e) {
                return "N/A";
            }
        },

        validateOrdersBeforePurchase() {
            const orders = this.selectedShipmentData || [];
            if (!orders.length)
                return { ok: false, msg: "No selected orders." };

            // Require carrier per order + required fields per order
            const requiredFields = [
                "length",
                "width",
                "height",
                "dimensionUnit",
                "weight",
                "weightUnit",
                "deliveryExperience",
                "shipBy", // you are sending this to backend
                // "deliverBy", // only if you truly require it
                // "currency",  // only if you truly require it
            ];

            for (const o of orders) {
                const oid = o.platform_order_id;
                if (!oid)
                    return {
                        ok: false,
                        msg: "A selected order is missing platform_order_id.",
                    };

                // 1) carrier selected
                const carrier = this.selectedCarriers?.[oid];
                if (!carrier || !carrier.ShippingServiceId) {
                    return {
                        ok: false,
                        msg: `Select a carrier for order ${oid}.`,
                    };
                }

                // 2) required fields filled
                const f = this.forms?.[oid];
                if (!f)
                    return {
                        ok: false,
                        msg: `Missing form data for order ${oid}.`,
                    };

                for (const k of requiredFields) {
                    const v = f?.[k];

                    // treat empty string / null / undefined as missing
                    if (
                        v === undefined ||
                        v === null ||
                        String(v).trim() === ""
                    ) {
                        return {
                            ok: false,
                            msg: `Please fill ${k} for order ${oid}.`,
                        };
                    }
                }

                // 3) numeric sanity checks (common failure)
                const nums = ["length", "width", "height", "weight"];
                for (const n of nums) {
                    const val = Number(f[n]);
                    if (!Number.isFinite(val) || val <= 0) {
                        return {
                            ok: false,
                            msg: `${n} must be a valid number > 0 for order ${oid}.`,
                        };
                    }
                }

                // 4) unit checks (common failure)
                const dimUnit = String(f.dimensionUnit).toLowerCase();
                const weightUnit = String(f.weightUnit).toLowerCase();
                const okDim = [
                    "inches",
                    "inch",
                    "cm",
                    "centimeters",
                    "centimetres",
                ].includes(dimUnit);
                const okW = [
                    "pound",
                    "lb",
                    "lbs",
                    "kilogram",
                    "kg",
                    "grams",
                    "gram",
                    "g",
                    "ounces",
                    "ounce",
                    "oz",
                ].includes(weightUnit);

                if (!okDim)
                    return {
                        ok: false,
                        msg: `Invalid dimensionUnit for order ${oid}.`,
                    };
                if (!okW)
                    return {
                        ok: false,
                        msg: `Invalid weightUnit for order ${oid}.`,
                    };
            }

            return { ok: true };
        },

        async buyShipmentLabel() {
            const check = this.validateOrdersBeforePurchase();
            if (!check.ok) {
                Swal.fire(
                    "Missing Information",
                    check.msg || "Please complete all required fields.",
                    "warning",
                );
                return;
            }

            const ordersWithCarrier = (this.selectedShipmentData || []).map(
                (o) => ({
                    ...o,
                    selectedCarrier: this.selectedCarriers[o.platform_order_id],
                }),
            );

            const payload = {
                orders: ordersWithCarrier,
                forms: this.forms,
                destinationMarketplace: "ATVPDKIKX0DER",
                nextToken: null,
            };

            Swal.fire({
                title: "Purchasing shipping labels…",
                html:
                    '<div class="progress" style="height: 20px;">' +
                    '<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>' +
                    "</div>",
                didOpen: () => Swal.showLoading(),
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
            });

            try {
                const res = await axios.post(
                    "/amzn/fbm-orders/purchase-label/createshipment",
                    payload,
                );

                const results = res.data?.results || [];
                const failed = results.filter(
                    (r) =>
                        r?.error ||
                        r?.exception ||
                        (r?.status && r.status >= 400),
                );

                if (failed.length) {
                    console.log("purchase failed items:", failed);

                    // Optional: show which orders failed (first few)
                    const list = failed
                        .slice(0, 5)
                        .map(
                            (f) =>
                                `• ${f.platform_order_id || "Unknown Order"}`,
                        )
                        .join("<br>");

                    Swal.fire(
                        "Some labels failed",
                        `${failed.length} label(s) failed.<br>${list}${
                            failed.length > 5 ? "<br>• ..." : ""
                        }<br><br>Check console for details.`,
                        "error",
                    );
                    return;
                }

                Swal.fire(
                    "Success",
                    "Shipment labels purchased successfully.",
                    "success",
                );
                this.closeShipmentLabelModal();
            } catch (err) {
                console.error(err);
                Swal.fire(
                    "Purchase failed",
                    "Check console/network for details.",
                    "error",
                );
            } finally {
                Swal.close(); // ✅ always closes loading
            }
        },

        openPrintDocumentsModal() {
            const platformIds = this.getSelectedPlatformOrderIds();

            if (!platformIds.length) {
                Swal.fire({
                    icon: "warning",
                    title: "Ooops",
                    text: "Please select at least one order to print documents",
                    confirmButtonText: "Ok",
                });
                return;
            }

            this.selectedPlatformOrderIdsForPrint = platformIds; // ✅ AmazonOrderIds
            this.showPrintDocumentsModal = true;
        },

        closePrintDocumentsModal() {
            this.showPrintDocumentsModal = false;
        },
        /*
        async handlePrintDocuments({ labelOrders, invoiceOrders }) {
            // labelOrders/invoiceOrders are now AmazonOrderIds like 113-xxx

            if (labelOrders?.length) {
                const res = await axios.post("/fbm-orders-shippinglabel", {
                    platform_order_ids: labelOrders,
                    action: "PrintShipmentLabel",
                    note: "",
                });

                const rows = res.data?.results || [];
                
                rows.forEach((r) => {
                    if (r?.pdf_url) window.open(r.pdf_url, "_blank");
                });
                
            }

            // invoiceOrders later...
        }, 
        */

        async handlePrintDocuments(payload, done) {
            const {
                labelOrders,
                invoiceOrders,
                labelAction,
                invoiceAction,
                invoiceSettings,
            } = payload || {};

            const labels = Array.isArray(labelOrders) ? labelOrders : [];
            const invoices = Array.isArray(invoiceOrders) ? invoiceOrders : [];

            const result = { label: {}, invoice: {} };

            try {
                // 1) Shipping Labels
                if (labels.length) {
                    const res = await axios.post("/fbm-orders-shippinglabel", {
                        platform_order_ids: labels,
                        action: labelAction,
                        note: "",
                    });

                    const rows = res?.data?.results || [];

                    // map by order id
                    const byId = new Map(
                        rows.map((r) => [
                            String(r.order_id || r.platform_order_id || ""),
                            r,
                        ]),
                    );

                    labels.forEach((oid) => {
                        const row = byId.get(String(oid));
                        const pdfUrl = row?.pdf_url || "";

                        if (!row) {
                            result.label[oid] = {
                                ok: false,
                                status: "Failed",
                                pdfUrl: "",
                            };
                            return;
                        }

                        // If action is view and we have a URL => Ready to view + clickable
                        if (labelAction === "ViewShipmentLabel" && pdfUrl) {
                            result.label[oid] = {
                                ok: true,
                                status: "Ready to view",
                                pdfUrl,
                            };
                        } else if (labelAction === "PrintShipmentLabel") {
                            // print mode: we usually don't need url, but store it if provided
                            result.label[oid] = {
                                ok: true,
                                status: "Printed",
                                pdfUrl,
                            };
                        } else {
                            result.label[oid] = {
                                ok: false,
                                status: "Failed",
                                pdfUrl: "",
                            };
                        }
                    });
                }

                // 2) Invoices
                if (invoices.length) {
                    const res = await axios.post("/fbm-orders-invoice", {
                        platform_order_ids: invoices,
                        action: invoiceAction,
                        settings: {
                            displayPrice: invoiceSettings?.displayPrice
                                ? "TRUE"
                                : "FALSE",
                            signatureRequired:
                                invoiceSettings?.signatureRequired
                                    ? "TRUE"
                                    : "FALSE",
                            testPrint: !!invoiceSettings?.testPrint,
                            width: 350,
                        },
                    });

                    const rows = res?.data?.results || [];
                    const byId = new Map(
                        rows.map((r) => [
                            String(r.order_id || r.platform_order_id || ""),
                            r,
                        ]),
                    );

                    invoices.forEach((oid) => {
                        const row = byId.get(String(oid));
                        const pdfUrl = row?.pdf_url || "";

                        if (!row) {
                            result.invoice[oid] = {
                                ok: false,
                                status: "Failed",
                                pdfUrl: "",
                            };
                            return;
                        }

                        if (invoiceAction === "ViewInvoice" && pdfUrl) {
                            result.invoice[oid] = {
                                ok: true,
                                status: "Ready to view",
                                pdfUrl,
                            };
                        } else if (invoiceAction === "PrintInvoice") {
                            result.invoice[oid] = {
                                ok: true,
                                status: "Printed",
                                pdfUrl,
                            };
                        } else {
                            result.invoice[oid] = {
                                ok: false,
                                status: "Failed",
                                pdfUrl: "",
                            };
                        }
                    });
                }
            } catch (e) {
                // if the whole request fails, mark all requested as Failed
                labels.forEach(
                    (oid) =>
                        (result.label[oid] = {
                            ok: false,
                            status: "Failed",
                            pdfUrl: "",
                        }),
                );
                invoices.forEach(
                    (oid) =>
                        (result.invoice[oid] = {
                            ok: false,
                            status: "Failed",
                            pdfUrl: "",
                        }),
                );
            } finally {
                // ✅ tell modal we’re done so it can enable button + show statuses
                if (typeof done === "function") done(result);
            }
        },

        getSelectedPlatformOrderIds() {
            const selectedOutboundIds = this.persistentSelectedOrderIds || [];
            if (!selectedOutboundIds.length) return [];

            // map outboundorderid -> platform_order_id
            const map = new Map(
                (this.orders || []).map((o) => [
                    String(o.outboundorderid),
                    o.platform_order_id,
                ]),
            );

            return selectedOutboundIds
                .map((id) => map.get(String(id)))
                .filter(Boolean);
        },

        // Shipment Label History Modal
        openShipmentLabelHistoryModal() {
            this.showShipmentLabelHistory = true;
        },
        closeShipmentLabelHistoryModal() {
            this.showShipmentLabelHistory = false;
        },

        // work history modal
        openWorkHistoryModal() {
            console.log("🚀 Opening work history modal...");
            this.showWorkHistoryModal = true;

            // Reset pagination to first page when opening modal
            this.workHistoryPagination.currentPage = 1;

            this.fetchWorkHistory();

            this.$nextTick(() => {
                const modal = document.querySelector(".modal.workHistory");
                if (modal) {
                    modal.classList.add("show");
                    modal.style.display = "flex";
                    console.log("✅ Modal should now be visible");
                } else {
                    console.error("❌ Modal element not found in DOM");
                }
            });
        },

        closeWorkHistoryModal() {
            console.log("🔒 Closing work history modal...");
            this.showWorkHistoryModal = false;

            this.workHistoryFilters = {
                sortBy: "purchase_date",
                startDate: "2024-05-20T05:49",
                endDate: "2025-06-03T05:49",
                userId: "all",
                lateOrders: "",
                searchQuery: "",
                carrierFilter: "",
                storeFilter: "",
            };

            // Also force hide via DOM manipulation
            this.$nextTick(() => {
                const modal = document.querySelector(".modal.workHistory");
                if (modal) {
                    modal.classList.remove("show");
                    modal.style.display = "none";
                }
            });
        },

        async fetchWorkHistory(resetPage = false) {
            console.log(
                "🔄 fetchWorkHistory called - using POST method with pagination",
            );

            if (resetPage) {
                this.workHistoryPagination.currentPage = 1;
            }

            this.loading = true;
            this.error = null;

            try {
                const payload = {
                    user_id: this.workHistoryFilters.userId,
                    start_date: this.workHistoryFilters.startDate
                        ? this.formatDateForAPI(
                              this.workHistoryFilters.startDate,
                          )
                        : "2024-05-20",
                    end_date: this.workHistoryFilters.endDate
                        ? this.formatDateForAPI(this.workHistoryFilters.endDate)
                        : "2025-06-01",
                    sort_by: this.workHistoryFilters.sortBy,
                    sort_order: "DESC",
                    search_query: this.workHistoryFilters.searchQuery || "",
                    late_orders: this.workHistoryFilters.lateOrders || "",
                    carrier_filter: this.workHistoryFilters.carrierFilter || "",
                    store_filter: this.workHistoryFilters.storeFilter || "",
                    // Add pagination parameters
                    page: this.workHistoryPagination.currentPage,
                    per_page: this.workHistoryPagination.perPage,
                };

                console.log(
                    "Sending work history request with payload:",
                    payload,
                );

                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/work-history`,
                    payload,
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            )?.content,
                        },
                    },
                );

                console.log("Work history response:", response);

                if (response.data && response.data.success) {
                    // Handle paginated response
                    this.workHistory = response.data.history;

                    // Update pagination info
                    this.workHistoryPagination.totalRecords =
                        response.data.total || 0;
                    this.workHistoryPagination.totalPages =
                        response.data.last_page || 1;
                    this.workHistoryPagination.currentPage =
                        response.data.current_page || 1;
                    this.workHistoryPagination.perPage =
                        response.data.per_page || 10;
                    this.workHistoryPagination.from = response.data.from || 0;
                    this.workHistoryPagination.to = response.data.to || 0;

                    // Update total orders stat
                    this.workHistoryStats.totalOrders =
                        this.workHistoryPagination.totalRecords;

                    if (response.data.message) {
                        console.log(
                            "Work history message:",
                            response.data.message,
                        );
                    }
                } else {
                    this.workHistory = [];
                    this.workHistoryPagination.totalRecords = 0;
                    this.workHistoryPagination.totalPages = 1;
                }
            } catch (err) {
                this.error = "Failed to load work history.";
                console.error("Work history fetch error:", err);

                if (err.response) {
                    console.error("Error response:", err.response.data);
                    console.error("Error status:", err.response.status);
                }
            } finally {
                this.loading = false;
            }
        },

        // Change per page
        changeWorkHistoryPerPage() {
            this.workHistoryPagination.currentPage = 1;
            this.fetchWorkHistory();
        },

        // Navigate to previous page
        prevWorkHistoryPage() {
            if (this.workHistoryPagination.currentPage > 1) {
                this.workHistoryPagination.currentPage--;
                this.fetchWorkHistory();
            }
        },

        // Navigate to next page
        nextWorkHistoryPage() {
            if (
                this.workHistoryPagination.currentPage <
                this.workHistoryPagination.totalPages
            ) {
                this.workHistoryPagination.currentPage++;
                this.fetchWorkHistory();
            }
        },

        // Go to specific page
        goToWorkHistoryPage(page) {
            if (page >= 1 && page <= this.workHistoryPagination.totalPages) {
                this.workHistoryPagination.currentPage = page;
                this.fetchWorkHistory();
            }
        },

        // Get page range for pagination buttons
        getWorkHistoryPageRange() {
            const current = this.workHistoryPagination.currentPage;
            const total = this.workHistoryPagination.totalPages;
            const delta = 2; // Number of pages to show on each side of current page

            const range = [];
            const rangeWithDots = [];
            let l;

            for (let i = 1; i <= total; i++) {
                if (
                    i === 1 ||
                    i === total ||
                    (i >= current - delta && i <= current + delta)
                ) {
                    range.push(i);
                }
            }

            range.forEach((i) => {
                if (l) {
                    if (i - l === 2) {
                        rangeWithDots.push(l + 1);
                    } else if (i - l !== 1) {
                        rangeWithDots.push("...");
                    }
                }
                rangeWithDots.push(i);
                l = i;
            });

            return rangeWithDots.filter((i) => i !== "...");
        },

        // Quick jump to page
        quickJumpToPage() {
            const page = parseInt(this.quickJumpPage);
            if (
                !isNaN(page) &&
                page >= 1 &&
                page <= this.workHistoryPagination.totalPages
            ) {
                this.goToWorkHistoryPage(page);
            } else {
                alert(
                    `Please enter a valid page number between 1 and ${this.workHistoryPagination.totalPages}`,
                );
                this.quickJumpPage = this.workHistoryPagination.currentPage;
            }
        },

        // Format date for API (convert from datetime-local to YYYY-MM-DD)
        formatDateForAPI(dateTimeString) {
            if (!dateTimeString) return "";
            return dateTimeString.split("T")[0];
        },

        // Format date for work history table display
        formatWorkDate(dateStr) {
            if (!dateStr || dateStr === "N/A") return "N/A";
            try {
                const date = new Date(dateStr);
                return (
                    date.toLocaleDateString("en-US", {
                        month: "2-digit",
                        day: "2-digit",
                        year: "numeric",
                    }) +
                    " " +
                    date.toLocaleTimeString("en-US", {
                        hour: "2-digit",
                        minute: "2-digit",
                        hour12: true,
                    })
                );
            } catch (e) {
                return dateStr;
            }
        },

        // Export work history functionality
        async exportWorkHistory() {
            try {
                // Check if date filters are applied
                const isDateFiltered =
                    this.workHistoryFilters.startDate &&
                    this.workHistoryFilters.endDate;

                // Build confirmation message HTML
                let confirmMessageHtml = "<div style='text-align: left;'>";
                confirmMessageHtml +=
                    "<p style='margin-bottom: 15px;'><strong>⚠️ Note:</strong> This will export ALL matching records, not just the current page.</p>";
                confirmMessageHtml +=
                    "<p style='margin-bottom: 10px;'><strong>Export Details:</strong></p>";
                confirmMessageHtml +=
                    "<ul style='list-style: none; padding-left: 0;'>";

                if (isDateFiltered) {
                    const startDate = new Date(
                        this.workHistoryFilters.startDate,
                    ).toLocaleDateString();
                    const endDate = new Date(
                        this.workHistoryFilters.endDate,
                    ).toLocaleDateString();
                    confirmMessageHtml += `<li style='margin-bottom: 8px;'>📅 <strong>Date Range:</strong> ${startDate} to ${endDate}</li>`;
                } else {
                    confirmMessageHtml +=
                        "<li style='margin-bottom: 8px;'>📅 <strong>Date Range:</strong> All available data (no date filter applied)</li>";
                }

                if (this.workHistoryFilters.userId !== "all") {
                    confirmMessageHtml += `<li style='margin-bottom: 8px;'>👤 <strong>User:</strong> ${this.workHistoryFilters.userId}</li>`;
                }

                if (this.workHistoryFilters.searchQuery) {
                    confirmMessageHtml += `<li style='margin-bottom: 8px;'>🔍 <strong>Search:</strong> "${this.workHistoryFilters.searchQuery}"</li>`;
                }

                if (this.workHistoryFilters.carrierFilter) {
                    confirmMessageHtml += `<li style='margin-bottom: 8px;'>🚚 <strong>Carrier:</strong> ${this.workHistoryFilters.carrierFilter}</li>`;
                }

                if (this.workHistoryFilters.storeFilter) {
                    confirmMessageHtml += `<li style='margin-bottom: 8px;'>🏪 <strong>Store:</strong> ${this.workHistoryFilters.storeFilter}</li>`;
                }

                if (this.workHistoryFilters.lateOrders) {
                    confirmMessageHtml += `<li style='margin-bottom: 8px;'>⏰ <strong>Late Orders:</strong> ${this.workHistoryFilters.lateOrders}</li>`;
                }

                confirmMessageHtml += "</ul></div>";

                // Show SweetAlert confirmation dialog
                const result = await Swal.fire({
                    title: "Export Work History to Excel",
                    html: confirmMessageHtml,
                    icon: "question",
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Yes, export it!",
                    cancelButtonText: "Cancel",
                    customClass: {
                        popup: "swal-wide",
                    },
                });

                if (!result.isConfirmed) {
                    return;
                }

                // Continue with export...

                // Show loading state
                const exportButton = document.querySelector(
                    ".btn-export, .btn-primary",
                );
                const originalText = exportButton ? exportButton.innerHTML : "";
                if (exportButton) {
                    exportButton.innerHTML =
                        '<i class="fas fa-spinner fa-spin"></i> Exporting...';
                    exportButton.disabled = true;
                }

                // ✅ FIXED: Ensure all values are proper strings or empty strings
                const payload = {
                    user_id: String(this.workHistoryFilters.userId || "all"),
                    start_date: this.workHistoryFilters.startDate
                        ? String(
                              this.formatDateForAPI(
                                  this.workHistoryFilters.startDate,
                              ),
                          )
                        : "",
                    end_date: this.workHistoryFilters.endDate
                        ? String(
                              this.formatDateForAPI(
                                  this.workHistoryFilters.endDate,
                              ),
                          )
                        : "",
                    sort_by: String(
                        this.workHistoryFilters.sortBy || "purchase_date",
                    ),
                    sort_order: String("DESC"),
                    search_query: String(
                        this.workHistoryFilters.searchQuery || "",
                    ),
                    late_orders: String(
                        this.workHistoryFilters.lateOrders || "",
                    ),
                    carrier_filter: String(
                        this.workHistoryFilters.carrierFilter || "",
                    ),
                    store_filter: String(
                        this.workHistoryFilters.storeFilter || "",
                    ),
                };

                console.log("Sending export request with payload:", payload);

                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/export-work-history`,
                    payload,
                    {
                        responseType: "blob", // Important for file downloads
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            )?.content,
                        },
                        timeout: 300000, // 5 minute timeout for large exports
                    },
                );

                // Check if response is actually an error
                if (response.data.type === "application/json") {
                    const text = await response.data.text();
                    const errorData = JSON.parse(text);
                    throw new Error(errorData.message || "Export failed");
                }

                // Generate filename based on filters
                let filename = "work-history";
                if (isDateFiltered) {
                    const startDate = this.formatDateForAPI(
                        this.workHistoryFilters.startDate,
                    );
                    const endDate = this.formatDateForAPI(
                        this.workHistoryFilters.endDate,
                    );
                    filename += `_${startDate}_to_${endDate}`;
                }
                if (this.workHistoryFilters.userId !== "all") {
                    filename += `_${this.workHistoryFilters.userId}`;
                }
                filename += `_${new Date().toISOString().split("T")[0]}.xlsx`;

                // Create download link
                const url = window.URL.createObjectURL(
                    new Blob([response.data]),
                );
                const link = document.createElement("a");
                link.href = url;
                link.setAttribute("download", filename);
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(url);

                // Restore button state
                if (exportButton) {
                    exportButton.innerHTML =
                        originalText ||
                        '<i class="fas fa-download"></i> Export Work History';
                    exportButton.disabled = false;
                }

                // alert("Work history exported successfully!");
                Swal.fire({
                    icon: "success",
                    title: "Work history exported successfully!",
                    confirmButtonText: "Ok",
                });
            } catch (error) {
                console.error("Error exporting work history:", error);

                // Restore button state on error
                const exportButton = document.querySelector(
                    ".btn-export, .btn-primary",
                );
                if (exportButton) {
                    exportButton.innerHTML =
                        '<i class="fas fa-download"></i> Export Work History';
                    exportButton.disabled = false;
                }

                // Better error handling
                let errorMessage = "Failed to export work history. ";
                if (error.response) {
                    if (error.response.status === 404) {
                        errorMessage +=
                            "Export endpoint not found. Please check your routes.";
                    } else if (error.response.status === 500) {
                        errorMessage +=
                            "Server error occurred. Please check the logs.";
                    } else if (error.response.status === 422) {
                        errorMessage += "Invalid data provided.";
                        if (error.response.data && error.response.data.errors) {
                            errorMessage += "\n\nValidation errors:\n";
                            Object.keys(error.response.data.errors).forEach(
                                (key) => {
                                    errorMessage += `• ${key}: ${error.response.data.errors[
                                        key
                                    ].join(", ")}\n`;
                                },
                            );
                        }
                    } else {
                        errorMessage += `Server returned error ${error.response.status}.`;
                    }
                } else if (error.request) {
                    errorMessage +=
                        "No response from server. Please check your connection.";
                } else {
                    errorMessage += error.message || "Unknown error occurred.";
                }

                // alert(errorMessage);

                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: errorMessage,
                    confirmButtonText: "Ok",
                });
            }
        },

        // Helper methods for exact data display matching the screenshot
        getMainDate(orderInfo) {
            if (
                !orderInfo.datecreatedsheesh ||
                orderInfo.datecreatedsheesh === "N/A"
            ) {
                return "N/A";
            }
            try {
                const date = new Date(orderInfo.datecreatedsheesh);
                return date.toLocaleDateString("en-US", {
                    month: "2-digit",
                    day: "2-digit",
                    year: "numeric",
                });
            } catch (e) {
                return "N/A";
            }
        },

        getSubDate(orderInfo) {
            if (
                !orderInfo.purchaselabeldate ||
                orderInfo.purchaselabeldate === "N/A"
            ) {
                return "";
            }
            try {
                const date = new Date(orderInfo.purchaselabeldate);
                return date.toLocaleTimeString("en-US", {
                    hour: "2-digit",
                    minute: "2-digit",
                    hour12: true,
                });
            } catch (e) {
                return "";
            }
        },

        getCarrierClass(carrier) {
            if (!carrier || carrier === "N/A") {
                return "carrier-na";
            }
            const carrierUpper = carrier.toString().toUpperCase();
            if (carrierUpper.includes("UPS")) {
                return "carrier-ups";
            } else if (
                carrierUpper.includes("FEDEX") ||
                carrierUpper.includes("FEDX")
            ) {
                return "carrier-fedex";
            } else if (carrierUpper.includes("USPS")) {
                return "carrier-usps";
            } else if (carrierUpper.includes("DHL")) {
                return "carrier-dhl";
            }
            return "carrier-other";
        },

        getCarrierText(carrier) {
            if (!carrier || carrier === "N/A") {
                return "N/A";
            }
            const carrierUpper = carrier.toString().toUpperCase();
            if (carrierUpper.includes("UPS")) {
                return "UPS";
            } else if (
                carrierUpper.includes("FEDEX") ||
                carrierUpper.includes("FEDX")
            ) {
                return "FEDEX";
            } else if (carrierUpper.includes("USPS")) {
                return "USPS";
            } else if (carrierUpper.includes("DHL")) {
                return "DHL";
            }
            return carrier;
        },

        getDeliveryStatus(orderInfo) {
            if (
                !orderInfo.datedeliveredsheesh ||
                orderInfo.datedeliveredsheesh === "N/A"
            ) {
                return "N/A";
            }
            return "N/A"; // Based on screenshot, most show N/A
        },

        getDeliverySubDate(orderInfo) {
            return "N/A"; // Based on screenshot
        },

        getDispensedStatus(orderInfo) {
            return "N/A"; // Based on screenshot, most show N/A
        },

        getRemarks(orderInfo) {
            return "N/A"; // Based on screenshot, most show N/A
        },

        selectCarrier(order, rate) {
            this.selectedCarriers[order.platform_order_id] = rate;
            this.closeCarrierModal();
        },

        getRatesForOrder(orderId) {
            return this.rateResultsByOrderId?.[orderId] || [];
        },

        handleShipmentLabelSubmit(data) {
            console.log("Submitted shipment label data:", data);
            this.closeShipmentLabelModal();
        },
        //________________________________________________________________________________

        // Normalize store name for consistent comparison
        normalizeStoreName(storeName) {
            if (!storeName) return "";
            return storeName.toLowerCase().replace(/[\s\-_]+/g, "");
        },

        // Format date for display
        formatDate(dateStr) {
            if (!dateStr) return "N/A";
            const date = new Date(dateStr);
            return date.toLocaleString();
        },

        // Format address for display
        formatAddress(address, fullFormat = false) {
            if (!address) return "N/A";

            if (fullFormat) {
                return address.split(", ").join("\n");
            }

            return address;
        },

        // Get CSS class for status badges
        getStatusClass(status) {
            switch (status) {
                case "Pending":
                    return "status-badge status-pending";
                case "Unshipped":
                    return "status-badge status-pending";
                case "Shipped":
                    return "status-badge status-shipped";
                case "Canceled":
                    return "status-badge status-canceled";
                default:
                    return "status-badge";
            }
        },

        // HELPER METHODS FOR NULL CHECKING
        hasTrackingNumber(order) {
            return (
                order &&
                order.items &&
                Array.isArray(order.items) &&
                order.items.some((item) => item.tracking_number)
            );
        },

        getShipStatus(order) {
            if (!order || !order.items || !Array.isArray(order.items)) {
                return "Not Shipped";
            }
            return order.items.some((item) => item.tracking_number)
                ? "Shipped"
                : "Not Shipped";
        },

        getTrackingStatus(order) {
            if (!order || !order.items || !Array.isArray(order.items)) {
                return "Not Available";
            }
            const trackedItem = order.items.find(
                (item) => item.tracking_status,
            );
            return trackedItem ? trackedItem.tracking_status : "Not Available";
        },

        formatShipByDate(date) {
            if (!date) return "N/A";
            return this.formatDate(date);
        },

        formatDeliveryDate(date) {
            if (!date) return "N/A";
            return this.formatDate(date);
        },

        // Check if order has any dispensed items

        hasDispensedItems(order) {
            if (!order || !order.items || !Array.isArray(order.items)) {
                return false;
            }

            return order.items.some((item) => {
                return (
                    item.product_id ||
                    (item.dispensed_products &&
                        item.dispensed_products.length > 0) ||
                    (item.dispensed_count && item.dispensed_count > 0)
                );
            });
        },

        // Check if a specific item is dispensed
        isItemDispensed(item) {
            if (!item) return false;

            return (
                item.product_id ||
                (item.dispensed_products &&
                    item.dispensed_products.length > 0) ||
                (item.dispensed_count && item.dispensed_count > 0)
            );
        },

        // Get dispensed product count for an item
        getDispensedProductCount(item) {
            if (!item) return 0;

            if (item.dispensed_count !== undefined) {
                return item.dispensed_count;
            }

            if (
                item.dispensed_products &&
                Array.isArray(item.dispensed_products)
            ) {
                return item.dispensed_products.length;
            }

            return item.product_id ? 1 : 0;
        },

        // Get dispensed products details for display
        getDispensedProductsDisplay(item) {
            if (!this.isItemDispensed(item)) return [];

            if (
                item.dispensed_products &&
                Array.isArray(item.dispensed_products)
            ) {
                return item.dispensed_products;
            }

            if (item.product_id) {
                return [
                    {
                        product_id: item.product_id,
                        title: item.title || "N/A",
                        asin: item.asin || "N/A",
                        warehouseLocation: item.warehouseLocation || "",
                        serialNumber: item.serialNumber || "",
                        rtCounter: item.rtCounter || "",
                        FNSKU: item.FNSKU || "",
                    },
                ];
            }

            return [];
        },

        // Get condition display text
        getConditionDisplay(item) {
            if (!item) return "N/A";

            if (item.condition) return item.condition;
            if (item.ordered_condition) return item.ordered_condition;

            const conditionId = item.ConditionId || "";
            const subtypeId = item.ConditionSubtypeId || "";

            return `${conditionId}${subtypeId}`;
        },

        // Check if a product's condition is valid for the item's condition
        isConditionValid(itemCondition, productCondition, storeName) {
            const normalizedStore = this.normalizeStoreName(storeName);

            if (normalizedStore === "allrenewed") {
                const conditionHierarchy = {
                    "Refurbished - Excellent": 3,
                    "Refurbished - Good": 2,
                    "Refurbished - Acceptable": 1,
                };

                const itemRank = conditionHierarchy[itemCondition] || 0;
                const productRank = conditionHierarchy[productCondition] || 0;

                return productRank >= itemRank;
            }

            return itemCondition === productCondition;
        },

        // Format store-specific condition
        formatStoreSpecificCondition(
            conditionId,
            conditionSubtypeId,
            storeName,
        ) {
            const normalizedStore = this.normalizeStoreName(storeName);

            if (normalizedStore === "allrenewed") {
                // Only apply Refurbished mapping for New items
                if (conditionId === "New") {
                    const combinedCondition = conditionId + conditionSubtypeId;

                    switch (combinedCondition) {
                        case "NewNew":
                            return "Refurbished - Excellent";
                        case "NewGood":
                            return "Refurbished - Good";
                        case "NewAcceptable":
                            return "Refurbished - Acceptable";
                        default:
                            return combinedCondition;
                    }
                } else {
                    // ✅ NEW: For Used conditions, display as-is
                    if (conditionSubtypeId) {
                        return conditionId + " " + conditionSubtypeId;
                    }
                    return conditionId;
                }
            }

            // Default for other stores
            return conditionId + conditionSubtypeId;
        },

        // Open scanner modal method
        openScannerModal() {
            if (this.$refs.scanner) {
                this.$refs.scanner.openScannerModal();
            }
        },

        // Initialize dispenseItemsSelected on component mount

        initializeDispenseItems() {
            this.dispenseItemsSelected = [];

            this.orders.forEach((order) => {
                if (order.items) {
                    order.items.forEach((item) => {
                        if (this.isItemDispensed(item)) {
                            this.dispenseItemsSelected.push(
                                item.outboundorderitemid,
                            );
                        }
                    });
                }
            });

            // REMOVED: Auto-check functionality
            // We only want to auto-check when items are NEWLY dispensed
            // NOT on every page load/refresh
        },

        autoCheckOrderAfterDispense(orderId) {
            const orderIndex = this.orders.findIndex(
                (o) => o.outboundorderid === orderId,
            );
            if (
                orderIndex !== -1 &&
                this.hasDispensedItems(this.orders[orderIndex])
            ) {
                this.orders[orderIndex].checked = true;
                if (!this.persistentSelectedOrderIds.includes(orderId)) {
                    this.persistentSelectedOrderIds.push(orderId);
                }
                console.log(
                    `✅ Auto-checked order ${this.orders[orderIndex].platform_order_id} after dispensing`,
                );
            }
        },

        // Handle order checkbox change event
        handleOrderCheckChange(order) {
            if (!this.hasDispensedItems(order)) {
                order.checked = false;
                return;
            }

            if (order.checked) {
                if (
                    !this.persistentSelectedOrderIds.includes(
                        order.outboundorderid,
                    )
                ) {
                    this.persistentSelectedOrderIds.push(order.outboundorderid);
                }
            } else {
                this.persistentSelectedOrderIds =
                    this.persistentSelectedOrderIds.filter(
                        (id) => id !== order.outboundorderid,
                    );
            }
        },

        changeStatusFilter() {
            this.currentPage = 1;
            this.clearAllSelections();
            this.fetchOrders();
        },

        // ✅ ADD THIS NEW METHOD
        changeOrderBy() {
            this.currentPage = 1;
            this.clearAllSelections();
            this.fetchOrders();
        },

        // Fetch orders from the API with persistent selection
        async fetchOrders() {
            this.loading = true;

            try {
                console.log("Fetching orders with params:", {
                    search: this.searchQuery,
                    page: this.currentPage,
                    per_page: this.perPage,
                    store: this.selectedStore,
                    status: this.statusFilter,
                    sort_column: this.sortColumn,
                    sort_order: this.sortOrder,
                    order_by: this.orderByFilter,
                });

                const response = await axios.get(
                    `${API_BASE_URL}/api/fbm-orders`,
                    {
                        params: {
                            search: this.searchQuery,
                            page: this.currentPage,
                            per_page: this.perPage,
                            store: this.selectedStore,
                            status: this.statusFilter,
                            sort_column: this.sortColumn,
                            sort_order: this.sortOrder,
                            order_by: this.orderByFilter,
                        },
                        withCredentials: true,
                    },
                );

                console.log("API Response:", response);

                if (response.data && response.data.success) {
                    this.orders = (response.data.data || []).map((order) => {
                        const processedItems = Array.isArray(order.items)
                            ? order.items.map((item) => {
                                  return item;
                              })
                            : [];

                        // FIXED: Only check if ID exists in persistentSelectedOrderIds
                        // This prevents auto-checking on fresh page load
                        const isChecked =
                            this.persistentSelectedOrderIds.length > 0 &&
                            this.persistentSelectedOrderIds.includes(
                                order.outboundorderid,
                            );

                        return {
                            ...order,
                            checked: isChecked,
                            items: processedItems,
                        };
                    });

                    console.log(
                        "Processed orders with dispensed items:",
                        this.orders,
                    );

                    this.totalPages = response.data.last_page || 1;

                    this.initializeDispenseItems();
                } else {
                    console.error("Invalid response format:", response.data);
                    this.orders = [];
                    this.totalPages = 1;
                }
            } catch (error) {
                console.error("Error fetching orders:", error);
                this.orders = [];
                this.totalPages = 1;
            } finally {
                this.loading = false;
            }
        },

        // Fetch stores for dropdown
        async fetchStores() {
            try {
                console.log("Fetching stores");
                const response = await axios.get(
                    `${API_BASE_URL}/api/fbm-orders/stores`,
                    {
                        withCredentials: true,
                    },
                );
                console.log("Stores response:", response);
                this.stores = response.data || [];
            } catch (error) {
                console.error("Error fetching stores:", error);
                this.stores = [];
            }
        },

        // Change store filter and clear selections
        changeStore() {
            this.currentPage = 1;
            this.clearAllSelections();
            this.fetchOrders();
        },

        // Change status filter and clear selections
        changeStatusFilter() {
            this.currentPage = 1;
            this.clearAllSelections();
            this.fetchOrders();
        },

        // Refresh data
        refreshData() {
            this.fetchOrders();
        },

        // Pagination methods
        changePerPage() {
            this.currentPage = 1;
            this.fetchOrders();
        },

        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.fetchOrders();
            }
        },

        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.fetchOrders();
            }
        },

        // Toggle select all orders
        toggleAll() {
            const newValue = this.selectAll;

            this.orders.forEach((order) => {
                if (this.hasDispensedItems(order)) {
                    order.checked = newValue;
                    this.handleOrderCheckChange(order);
                } else {
                    order.checked = false;
                }
            });
        },

        clearAllSelections() {
            console.log("🧹 Clearing ALL selections (orders + items)");

            // 1️⃣ Clear order-level selection
            this.persistentSelectedOrderIds = [];
            this.selectAll = false;

            // 2️⃣ Clear item-level selection (THIS IS THE MISSING PART)
            this.dispenseItemsSelected = [];

            // 3️⃣ Reset checked state on orders
            this.orders = this.orders.map((order) => ({
                ...order,
                checked: false,
            }));

            // 4️⃣ Force Vue + DOM checkbox cleanup
            this.$nextTick(() => {
                // Uncheck ALL checkboxes (order + item)
                document
                    .querySelectorAll('input[type="checkbox"]')
                    .forEach((cb) => (cb.checked = false));

                // Remove PrimeVue / UI highlight classes
                document
                    .querySelectorAll(
                        ".p-checkbox-box, .p-checkbox, .p-highlight, .p-checked",
                    )
                    .forEach((el) =>
                        el.classList.remove(
                            "p-highlight",
                            "p-checked",
                            "p-focus",
                        ),
                    );

                console.log("✅ All selections cleared");
                console.log("Orders:", this.persistentSelectedOrderIds);
                console.log("Items:", this.dispenseItemsSelected);
            });
        },

        // Sorting method
        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortOrder = this.sortOrder === "asc" ? "desc" : "asc";
            } else {
                this.sortColumn = column;
                this.sortOrder = "asc";
            }

            this.fetchOrders();
        },

        // Open order details modal
        viewOrderDetails(order) {
            this.selectedOrder = order;
            this.showOrderDetailsModal = true;
        },

        // Close order details modal
        closeOrderDetailsModal() {
            this.showOrderDetailsModal = false;
            this.selectedOrder = null;
        },

        // Process modal functions
        openProcessModal(order) {
            this.currentProcessOrder = order;
            this.selectedItems =
                order && order.items && Array.isArray(order.items)
                    ? order.items.map((item) => item.outboundorderitemid)
                    : [];
            this.resetProcessData();
            this.showProcessModal = true;
        },

        // Open process modal from details
        openProcessModalFromDetails(order) {
            this.closeOrderDetailsModal();
            this.openProcessModal(order);
        },

        // Reset process form data
        resetProcessData() {
            this.processData = {
                shipmentType: "Standard",
                trackingNumber: "",
                notes: "",
            };
        },

        // Close process modal
        closeProcessModal() {
            this.showProcessModal = false;
            this.currentProcessOrder = null;
            this.selectedItems = [];
            this.processingAutoDispense = false;
        },

        // Submit process order
        async submitProcessOrder() {
            if (!this.isProcessFormValid) return;

            try {
                this.isProcessing = true;

                const processData = {
                    order_id: this.currentProcessOrder.outboundorderid,
                    item_ids: this.selectedItems,
                    shipment_type: this.processData.shipmentType,
                    tracking_number: this.processData.trackingNumber,
                    notes: this.processData.notes,
                };

                console.log("Processing order with data:", processData);

                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/process`,
                    processData,
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            )?.content,
                        },
                    },
                );

                console.log("Process response:", response);

                if (response.data && response.data.success) {
                    alert("Order processed successfully");
                    this.closeProcessModal();
                    this.fetchOrders();
                } else {
                    alert(
                        `Error: ${
                            response.data.message || "Failed to process order"
                        }`,
                    );
                }
            } catch (error) {
                console.error("Error processing order:", error);
                alert("Failed to process order. Please try again.");
            } finally {
                this.isProcessing = false;
            }
        },

        // Auto Dispense Functions
        autoDispense(order) {
            const itemsNeedingDispense = order.items.filter((item) => {
                const dispensedCount = this.getDispensedProductCount(item);
                return dispensedCount < item.quantity_ordered;
            });

            if (itemsNeedingDispense.length === 0) {
                alert("All items in this order are already fully dispensed.");
                return;
            }

            const itemIds = itemsNeedingDispense.map(
                (item) => item.outboundorderitemid,
            );

            let message = `Auto-dispense products for ${itemsNeedingDispense.length} item(s) in this order?\n\n`;
            message += "Items to dispense:\n";
            itemsNeedingDispense.forEach((item) => {
                const dispensedCount = this.getDispensedProductCount(item);
                const remaining = item.quantity_ordered - dispensedCount;
                message += `• ${item.platform_title} (${remaining} needed)\n`;
            });

            if (confirm(message)) {
                this.performStandaloneAutoDispense(
                    order.outboundorderid,
                    itemIds,
                );
            }
        },

        async performStandaloneAutoDispense(orderId, itemIds) {
    try {
        const requestData = {
            order_id: orderId,
            item_ids: itemIds,
        };

        console.log("🤖 Standalone auto dispense request:", requestData);

        const response = await axios.post(
            `${API_BASE_URL}/api/fbm-orders/auto-dispense`,
            requestData,
            {
                withCredentials: true,
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]'
                    )?.content,
                },
            }
        );

        // ✅ PASTE YOUR CODE HERE - REPLACE THE EXISTING ALERT
        if (response.data && response.data.success) {
            let message = `Smart auto-dispense completed!\n\n`;
            message += `📦 ${response.data.dispensed_count} singles dispensed\n`;
            
            if (response.data.packs_created > 0) {
                message += `🎁 ${response.data.packs_created} pack(s) auto-merged\n\n`;
                
                // Show merge details
                if (response.data.merge_details && response.data.merge_details.length > 0) {
                    message += `Pack Details:\n`;
                    response.data.merge_details.forEach(detail => {
                        message += `\n• ASIN ${detail.asin}: ${detail.packs_created} × ${detail.pack_size}-pack\n`;
                        message += `  Used ${detail.singles_used} singles\n`;
                        
                        // ✅ OPTIONAL: Show pack locations
                        if (detail.pack_locations && detail.pack_locations.length > 0) {
                            detail.pack_locations.forEach((loc, idx) => {
                                message += `  Pack #${idx + 1}: ${loc}\n`;
                            });
                        }
                    });
                }
            }
            
            alert(message);

            // COMPREHENSIVE REFRESH AFTER AUTO DISPENSE
            console.log("🔄 Starting comprehensive refresh after auto dispense for order:", orderId);

            // Step 1: Always refresh main orders list first
            console.log("📋 Refreshing main orders list...");
            await this.fetchOrders();

            // Step 2: Update details modal if open for this order
            if (this.selectedOrder && this.selectedOrder.outboundorderid === orderId) {
                console.log("📝 Updating details modal...");
                const updatedOrder = this.orders.find(
                    (o) => o.outboundorderid === orderId
                );
                if (updatedOrder) {
                    this.selectedOrder = { ...updatedOrder };
                    console.log("✅ Details modal updated with dispensed products");
                }
            }

            // Step 3: Update process modal if open for this order
            if (this.currentProcessOrder && this.currentProcessOrder.outboundorderid === orderId) {
                console.log("🔧 Updating process modal...");
                const updatedOrderFromList = this.orders.find(
                    (o) => o.outboundorderid === orderId
                );
                if (updatedOrderFromList) {
                    const wasChecked = this.currentProcessOrder.checked;
                    this.currentProcessOrder = {
                        ...updatedOrderFromList,
                        checked: wasChecked,
                    };

                    this.selectedItems = this.currentProcessOrder.items
                        ? this.currentProcessOrder.items.map(
                              (item) => item.outboundorderitemid
                          )
                        : [];

                    console.log("✅ Process modal updated with dispensed products");
                }
            }

            // Step 4: Update auto dispense modal if open for this order
            if (this.autoDispenseOrder && this.autoDispenseOrder.outboundorderid === orderId) {
                console.log("🤖 Updating auto dispense modal...");
                const updatedOrderFromList = this.orders.find(
                    (o) => o.outboundorderid === orderId
                );
                if (updatedOrderFromList) {
                    this.autoDispenseOrder = { ...updatedOrderFromList };
                    console.log("✅ Auto dispense modal updated");
                }
            }

            // Step 5: Reinitialize dispense items selection
            console.log("🔄 Reinitializing dispense items...");
            this.initializeDispenseItems();
            
            // Step 6: Check ONLY this order since items were just dispensed
            console.log("☑️ Checking order-level checkbox...");
            this.autoCheckOrderAfterDispense(orderId);

            // Step 7: Force Vue to update all components
            this.$nextTick(() => {
                this.$forceUpdate();
                console.log("✅ Vue components force updated after auto dispense");
            });

            console.log("🎉 Auto dispense refresh completed!");
        } else {
            alert(`Error in auto-dispensing: ${response.data.message || "Unknown error"}`);
        }
    } catch (error) {
        console.error("Error in standalone auto dispense:", error);
        await Swal.fire({
            icon: "error",
            title: "Operation Failed",
            text: "Failed to perform auto-dispensing. Please try again.",
            confirmButtonText: "Ok",
        });
    }
       },

        // More methods continue here...
        closeAutoDispenseModal() {
            this.showAutoDispenseModal = false;
            this.autoDispenseOrder = null;
            this.dispenseProducts = [];
            this.selectedDispenseProducts = {};
        },

        // FIXED: Enhanced cancel dispense with comprehensive refresh
        async cancelDispense(order) {
            if (!this.hasDispensedItems(order)) return;

            const result = await Swal.fire({
                title: "Are you sure?",
                text: "Are you sure you want to cancel dispense for this order?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Yes, cancel it",
                cancelButtonText: "No",
            });
            if (!result.isConfirmed) return;
            try {
                const itemIds = order.items
                    .filter((item) => this.isItemDispensed(item))
                    .map((item) => item.outboundorderitemid);

                if (itemIds.length === 0) return;

                console.log(
                    "🗑️ Canceling dispense for order:",
                    order.outboundorderid,
                    "items:",
                    itemIds,
                );

                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/cancel-dispense`,
                    {
                        order_id: order.outboundorderid,
                        item_ids: itemIds,
                    },
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            )?.content,
                        },
                    },
                );

                if (response.data && response.data.success) {
                    await Swal.fire({
                        icon: "success",
                        title: "Operation Success",
                        text: "Dispense canceled successfully",
                        confirmButtonText: "Ok",
                    });
                    // alert("Dispense canceled successfully");

                    // COMPREHENSIVE REFRESH STRATEGY
                    const orderId = order.outboundorderid;
                    console.log(
                        "🔄 Starting comprehensive refresh for order:",
                        orderId,
                    );

                    // Step 1: Always refresh main orders list first
                    console.log("📋 Refreshing main orders list...");
                    await this.fetchOrders();

                    // Step 2: Update process modal if open for this order
                    if (
                        this.currentProcessOrder &&
                        this.currentProcessOrder.outboundorderid === orderId
                    ) {
                        console.log("🔧 Updating process modal...");
                        const updatedOrderFromList = this.orders.find(
                            (o) => o.outboundorderid === orderId,
                        );
                        if (updatedOrderFromList) {
                            // Preserve modal state while updating data
                            const wasChecked = this.currentProcessOrder.checked;
                            this.currentProcessOrder = {
                                ...updatedOrderFromList,
                                checked: wasChecked,
                            };

                            // Update selected items
                            this.selectedItems = this.currentProcessOrder.items
                                ? this.currentProcessOrder.items.map(
                                      (item) => item.outboundorderitemid,
                                  )
                                : [];

                            console.log(
                                "✅ Process modal updated with fresh data",
                            );
                        }
                    }

                    // Step 3: Update details modal if open for this order
                    if (
                        this.selectedOrder &&
                        this.selectedOrder.outboundorderid === orderId
                    ) {
                        console.log("📝 Updating details modal...");
                        const updatedOrderFromList = this.orders.find(
                            (o) => o.outboundorderid === orderId,
                        );
                        if (updatedOrderFromList) {
                            this.selectedOrder = { ...updatedOrderFromList };
                            console.log(
                                "✅ Details modal updated with fresh data",
                            );
                        }
                    }

                    // Step 4: Update auto dispense modal if open for this order
                    if (
                        this.autoDispenseOrder &&
                        this.autoDispenseOrder.outboundorderid === orderId
                    ) {
                        console.log("🤖 Updating auto dispense modal...");
                        const updatedOrderFromList = this.orders.find(
                            (o) => o.outboundorderid === orderId,
                        );
                        if (updatedOrderFromList) {
                            this.autoDispenseOrder = {
                                ...updatedOrderFromList,
                            };
                            console.log(
                                "✅ Auto dispense modal updated with fresh data",
                            );
                        }
                    }

                    // Step 5: Reinitialize dispense items selection
                    console.log("🔄 Reinitializing dispense items...");
                    this.initializeDispenseItems();

                    // Step 6: Force Vue to update all components
                    this.$nextTick(() => {
                        this.$forceUpdate();
                        console.log("✅ Vue components force updated");
                    });

                    console.log("🎉 Comprehensive refresh completed!");
                } else {
                    alert(
                        `Error: ${
                            response.data.message || "Failed to cancel dispense"
                        }`,
                    );
                }
            } catch (error) {
                console.error("Error canceling dispense:", error);
                alert("Failed to cancel dispense. Please try again.");
            }
        },

        async cancelSingleDispensedProduct(productId, item) {
    try {
        const result = await Swal.fire({
            title: 'Cancel This Dispense?',
            html: `
                <div style="text-align:left;">
                    <p><strong>Product ID:</strong> ${productId}</p>
                    <p><strong>Item:</strong> ${item.platform_title}</p>
                    <p class="text-warning mt-2">This will remove this specific product from the order and make it available again.</p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, cancel it',
            confirmButtonColor: '#d33',
            cancelButtonText: 'No, keep it'
        });

        if (!result.isConfirmed) return;

        console.log('🗑️ Canceling single dispensed product:', {
            product_id: productId,
            item_id: item.outboundorderitemid,
            order_id: this.currentProcessOrder.outboundorderid
        });

        const response = await axios.post(
            `${API_BASE_URL}/api/fbm-orders/cancel-single-dispense`,
            {
                product_id: productId,
                item_id: item.outboundorderitemid,
                order_id: this.currentProcessOrder.outboundorderid
            },
            {
                withCredentials: true,
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]',
                    )?.content,
                },
            }
        );

        if (response.data && response.data.success) {
            await Swal.fire({
                icon: 'success',
                title: 'Dispense Canceled',
                text: 'Product dispense canceled successfully',
                confirmButtonText: 'Ok',
            });

            // COMPREHENSIVE REFRESH
            const orderId = this.currentProcessOrder.outboundorderid;
            console.log('🔄 Starting comprehensive refresh after single cancel dispense');

            // Step 1: Refresh main orders list
            await this.fetchOrders();

            // Step 2: Update process modal
            if (this.currentProcessOrder && this.currentProcessOrder.outboundorderid === orderId) {
                const updatedOrderFromList = this.orders.find(
                    (o) => o.outboundorderid === orderId
                );
                if (updatedOrderFromList) {
                    const wasChecked = this.currentProcessOrder.checked;
                    this.currentProcessOrder = {
                        ...updatedOrderFromList,
                        checked: wasChecked,
                    };

                    this.selectedItems = this.currentProcessOrder.items
                        ? this.currentProcessOrder.items.map(
                              (item) => item.outboundorderitemid
                          )
                        : [];
                }
            }

            // Step 3: Update details modal if open
            if (this.selectedOrder && this.selectedOrder.outboundorderid === orderId) {
                const updatedOrder = this.orders.find(
                    (o) => o.outboundorderid === orderId
                );
                if (updatedOrder) {
                    this.selectedOrder = { ...updatedOrder };
                }
            }

            // Step 4: Reinitialize dispense items
            this.initializeDispenseItems();

            // Step 5: Force Vue update
            this.$nextTick(() => {
                this.$forceUpdate();
            });

            console.log('✅ Single dispense cancel refresh completed');
        } else {
            await Swal.fire({
                icon: 'error',
                title: 'Operation Failed',
                text: response.data.message || 'Failed to cancel dispense',
                confirmButtonText: 'Ok',
            });
        }
    } catch (error) {
        console.error('Error canceling single dispense:', error);
        await Swal.fire({
            icon: 'error',
            title: 'Operation Failed',
            text: 'Failed to cancel dispense. Please try again.',
            confirmButtonText: 'Ok',
        });
    }
    },

        // Add remaining methods...
        async startAutoDispenseInProcess() {
            this.processingAutoDispense = true;
            this.dispenseProducts = [];
            this.selectedDispenseProducts = {};

            const itemsToDispense = this.currentProcessOrder.items
                .filter((item) => {
                    const dispensedCount = this.getDispensedProductCount(item);
                    return dispensedCount < item.quantity_ordered;
                })
                .map((item) => item.outboundorderitemid);

            if (itemsToDispense.length === 0) {
                alert("All items are already fully dispensed.");
                this.processingAutoDispense = false;
                return;
            }

            await this.loadAndAutoDispenseProducts(itemsToDispense);
        },

        cancelAutoDispenseProcess() {
            this.processingAutoDispense = false;
            this.dispenseProducts = [];
            this.selectedDispenseProducts = {};
        },

        selectDispenseProduct(itemId, slotIndex, product) {
            const key = `${itemId}-${slotIndex}`;
            const updatedSelection = { ...this.selectedDispenseProducts };

            if (
                updatedSelection[key] &&
                updatedSelection[key].ProductID === product.ProductID
            ) {
                delete updatedSelection[key];
            } else {
                updatedSelection[key] = product;
            }

            this.selectedDispenseProducts = updatedSelection;
        },

       async confirmAutoDispenseInProcess() {
            if (Object.keys(this.selectedDispenseProducts).length === 0) return;

            try {
                const dispenseItems = Object.entries(this.selectedDispenseProducts).map(
                    ([key, product]) => {
                        const itemId = parseInt(key.split("-")[0]);
                        return {
                            item_id: itemId,
                            product_id: product.ProductID,
                        };
                    }
                );

                console.log("🔧 Confirming auto dispense in process modal:", dispenseItems);

                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/dispense`,
                    {
                        order_id: this.currentProcessOrder.outboundorderid,
                        dispense_items: dispenseItems,
                    },
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]'
                            )?.content,
                        },
                    }
                );

                // ✅ PASTE YOUR CODE HERE - REPLACE THE EXISTING ALERT
                if (response.data && response.data.success) {
                    let message = `Smart auto-dispense completed!\n\n`;
                    message += `📦 ${response.data.dispensed_count || dispenseItems.length} singles dispensed\n`;
                    
                    if (response.data.packs_created > 0) {
                        message += `🎁 ${response.data.packs_created} pack(s) auto-merged\n\n`;
                        
                        // Show merge details
                        if (response.data.merge_details && response.data.merge_details.length > 0) {
                            message += `Pack Details:\n`;
                            response.data.merge_details.forEach(detail => {
                                message += `\n• ASIN ${detail.asin}: ${detail.packs_created} × ${detail.pack_size}-pack\n`;
                                message += `  Used ${detail.singles_used} singles\n`;
                                
                                // ✅ OPTIONAL: Show pack locations
                                if (detail.pack_locations && detail.pack_locations.length > 0) {
                                    detail.pack_locations.forEach((loc, idx) => {
                                        message += `  Pack #${idx + 1}: ${loc}\n`;
                                    });
                                }
                            });
                        }
                    }
                    
                    alert(message);

                    // IMMEDIATE STATE CLEANUP
                    this.processingAutoDispense = false;
                    this.dispenseProducts = [];
                    this.selectedDispenseProducts = {};

                    // COMPREHENSIVE REFRESH FOR PROCESS MODAL
                    const orderId = this.currentProcessOrder.outboundorderid;
                    console.log("🔄 Starting comprehensive refresh for process modal, order:", orderId);

                    // Step 1: Refresh main orders list to get latest data
                    console.log("📋 Refreshing main orders list...");
                    await this.fetchOrders();

                    // Step 2: Update process modal with fresh data from main list
                    console.log("🔧 Updating process modal with fresh data...");
                    const updatedOrderFromList = this.orders.find(
                        (o) => o.outboundorderid === orderId
                    );
                    if (updatedOrderFromList) {
                        const wasChecked = this.currentProcessOrder.checked;
                        this.currentProcessOrder = {
                            ...updatedOrderFromList,
                            checked: wasChecked,
                        };

                        this.selectedItems = this.currentProcessOrder.items
                            ? this.currentProcessOrder.items.map(
                                (item) => item.outboundorderitemid
                            )
                            : [];

                        console.log("✅ Process modal updated with dispensed products");
                    } else {
                        console.error("❌ Could not find updated order in main list");
                    }

                    // Step 3: Update details modal if open for same order
                    if (this.selectedOrder && this.selectedOrder.outboundorderid === orderId) {
                        console.log("📝 Updating details modal...");
                        this.selectedOrder = { ...this.currentProcessOrder };
                        console.log("✅ Details modal updated");
                    }

                    // Step 4: Reinitialize dispense items selection
                    console.log("🔄 Reinitializing dispense items...");
                    this.initializeDispenseItems();
                    
                    // Step 5: Check ONLY this order since items were just dispensed
                    console.log("☑️ Checking order-level checkbox...");
                    this.autoCheckOrderAfterDispense(orderId);

                    // Step 6: Force Vue reactivity update
                    this.$nextTick(() => {
                        this.$forceUpdate();
                        console.log("✅ Vue components force updated in process modal");
                    });

                    console.log("🎉 Process modal refresh completed!");
                } else {
                    alert(`Error: ${response.data.message || "Failed to dispense items"}`);
                }
            } catch (error) {
                console.error("Error confirming dispense:", error);
                alert("Failed to dispense items. Please try again.");
            }
        },

        // CRITICAL: Enhanced modal refresh method
        async refreshCurrentProcessOrderForModal() {
            if (!this.currentProcessOrder) return;

            try {
                console.log(
                    "🔄 Refreshing process modal content for order:",
                    this.currentProcessOrder.outboundorderid,
                );

                // Method 1: Try to get fresh data from detail endpoint
                try {
                    const response = await axios.get(
                        `${API_BASE_URL}/api/fbm-orders/detail`,
                        {
                            params: {
                                order_id:
                                    this.currentProcessOrder.outboundorderid,
                            },
                            withCredentials: true,
                        },
                    );

                    if (response.data && response.data.success) {
                        const updatedOrder = response.data.data;

                        // Update the current process order with fresh data
                        this.currentProcessOrder = {
                            ...updatedOrder,
                            checked: this.currentProcessOrder.checked || false,
                        };

                        console.log(
                            "✅ Process modal refreshed via detail endpoint",
                        );
                    } else {
                        throw new Error("Detail endpoint failed");
                    }
                } catch (detailError) {
                    console.log(
                        "⚠️ Detail endpoint failed, trying main orders refresh...",
                    );

                    // Method 2: Fallback to main orders refresh
                    await this.fetchOrders();

                    const updatedOrder = this.orders.find(
                        (o) =>
                            o.outboundorderid ===
                            this.currentProcessOrder.outboundorderid,
                    );

                    if (updatedOrder) {
                        this.currentProcessOrder = {
                            ...updatedOrder,
                            checked: this.currentProcessOrder.checked || false,
                        };
                        console.log(
                            "✅ Process modal refreshed via main orders",
                        );
                    } else {
                        console.error("❌ Could not find updated order");
                    }
                }

                // Reset selectedItems to include all items
                this.selectedItems = this.currentProcessOrder.items
                    ? this.currentProcessOrder.items.map(
                          (item) => item.outboundorderitemid,
                      )
                    : [];

                // Update dispense items selection
                this.initializeDispenseItems();

                // If details modal is open for this order, update it too
                if (
                    this.selectedOrder &&
                    this.selectedOrder.outboundorderid ===
                        this.currentProcessOrder.outboundorderid
                ) {
                    this.selectedOrder = { ...this.currentProcessOrder };
                }

                // Force Vue reactivity update
                this.$nextTick(() => {
                    this.$forceUpdate();
                });

                console.log("✅ Process modal content refresh completed");
            } catch (error) {
                console.error(
                    "❌ Error refreshing process modal content:",
                    error,
                );
            }
        },

        processSelectedOrders() {
            const selectedOrderIds = this.persistentSelectedOrderIds;

            if (selectedOrderIds.length === 0) {
                // alert("Please select at least one order to process");
                Swal.fire({
                    icon: "warning",
                    title: "Ooops",
                    text: "Please select at least one order to process",
                    confirmButtonText: "Ok",
                });
                return;
            }

            const visibleSelectedOrder = this.orders.find((order) =>
                selectedOrderIds.includes(order.outboundorderid),
            );

            if (visibleSelectedOrder) {
                this.openProcessModal(visibleSelectedOrder);
            } else {
                this.fetchSelectedOrderForProcessing(selectedOrderIds[0]);
            }
        },

        async fetchSelectedOrderForProcessing(orderId) {
            try {
                this.loading = true;

                const response = await axios.get(
                    `${API_BASE_URL}/api/fbm-orders/detail`,
                    {
                        params: { order_id: orderId },
                        withCredentials: true,
                    },
                );

                if (response.data && response.data.success) {
                    const order = response.data.data;
                    const processedOrder = {
                        ...order,
                        checked: true,
                    };
                    this.openProcessModal(processedOrder);
                } else {
                    alert(
                        "Could not fetch the selected order. Please try again.",
                    );
                }
            } catch (error) {
                console.error("Error fetching order for processing:", error);
                alert("Error fetching the selected order. Please try again.");
            } finally {
                this.loading = false;
            }
        },

        async generatePackingSlip(orderId) {
            try {
                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/packing-slip`,
                    { order_id: orderId },
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            )?.content,
                        },
                    },
                );

                if (response.data && response.data.success) {
                    alert("Packing slip generated successfully");
                    if (response.data.pdf_url) {
                        window.open(response.data.pdf_url, "_blank");
                    }
                } else {
                    alert(
                        `Error: ${
                            response.data.message ||
                            "Failed to generate packing slip"
                        }`,
                    );
                }
            } catch (error) {
                console.error("Error generating packing slip:", error);
                alert("Failed to generate packing slip. Please try again.");
            }
        },

        confirmCancelOrder(orderId) {
            if (confirm("Are you sure you want to cancel this order?")) {
                this.cancelOrder(orderId);
            }
        },

        async cancelOrder(orderId) {
            try {
                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/cancel`,
                    { order_id: orderId },
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            )?.content,
                        },
                    },
                );

                if (response.data && response.data.success) {
                    alert("Order canceled successfully");
                    this.closeOrderDetailsModal();
                    this.fetchOrders();
                } else {
                    alert(
                        `Error: ${
                            response.data.message || "Failed to cancel order"
                        }`,
                    );
                }
            } catch (error) {
                console.error("Error canceling order:", error);
                alert("Failed to cancel order. Please try again.");
            }
        },

        async markProductNotFound(productId, item) {
            if (
                !confirm(
                    `Mark this product as "Not Found" and automatically select a replacement?\n\nThis will:\n1. Mark the current product as not found\n2. Remove it from this order\n3. Automatically select a new product if available`,
                )
            ) {
                return;
            }

            try {
                let orderId = this.getCurrentOrderId();

                if (!orderId && item && item.outboundorderitemid) {
                    for (const order of this.orders) {
                        if (
                            order.items &&
                            order.items.some(
                                (orderItem) =>
                                    orderItem.outboundorderitemid ===
                                    item.outboundorderitemid,
                            )
                        ) {
                            orderId = order.outboundorderid;
                            break;
                        }
                    }
                }

                if (!orderId) {
                    alert("Unable to determine order ID. Please try again.");
                    return;
                }

                const requestData = {
                    product_id: productId,
                    item_id: item.outboundorderitemid,
                    order_id: orderId,
                };

                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/mark-not-found`,
                    requestData,
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            )?.content,
                        },
                    },
                );

                // if (response.data && response.data.success) {
                //     let message = 'Product marked as "Not Found" successfully.';

                //     if (response.data.replacement_found) {
                //         message += `\n\nReplacement product automatically selected:\n• ${response.data.replacement_details.title}\n• Location: ${response.data.replacement_details.warehouseLocation}`;
                //     } else {
                //         message +=
                //             "\n\nNo replacement product was found in inventory.";
                //     }

                //     alert(message);
                //     await this.refreshCurrentContext();
                // }

                if (response.data && response.data.success) {
                    const hasReplacement = response.data.replacement_found;

                    let htmlMessage = `
                            <div style="text-align:left;">
                                Product marked as <strong>"Not Found"</strong> successfully.<br><br>
                        `;

                    if (hasReplacement) {
                        htmlMessage += `
                                <strong>Replacement product automatically selected:</strong><br>
                                • ${response.data.replacement_details.title}<br>
                                • Location: ${response.data.replacement_details.warehouseLocation}<br>
                            `;
                    } else {
                        htmlMessage += `
                                <strong>No replacement product was found in inventory.</strong><br>
                            `;
                    }

                    htmlMessage += `</div>`;

                    Swal.fire({
                        icon: hasReplacement ? "success" : "warning",
                        title: hasReplacement
                            ? "Replacement Found"
                            : "No Replacement Found",
                        html: htmlMessage,
                        confirmButtonText: "OK",
                    }).then(() => {
                        this.refreshCurrentContext();
                    });
                } else {
                    // alert(
                    //     `Error: ${
                    //         response.data.message ||
                    //         "Failed to mark product as not found"
                    //     }`
                    // );
                    Swal.fire({
                        icon: "error",
                        title: "Operation Failed",
                        text: `${
                            response.data.message ||
                            "Failed to mark product as not found"
                        }`,
                        confirmButtonText: "Ok",
                    });
                }
            } catch (error) {
                console.error("Error marking product as not found:", error);
                // alert("Failed to mark product as not found. Please try again.");
                Swal.fire({
                    icon: "error",
                    title: "Operation Failed",
                    text: "Failed to mark product as not found. Please try again.",
                    confirmButtonText: "Ok",
                });
            }
        },

        getCurrentOrderId() {
            if (this.currentProcessOrder) {
                return this.currentProcessOrder.outboundorderid;
            } else if (this.selectedOrder) {
                return this.selectedOrder.outboundorderid;
            } else if (this.autoDispenseOrder) {
                return this.autoDispenseOrder.outboundorderid;
            }
            return null;
        },

        async refreshCurrentContext() {
            try {
                if (this.currentProcessOrder) {
                    await this.refreshCurrentProcessOrderForModal();
                } else if (this.selectedOrder) {
                    const orderId = this.selectedOrder.outboundorderid;
                    await this.fetchOrders();
                    const updatedOrder = this.orders.find(
                        (o) => o.outboundorderid === orderId,
                    );
                    if (updatedOrder) {
                        this.selectedOrder = { ...updatedOrder };
                    }
                } else {
                    await this.fetchOrders();
                }
                //this.initializeDispenseItems();
            } catch (error) {
                console.error("Error refreshing context:", error);
            }
        },

        async printShippingLabels() {
            const selectedOrderIds = this.persistentSelectedOrderIds || [];
            if (!selectedOrderIds.length) {
                Swal.fire({
                    icon: "warning",
                    title: "Ooops",
                    text: "Please select at least one order.",
                    confirmButtonText: "Ok",
                });
                return;
            }

            Swal.fire({
                title: "Generating labels…",
                didOpen: () => Swal.showLoading(),
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
            });

            const success = [];
            const failed = [];

            try {
                for (const id of selectedOrderIds) {
                    try {
                        const ok = await this.printShippingLabelSilent(id); // silent version below
                        if (ok?.label_url) success.push(ok.label_url);
                        else failed.push(id);
                    } catch (e) {
                        failed.push(id);
                    }
                }

                Swal.close();

                if (!success.length) {
                    Swal.fire({
                        icon: "warning",
                        title: "No labels printed",
                        text: "None of the selected orders had labels available.",
                        confirmButtonText: "Ok",
                    });
                    return;
                }

                // open labels
                success.forEach((url) => window.open(url, "_blank"));

                if (failed.length) {
                    Swal.fire({
                        icon: "info",
                        title: "Some failed",
                        text: `Printed ${success.length}. Failed ${failed.length}. Check console for details.`,
                        confirmButtonText: "Ok",
                    });
                } else {
                    Swal.fire({
                        icon: "success",
                        title: "Success",
                        text: `Printed ${success.length} label(s).`,
                        confirmButtonText: "Ok",
                    });
                }
            } catch (err) {
                console.error(err);
                Swal.close();
                Swal.fire({
                    icon: "error",
                    title: "Operation Failed",
                    text: "Failed to print labels.",
                    confirmButtonText: "Ok",
                });
            }
        },

        async printShippingLabelSilent(platformOrderId) {
            const res = await axios.post(
                "/fbm-orders-shippinglabel",
                {
                    platform_order_ids: [platformOrderId],
                    action: "ViewShipmentLabel", // important: we want PDF URL, not actual print
                    note: "", // optional
                    settings: { testPrint: false }, // optional; your controller ignores most settings anyway
                },
                {
                    withCredentials: true,
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        "X-CSRF-TOKEN": document.querySelector(
                            'meta[name="csrf-token"]',
                        )?.content,
                    },
                },
            );

            // Your controller returns: { success: true, results: [{ order_id, pdf_url, zpl_preview }] }
            const row = res.data?.results?.[0];

            if (res.data?.success && row?.pdf_url) {
                return { label_url: row.pdf_url };
            }

            return null;
        },

        async printShippingLabel(orderId) {
            try {
                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/shipping-label`,
                    { order_id: orderId },
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            )?.content,
                        },
                    },
                );

                if (response.data && response.data.success) {
                    // alert("Shipping label generated successfully");
                    await Swal.fire({
                        icon: "success",
                        title: "Operation Success",
                        text: "Shipping label generated successfully",
                        confirmButtonText: "Ok",
                    });
                    if (response.data.label_url) {
                        window.open(response.data.label_url, "_blank");
                    }
                } else {
                    // alert(
                    //     `Error: ${
                    //         response.data.message ||
                    //         "Failed to generate shipping label"
                    //     }`
                    // );
                    Swal.fire({
                        icon: "error",
                        title: "Operation Failed",
                        text: `${
                            response.data.message ||
                            "Failed to generate shipping label"
                        }`,
                        confirmButtonText: "Ok",
                    });
                }
            } catch (error) {
                console.error("Error generating shipping label:", error);
                // alert("Failed to generate shipping label. Please try again.");
                await Swal.fire({
                    icon: "success",
                    title: "Operation Failed",
                    text: "Failed to generate shipping label. Please try again.",
                    confirmButtonText: "Ok",
                });
            }
        },

        generatePackingSlips() {
            const selectedOrderIds = this.persistentSelectedOrderIds;
            if (selectedOrderIds.length === 0) {
                // alert(
                //     "Please select at least one order to generate packing slips"
                // );
                Swal.fire({
                    icon: "warning",
                    title: "Ooops",
                    text: "Please select at least one order to generate packing slips",
                    confirmButtonText: "Ok",
                });
                return;
            }
            selectedOrderIds.forEach((id) => this.generatePackingSlip(id));
        },

        // Additional missing methods from old code:

        async loadMatchingProducts() {
            if (!this.autoDispenseOrder) return;

            this.loadingDispenseProducts = true;

            try {
                const itemIds = this.autoDispenseOrder.items
                    .filter((item) => {
                        const dispensedCount =
                            this.getDispensedProductCount(item);
                        return dispensedCount < item.quantity_ordered;
                    })
                    .map((item) => item.outboundorderitemid);

                if (itemIds.length === 0) {
                    this.dispenseProducts = [];
                    this.loadingDispenseProducts = false;
                    return;
                }

                const requestData = {
                    order_id: this.autoDispenseOrder.outboundorderid,
                    item_ids: itemIds,
                };

                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/find-dispense-products`,
                    requestData,
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            )?.content,
                        },
                    },
                );

                if (response.data && response.data.success) {
                    this.dispenseProducts = response.data.data || [];

                    this.dispenseProducts.forEach((item) => {
                        if (
                            item.matching_products &&
                            item.matching_products.length > 0 &&
                            item.quantity_remaining > 0
                        ) {
                            const neededCount = Math.min(
                                item.quantity_remaining,
                                item.matching_products.length,
                            );

                            for (let i = 0; i < neededCount; i++) {
                                const product = item.matching_products[i];
                                const key = `${item.item_id}-${i}`;
                                this.selectedDispenseProducts[key] = product;
                            }
                        }
                    });
                } else {
                    this.dispenseProducts = [];
                }
            } catch (error) {
                console.error("Error loading matching products:", error);
                this.dispenseProducts = [];
            } finally {
                this.loadingDispenseProducts = false;
            }
        },

        async confirmAutoDispense() {
            if (Object.keys(this.selectedDispenseProducts).length === 0) return;

            try {
                const dispenseItems = Object.entries(
                    this.selectedDispenseProducts,
                ).map(([key, product]) => {
                    const itemId = parseInt(key.split("-")[0]);
                    return {
                        item_id: itemId,
                        product_id: product.ProductID,
                    };
                });

                console.log(
                    "🤖 Confirming auto dispense in standalone modal:",
                    dispenseItems,
                );

                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/dispense`,
                    {
                        order_id: this.autoDispenseOrder.outboundorderid,
                        dispense_items: dispenseItems,
                    },
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            )?.content,
                        },
                    },
                );

                if (response.data && response.data.success) {
                    // alert("Items dispensed successfully");
                    await Swal.fire({
                        icon: "success",
                        title: "Operation Successful",
                        text: "Items dispensed successfully",
                        confirmButtonText: "Ok",
                    });

                    // Close the auto dispense modal first
                    this.closeAutoDispenseModal();

                    // COMPREHENSIVE REFRESH AFTER STANDALONE AUTO DISPENSE
                    const orderId = this.autoDispenseOrder.outboundorderid;
                    console.log(
                        "🔄 Starting comprehensive refresh after standalone auto dispense for order:",
                        orderId,
                    );

                    // Step 1: Always refresh main orders list first
                    console.log("📋 Refreshing main orders list...");
                    await this.fetchOrders();

                    // Step 2: Update details modal if open for this order
                    if (
                        this.selectedOrder &&
                        this.selectedOrder.outboundorderid === orderId
                    ) {
                        console.log("📝 Updating details modal...");
                        const updatedOrder = this.orders.find(
                            (o) => o.outboundorderid === orderId,
                        );
                        if (updatedOrder) {
                            this.selectedOrder = { ...updatedOrder };
                            console.log(
                                "✅ Details modal updated with dispensed products",
                            );
                        }
                    }

                    // Step 3: Update process modal if open for this order
                    if (
                        this.currentProcessOrder &&
                        this.currentProcessOrder.outboundorderid === orderId
                    ) {
                        console.log("🔧 Updating process modal...");
                        const updatedOrderFromList = this.orders.find(
                            (o) => o.outboundorderid === orderId,
                        );
                        if (updatedOrderFromList) {
                            const wasChecked = this.currentProcessOrder.checked;
                            this.currentProcessOrder = {
                                ...updatedOrderFromList,
                                checked: wasChecked,
                            };

                            this.selectedItems = this.currentProcessOrder.items
                                ? this.currentProcessOrder.items.map(
                                      (item) => item.outboundorderitemid,
                                  )
                                : [];

                            console.log(
                                "✅ Process modal updated with dispensed products",
                            );
                        }
                    }

                    // Step 4: Reinitialize dispense items selection
                    console.log("🔄 Reinitializing dispense items...");
                    this.initializeDispenseItems();

                    // Step 5: Force Vue to update all components
                    this.$nextTick(() => {
                        this.$forceUpdate();
                        console.log(
                            "✅ Vue components force updated after standalone auto dispense",
                        );
                    });

                    console.log(
                        "🎉 Standalone auto dispense refresh completed!",
                    );
                } else {
                    alert(
                        `Error: ${
                            response.data.message || "Failed to dispense items"
                        }`,
                    );
                }
            } catch (error) {
                console.error("Error confirming dispense:", error);
                // alert("Failed to dispense items. Please try again.");
                await Swal.fire({
                    icon: "error",
                    title: "Dispense Failed",
                    text: "Failed to dispense items. Please try again.",
                    confirmButtonText: "Ok",
                });
            }
        },

        getDispenseCount(item) {
            if (!item) return "";
            const dispensed = this.getDispensedProductCount(item);
            const ordered = item.quantity_ordered || 0;
            return `${dispensed}/${ordered}`;
        },

        async loadAndAutoDispenseProducts(itemIds) {
            this.loadingDispenseProducts = true;

            try {
                const requestData = {
                    order_id: this.currentProcessOrder.outboundorderid,
                    item_ids: itemIds,
                };

                const findResponse = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/find-dispense-products`,
                    requestData,
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            )?.content,
                        },
                    },
                );

                if (
                    findResponse.data &&
                    findResponse.data.success &&
                    findResponse.data.data.length > 0
                ) {
                    const dispenseData = findResponse.data.data;

                    let dispenseMessage =
                        "The following products will be auto-dispensed:\n\n";
                    let totalItemsToDispense = 0;

                    let htmlMessage = `
                        <div style="text-align:left">
                            <strong>The following products will be auto-dispensed:</strong><br><br>
                    `;

                    // Build the message
                    dispenseData.forEach((item) => {
                        if (
                            item.auto_selected_products &&
                            item.auto_selected_products.length > 0
                        ) {
                            htmlMessage += `
                                <strong>${item.ordered_item.platform_title}</strong><br>
                                &nbsp;&nbsp;• Quantity needed: ${item.quantity_remaining}<br>
                                &nbsp;&nbsp;• Products selected: ${item.auto_selected_products.length}<br>
                            `;

                            item.auto_selected_products.forEach((product) => {
                                htmlMessage += `
                                    &nbsp;&nbsp;&nbsp;&nbsp;◦ Product ID: ${
                                        product.ProductID
                                    } (${
                                        product.warehouseLocation ||
                                        "No location"
                                    })<br>
                                `;
                                totalItemsToDispense++;
                            });

                            htmlMessage += `<br>`;
                        }
                    });

                    htmlMessage += `
                        <strong>Total products to dispense:</strong> ${totalItemsToDispense}<br><br>
                        Proceed with auto-dispensing?
                        </div>
                    `;

                    if (totalItemsToDispense > 0) {
                        // SHOW SWEET ALERT
                        const result = await Swal.fire({
                            title: "Auto-Dispense Confirmation",
                            html: htmlMessage,
                            icon: "question",
                            showCancelButton: true,
                            confirmButtonText: "Yes, proceed",
                            cancelButtonText: "Cancel",
                        });
                        if (result.isConfirmed) {
                            await this.performAutoDispense(itemIds);
                        } else {
                            this.processingAutoDispense = false;
                            return;
                        }
                    }

                    if (totalItemsToDispense === 0) {
                        await Swal.fire({
                            icon: "error",
                            title: "Dispense Failed",
                            text: "No products available for auto-dispensing at this time.",
                            confirmButtonText: "OK",
                        });
                        // alert(
                        //     "No products available for auto-dispensing at this time."
                        // );
                        this.processingAutoDispense = false;
                        this.loadingDispenseProducts = false;
                        return;
                    }

                    // if (confirm(dispenseMessage)) {
                    //     await this.performAutoDispense(itemIds);
                    // } else {
                    //     this.processingAutoDispense = false;
                    // }
                } else {
                    alert(
                        "No matching products found in inventory for auto-dispensing.",
                    );
                    this.processingAutoDispense = false;
                }
            } catch (error) {
                console.error("Error in auto dispense:", error);
                alert(
                    "Error finding products for auto-dispensing. Please try again.",
                );
                this.processingAutoDispense = false;
            } finally {
                this.loadingDispenseProducts = false;
            }
        },

       async performAutoDispense(itemIds) {
    try {
        const requestData = {
            order_id: this.currentProcessOrder.outboundorderid,
            item_ids: itemIds,
        };

        console.log("🤖 Performing auto dispense in process modal:", requestData);

        const response = await axios.post(
            `${API_BASE_URL}/api/fbm-orders/auto-dispense`,
            requestData,
            {
                withCredentials: true,
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]'
                    )?.content,
                },
            }
        );

        if (response.data && response.data.success) {
            // ✅ Enhanced success message with pack merge details
            let message = `Smart auto-dispense completed!\n\n`;
            message += `📦 ${response.data.dispensed_count} singles dispensed\n`;
            
            if (response.data.packs_created && response.data.packs_created > 0) {
                message += `🎁 ${response.data.packs_created} pack(s) auto-merged\n\n`;
                
                // Show detailed merge information
                if (response.data.merge_details && response.data.merge_details.length > 0) {
                    message += `Pack Details:\n`;
                    response.data.merge_details.forEach(detail => {
                        message += `\n• ASIN ${detail.asin}: ${detail.packs_created} × ${detail.pack_size}-pack\n`;
                        message += `  Used ${detail.singles_used} singles\n`;
                        
                        // Show pack locations if available
                        if (detail.pack_locations && detail.pack_locations.length > 0) {
                            detail.pack_locations.forEach((loc, idx) => {
                                message += `  Pack #${idx + 1}: ${loc}\n`;
                            });
                        }
                    });
                }
            }
            
            alert(message);

            // IMMEDIATE STATE CLEANUP
            this.processingAutoDispense = false;
            this.dispenseProducts = [];
            this.selectedDispenseProducts = {};

            // COMPREHENSIVE REFRESH FOR PROCESS MODAL
            const orderId = this.currentProcessOrder.outboundorderid;
            console.log("🔄 Starting comprehensive refresh after auto dispense in process modal, order:", orderId);

            // Step 1: Refresh main orders list to get latest data
            console.log("📋 Refreshing main orders list...");
            await this.fetchOrders();

            // Step 2: Update process modal with fresh data from main list
            console.log("🔧 Updating process modal with fresh data...");
            const updatedOrderFromList = this.orders.find(
                (o) => o.outboundorderid === orderId
            );
            if (updatedOrderFromList) {
                const wasChecked = this.currentProcessOrder.checked;
                this.currentProcessOrder = {
                    ...updatedOrderFromList,
                    checked: wasChecked,
                };

                this.selectedItems = this.currentProcessOrder.items
                    ? this.currentProcessOrder.items.map(
                          (item) => item.outboundorderitemid
                      )
                    : [];

                console.log("✅ Process modal updated with auto-dispensed products");
            } else {
                console.error("❌ Could not find updated order in main list");
            }

            // Step 3: Update details modal if open for same order
            if (this.selectedOrder && this.selectedOrder.outboundorderid === orderId) {
                console.log("📝 Updating details modal...");
                this.selectedOrder = { ...this.currentProcessOrder };
                console.log("✅ Details modal updated");
            }

            // Step 4: Reinitialize dispense items selection
            console.log("🔄 Reinitializing dispense items...");
            this.initializeDispenseItems();
            
            // Step 5: Check ONLY this order since items were just dispensed
            console.log("☑️ Checking order-level checkbox...");
            this.autoCheckOrderAfterDispense(orderId);

            // Step 6: Force Vue reactivity update
            this.$nextTick(() => {
                this.$forceUpdate();
                console.log("✅ Vue components force updated after auto dispense in process modal");
            });

            console.log("🎉 Auto dispense in process modal refresh completed!");
        } else {
            alert(`Error in auto-dispensing: ${response.data.message || "Unknown error"}`);
            this.processingAutoDispense = false;
        }
    } catch (error) {
        console.error("Error performing auto dispense:", error);
        alert("Failed to perform auto-dispensing. Please try again.");
        this.processingAutoDispense = false;
    }
      },

        async loadDispenseProductsForProcess(itemIds) {
            this.loadingDispenseProducts = true;

            try {
                const requestData = {
                    order_id: this.currentProcessOrder.outboundorderid,
                    item_ids: itemIds,
                };

                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/find-dispense-products`,
                    requestData,
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            )?.content,
                        },
                    },
                );

                if (response.data && response.data.success) {
                    this.dispenseProducts = response.data.data || [];
                    this.selectedDispenseProducts = {};

                    this.dispenseProducts.forEach((item) => {
                        if (
                            item.matching_products &&
                            item.matching_products.length > 0
                        ) {
                            const availableProducts = Math.min(
                                item.quantity_remaining,
                                item.matching_products.length,
                            );

                            for (let i = 0; i < availableProducts; i++) {
                                const key = `${item.item_id}-${i}`;
                                this.selectedDispenseProducts[key] =
                                    item.matching_products[i];
                            }
                        }
                    });
                } else {
                    this.dispenseProducts = [];
                }
            } catch (error) {
                console.error("Error loading matching products:", error);
                this.dispenseProducts = [];
            } finally {
                this.loadingDispenseProducts = false;
            }
        },

        async refreshCurrentProcessOrder() {
            if (!this.currentProcessOrder) return;

            try {
                console.log(
                    "Refreshing current process order data for ID:",
                    this.currentProcessOrder.outboundorderid,
                );

                // Use the detail endpoint to get comprehensive, up-to-date data
                const response = await axios.get(
                    `${API_BASE_URL}/api/fbm-orders/detail`,
                    {
                        params: {
                            order_id: this.currentProcessOrder.outboundorderid,
                        },
                        withCredentials: true,
                    },
                );

                console.log("Refresh response:", response);

                if (response.data && response.data.success) {
                    const updatedOrder = response.data.data;

                    // Update the current process order with fresh data
                    this.currentProcessOrder = {
                        ...updatedOrder,
                        checked: this.currentProcessOrder.checked || false,
                    };

                    console.log(
                        "Updated current process order:",
                        this.currentProcessOrder,
                    );

                    // Also update the corresponding order in the main orders array
                    const orderIndex = this.orders.findIndex(
                        (o) =>
                            o.outboundorderid ===
                            this.currentProcessOrder.outboundorderid,
                    );
                    if (orderIndex !== -1) {
                        this.orders[orderIndex] = {
                            ...this.currentProcessOrder,
                            checked: this.orders[orderIndex].checked || false,
                        };
                        console.log(
                            "Updated order in main list at index:",
                            orderIndex,
                        );
                    }

                    // Reset selectedItems to include all items
                    this.selectedItems = this.currentProcessOrder.items.map(
                        (item) => item.outboundorderitemid,
                    );

                    // Update dispense items selection to reflect newly dispensed items
                    this.initializeDispenseItems();

                    console.log("Process order refresh completed successfully");
                } else {
                    console.error(
                        "Failed to refresh order data:",
                        response.data,
                    );
                    // Don't throw error, just log it and continue with existing data
                }
            } catch (error) {
                console.error("Error refreshing order data:", error);

                // Instead of failing completely, let's try to refresh from the main orders list
                console.log("Attempting to refresh from main orders list...");
                try {
                    await this.fetchOrders();

                    // Find the updated order in the main list
                    const updatedOrder = this.orders.find(
                        (o) =>
                            o.outboundorderid ===
                            this.currentProcessOrder.outboundorderid,
                    );
                    if (updatedOrder) {
                        this.currentProcessOrder = {
                            ...updatedOrder,
                            checked: this.currentProcessOrder.checked || false,
                        };
                        console.log(
                            "Successfully refreshed from main orders list",
                        );
                    }
                } catch (fallbackError) {
                    console.error(
                        "Fallback refresh also failed:",
                        fallbackError,
                    );
                    // At this point we'll just continue with the existing data
                    console.log("Continuing with existing data...");
                }
            }
        },
        openPrintInvoiceModal(order) {
            this.selectedOrder = order;
            this.printInvoiceVisible = true;
        },
        closePrintInvoiceModal() {
            this.printInvoiceVisible = false;
            this.selectedOrder = null;
        },
        openManualShipmentLabelModal() {
            this.manualShipmentLabelVisible = true;
        },
        closeManualShipmentLabelModal() {
            this.manualShipmentLabelVisible = false;
        },

        openManualDispenseForItem(item) {
            if (!item) return;

            console.log(
                "🔧 Opening manual dispense for item:",
                item.outboundorderitemid,
            );

            this.currentManualDispenseItem = item;
            this.showManualDispenseModal = true;
        },

        async handleManualDispenseComplete(data) {
            console.log("✅ Manual dispense completed:", data);

            // Comprehensive refresh
            await this.refreshAfterManualDispense(data.orderId);
        },

        async refreshAfterManualDispense(orderId) {
            console.log(
                "🔄 Starting refresh after manual dispense for order:",
                orderId,
            );

            // Step 1: Refresh main orders list
            await this.fetchOrders();

            // Step 2: Update process modal if open
            if (
                this.currentProcessOrder &&
                this.currentProcessOrder.outboundorderid === orderId
            ) {
                const updatedOrder = this.orders.find(
                    (o) => o.outboundorderid === orderId,
                );
                if (updatedOrder) {
                    this.currentProcessOrder = {
                        ...updatedOrder,
                        checked: this.currentProcessOrder.checked,
                    };

                    this.selectedItems = this.currentProcessOrder.items
                        ? this.currentProcessOrder.items.map(
                              (item) => item.outboundorderitemid,
                          )
                        : [];
                }
            }

            // Step 3: Update details modal if open
            if (
                this.selectedOrder &&
                this.selectedOrder.outboundorderid === orderId
            ) {
                const updatedOrder = this.orders.find(
                    (o) => o.outboundorderid === orderId,
                );
                if (updatedOrder) {
                    this.selectedOrder = { ...updatedOrder };
                }
            }

            // Step 4: Reinitialize dispense items
            this.initializeDispenseItems();

            // Step 5: Check ONLY this order since items were just dispensed
            console.log(
                "☑️ Checking order-level checkbox after manual dispense...",
            );
            this.autoCheckOrderAfterDispense(orderId);

            // Step 6: Force update
            this.$nextTick(() => {
                this.$forceUpdate();
            });

            console.log("✅ Manual dispense refresh completed");
        },

        /**
         * Get remaining quantity needed for an item
         */
        getRemainingQuantityNeeded(item) {
            if (!item) return 0;
            const dispensed = this.getDispensedProductCount(item);
            return Math.max(0, item.quantity_ordered - dispensed);
        },

        /**
         * Check if item needs more products
         */
        itemNeedsMoreProducts(item) {
            return this.getRemainingQuantityNeeded(item) > 0;
        },
    },
    watch: {
        searchQuery() {
            this.currentPage = 1;
            this.fetchOrders();
        },
    },
    mounted() {
        axios.defaults.baseURL = window.location.origin;
        axios.defaults.withCredentials = true;

        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) {
            axios.defaults.headers.common["X-CSRF-TOKEN"] =
                token.getAttribute("content");
        }

        if (!document.querySelector('link[href*="font-awesome"]')) {
            const fontAwesome = document.createElement("link");
            fontAwesome.rel = "stylesheet";
            fontAwesome.href =
                "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css";
            document.head.appendChild(fontAwesome);
        }

        this.fetchStores();
        this.fetchOrders();
        this.initializeDispenseItems();

        // manualshipmentlabel modal func________________________________________
        const modalEl = document.getElementById("manualShipmentLabelModal");
        if (!modalEl) return;

        const manualLabelModal = new bootstrap.Modal(modalEl, {
            backdrop: "static",
            keyboard: false,
        });

        window.openManualShipmentLabel = () => {
            manualLabelModal.show();
        };

        window.closeManualShipmentLabel = () => {
            manualLabelModal.hide();
        };

        // Reset modal form when it’s closed by any method (X, backdrop, Cancel)
        modalEl.addEventListener("hidden.bs.modal", () => {
            this.resetForm();
        });
        //_______________________________________________________________________
    },
};
