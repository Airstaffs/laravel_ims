import { eventBus } from "../../components/eventbus";
import ScannerComponent from "../../components/Scanner.vue";
import NewScannedItemModal from "./modals/newScanneditem.vue";
import { SoundService } from "../../components/Sound_service";
import "../../../css/modules.css";
import Ds7OosModal from "./modals/ds7oos.vue";

// Fallback to current origin if VITE_API_URL is not set to avoid undefined requests
const API_BASE_URL = import.meta.env.VITE_API_URL || window.location.origin;

export default {
    name: "StockroomModule",
    components: {
        ScannerComponent,
        NewScannedItemModal,
        Ds7OosModal,
    },
    data() {
        return {
            inventory: [],
            loading: true,
            currentPage: 1,
            totalPages: 1,
            perPage: 10, // Default rows per page
            selectAll: false,
            expandedRows: {},
            serialDropdowns: {}, // Added for serial number dropdowns
            sortColumn: "",
            sortOrder: "asc",
            showDetails: false,

            // Store filter
            stores: [],
            selectedStore: "",

            availabilityFilter: "all",

            // Scanner data
            serialNumber: "",
            fnsku: "",
            locationInput: "",
            showManualInput: false, // Will be set from scanner component

            // For auto verification
            autoVerifyTimeout: null,

            // For FNSKU validation
            fnskuValid: false,
            fnskuChecking: false,
            fnskuStatus: "",
            fnskuNormalized: false,

            // ✅ NEW: Control FNSKU field visibility
            showFnskuField: false,
            checkingSerial: false,
            serialExists: false,
            serialCheckMessage: "",

            // For process modal (replaces move modal)
            showProcessModal: false,
            processShipmentType: "For Dispense",
            processTrackingNumber: "",
            processNotes: "",
            processLocation: "",
            currentProcessItem: null,
            currentProductId: null, // Added to store the product ID
            currentProductAsin: null, // Added to store the ASIN
            currentProductTitle: "", // Added to store the product title
            selectedItems: [],
            selectAllItems: false,
            isProcessing: false,

            //printing function
            showPrinterSelectionModal: false,
            selectedPrinterForPrint: null,
            availablePrinters: [],
            loadingPrinters: false,
            selectedItemsForPrint: [],
            printSmallLabelOnly: false,
            rememberedPrinterId: null,

            //scanned newly items - Updated for modal integration
            newScannedCount: 0,
            showNewScannedModal: false,
            countRefreshInterval: null,

            // For product details modal
            showProductDetailsModal: false,
            selectedProduct: null,
            enlargeImage: false, // For toggling enlarged image view

            // For image handling
            defaultImagePath: "/images/default-product.png",

            // Add this for inventory counts
            inventoryCounts: {
                total: 0,
                qoh: 0,
                fbm: 0,
                fba: 0,
            },

            // for post to AMazon
            isPosting: false,
            postForm: {
                marketplace: "ATVPDKIKX0DER",
                fulfillmentChannel: "DEFAULT",
                currency: "USD",
                price: 19.99,
            },

            // DS7oos
            showDs7Oos: false,
            dsFilters: {
                datalimit: 14,
                window: 7,
                store: "",
                min_sold: 0,
                sort: "ds_asc",
                per_page: 25,
                include_oos: 1,
                use_orders: 0,
                page: 1, // <-- good to keep for future pagination
            },

            // FNSKU Table
            fnskuSummaries: {},
            isUnmerging: false,

            isMovingToLabeling: false,
        };
    },
    computed: {
        searchQuery() {
            return eventBus.searchQuery;
        },
        // Filter inventory based on FBM/FBA availability
        filteredInventory() {
            if (this.availabilityFilter === "all") {
                return this.inventory;
            }

            return this.inventory.filter((item) => {
                const hasFBM = item.FBMAvailable > 0;
                const hasFBA = item.FbaAvailable > 0;

                switch (this.availabilityFilter) {
                    case "fbm":
                        return hasFBM && !hasFBA; // Only FBM
                    case "fba":
                        return hasFBA && !hasFBM; // Only FBA
                    case "both":
                        return hasFBM && hasFBA; // Both FBM and FBA
                    case "none":
                        return !hasFBM && !hasFBA; // Neither FBM nor FBA
                    default:
                        return true;
                }
            });
        },

        // Sort the filtered inventory
        sortedInventory() {
            const itemsToSort = this.filteredInventory; // Use filtered inventory

            if (!this.sortColumn) return itemsToSort;

            return [...itemsToSort].sort((a, b) => {
                const valueA = a[this.sortColumn];
                const valueB = b[this.sortColumn];

                if (typeof valueA === "number" && typeof valueB === "number") {
                    return this.sortOrder === "asc"
                        ? valueA - valueB
                        : valueB - valueA;
                }

                return this.sortOrder === "asc"
                    ? String(valueA).localeCompare(String(valueB))
                    : String(valueB).localeCompare(String(valueA));
            });
        },
        // Add a computed property to detect mobile
        isMobile() {
            return window.innerWidth <= 768;
        },
        // Check if only one item is selected
        singleItemSelected() {
            return this.selectedItems.length === 1;
        },
        // Check if any items are selected
        hasSelectedItems() {
            return this.selectedItems.length > 0;
        },

        // Check if process form is valid for submission
        isProcessFormValid() {
            // Basic validation for processing - require shipment type and tracking number
            return (
                this.processShipmentType &&
                this.processTrackingNumber &&
                this.selectedItems.length > 0
            );
        },

        shouldShowBadge() {
            console.log("🔍 Badge visibility check:", {
                count: this.newScannedCount,
                type: typeof this.newScannedCount,
                isNumber: !isNaN(this.newScannedCount),
                isGreaterThanZero: this.newScannedCount > 0,
            });

            // Simple, clear logic
            const count = Number(this.newScannedCount);
            return !isNaN(count) && count > 0;
        },

        badgeClasses() {
            const count = this.newScannedCount || 0;
            return {
                "large-number": count >= 10 && count < 100,
                "extra-large": count >= 100,
            };
        },

        displayCount() {
            const count = this.newScannedCount || 0;
            return count > 999 ? "999+" : count.toString();
        },

        distinctStores() {
            const uniq = Array.from(new Set(this.stores || []));
            return uniq.sort((a, b) => String(a).localeCompare(String(b)));
        },

        singlePrinters() {
            return this.availablePrinters.filter(
                (printer) => !printer.is_married,
            );
        },

        marriedPrinters() {
            return this.availablePrinters.filter(
                (printer) => printer.is_married,
            );
        },

        // Group married printers into pairs
        marriedPrinterGroups() {
            const married = this.marriedPrinters;
            const groups = [];
            const processed = new Set();

            for (let i = 0; i < married.length; i++) {
                const printer = married[i];

                // Skip if already processed
                if (processed.has(printer.printerid)) continue;

                // Find its partner (next married printer or itself)
                const partner = married[i + 1];

                if (partner && !processed.has(partner.printerid)) {
                    // Found a pair
                    groups.push({
                        label: `${printer.printername_short} & ${partner.printername_short}`,
                        value: `${printer.printerid},${partner.printerid}`, // Store both IDs
                    });
                    processed.add(printer.printerid);
                    processed.add(partner.printerid);
                } else {
                    // Single married printer (no partner found)
                    groups.push({
                        label: printer.printername_short,
                        value: printer.printerid.toString(),
                    });
                    processed.add(printer.printerid);
                }
            }

            return groups;
        },

        isMergedItem() {
            if (!this.currentProcessItem) {
                console.log("No current process item");
                return false;
            }

            console.log("Checking if merged item:", this.currentProcessItem);

            // Check multiple possible locations for mergeID
            // 1. Check at item level
            if (this.currentProcessItem.mergeID) {
                console.log(
                    "Found mergeID at item level:",
                    this.currentProcessItem.mergeID,
                );
                return true;
            }

            // 2. Check in serials array
            if (
                this.currentProcessItem.serials &&
                this.currentProcessItem.serials.length > 0
            ) {
                const hasMergeID = this.currentProcessItem.serials.some(
                    (serial) => {
                        const has =
                            serial.mergeID !== null &&
                            serial.mergeID !== undefined &&
                            serial.mergeID !== 0;
                        if (has) {
                            console.log(
                                "Found mergeID in serial:",
                                serial.ProductID,
                                "mergeID:",
                                serial.mergeID,
                            );
                        }
                        return has;
                    },
                );

                if (hasMergeID) return true;
            }

            console.log("No mergeID found - not a merged item");
            return false;
        },

        // Check if only one item is selected and it's merged
        canUnmerge() {
            const can = this.selectedItems.length === 1 && this.isMergedItem;
            console.log("Can unmerge?", can, {
                selectedCount: this.selectedItems.length,
                isMerged: this.isMergedItem,
            });
            return can;
        },

        canMergeSelected() {
            if (this.selectedItems.length < 2) {
                return false;
            }

            if (!this.currentProcessItem || !this.currentProcessItem.serials) {
                return false;
            }

            // Get the QuantityInside for the first item
            const firstSerial = this.currentProcessItem.serials.find(
                (s) => s.ProductID === this.selectedItems[0],
            );

            if (!firstSerial) {
                return false;
            }

            const quantityInside = this.currentProcessItem.quantity_inside || 1;
            const targetPackSize = this.selectedItems.length * quantityInside;

            // Only allow 2-pack or 4-pack
            const allowedPackSizes = [2, 4];

            return allowedPackSizes.includes(targetPackSize);
        },

        mergeButtonTooltip() {
            if (this.selectedItems.length < 2) {
                return "Select at least 2 items to merge";
            }

            if (!this.currentProcessItem) {
                return "No items selected";
            }

            const quantityInside = this.currentProcessItem.quantity_inside || 1;
            const targetPackSize = this.selectedItems.length * quantityInside;

            if (![2, 4].includes(targetPackSize)) {
                return `Cannot create ${targetPackSize}-pack. Only 2-pack and 4-pack are allowed.\n\nTo create:\n• 2-pack: Select 2 single items\n• 4-pack: Select 4 single items or 2 double items`;
            }

            return `Merge ${this.selectedItems.length} items into ${targetPackSize}-pack`;
        },

        hasSelectedItems() {
            return this.selectedItems && this.selectedItems.length > 0;
        },
    },
    methods: {
        setupDailyReset() {
            const scheduleNextReset = () => {
                const now = new Date();
                const usNow = new Date(
                    now.toLocaleString("en-US", {
                        timeZone: "America/Los_Angeles",
                    }),
                );

                // Calculate next midnight in US timezone new scan count
                const nextMidnight = new Date(usNow);
                nextMidnight.setHours(24, 0, 0, 0); // Set to next midnight

                const msUntilMidnight = nextMidnight - usNow;

                console.log(
                    `Next count reset scheduled in ${Math.round(
                        msUntilMidnight / 1000 / 60,
                    )} minutes (at US midnight)`,
                );

                setTimeout(() => {
                    console.log(
                        "Daily reset triggered - fetching new count for new day",
                    );
                    this.fetchNewScannedCount();
                    // Schedule the next reset
                    scheduleNextReset();
                }, msUntilMidnight + 1000); // Add 1 second buffer
            };

            // Start the daily reset cycle
            scheduleNextReset();
        },

        // Add this method for better error recovery new scan count
        async fetchCountWithRetry(maxRetries = 3) {
            for (let attempt = 1; attempt <= maxRetries; attempt++) {
                try {
                    console.log(`🔄 Fetch attempt ${attempt}/${maxRetries}`);

                    // CRITICAL FIX: Call refreshNewScannedCount instead of fetchNewScannedCount
                    await this.refreshNewScannedCount();

                    console.log(
                        `✅ Success on attempt ${attempt}, count:`,
                        this.newScannedCount,
                    );
                    return; // Success, exit retry loop
                } catch (error) {
                    console.log(`⚠️ Attempt ${attempt} failed:`, error.message);

                    if (attempt === maxRetries) {
                        console.error("❌ All retry attempts failed");
                        return;
                    }

                    // Wait before retrying (exponential backoff)
                    const delay = Math.pow(2, attempt) * 500; // 500ms, 1s, 2s
                    await new Promise((resolve) => setTimeout(resolve, delay));
                }
            }
        },
        // Function to get the image path based on ASIN
        getImagePath(asin) {
            // Direct path return without checks to prevent blinking
            return asin
                ? `/images/asinimg/${asin}_0.webp`
                : this.defaultImagePath;
        },

        // Simplified image error handling that just swaps to default image
        handleImageError(event, item) {
            // Immediately set the source to default image
            event.target.src = this.defaultImagePath;

            // Mark this item to use default image from now on
            if (item) item.useDefaultImage = true;
        },

        // Add this method to create an SVG placeholder
        createDefaultImageSVG() {
            return `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100' width='100' height='100'%3E%3Crect width='100' height='100' fill='%23f0f0f0'/%3E%3Cpath d='M35,30L65,30L65,70L35,70Z' fill='%23e0e0e0' stroke='%23bbbbbb' stroke-width='2'/%3E%3Cpath d='M45,40L55,40L55,60L45,60Z' fill='%23d0d0d0' stroke='%23bbbbbb'/%3E%3Cpath d='M35,80L65,80L65,85L35,85Z' fill='%23e0e0e0'/%3E%3C/svg%3E`;
        },

        // Format the item count to show pack information
        formatItemCount(item) {
            if (!item) return "0";

            // New logic based on QuantityInside from tblasin
            const quantityInside = item.quantity_inside || 1;
            const unitCount = item.unit_count || item.box_count || 0;
            const totalQuantity = item.item_count || 0;

            if (quantityInside > 1) {
                // Show units and total quantity
                return `${unitCount} units (${totalQuantity} qty)`;
            }

            // For single quantity items, just show the count
            return totalQuantity.toString();
        },

        // Add a separate method for viewing product image
        viewProductImage(item) {
            // Set the selected product and open the modal directly
            this.selectedProduct = item;
            this.showProductDetailsModal = true;
        },

        // Regular product details modal
        viewProductDetails(item) {
            const processedItem = this.applyGradeConversion([item])[0];
            this.selectedProduct = processedItem;
            this.showProductDetailsModal = true;
        },

        // Close product details modal
        closeProductDetailsModal() {
            this.showProductDetailsModal = false;
            this.selectedProduct = null;
            this.enlargeImage = false; // Reset enlarged state
        },

        // Open process modal from product details
        openProcessModalFromDetails(item) {
            this.closeProductDetailsModal();
            this.openProcessModal(item);
        },

        // Open scanner modal method - this will call the scanner component's method
        openScannerModal() {
            this.$refs.scanner.openScannerModal();
        },

        loadFBAInboundShipment() {
            if (window.loadContent) {
                window.loadContent("fbashipmentinbound");
            } else {
                console.error("loadContent not found on window");
            }
        },

        // Format RT number based on store name
        formatRTNumber(rtCounter, storeName) {
            const paddedCounter = String(rtCounter).padStart(5, "0");

            if (storeName === "RenovarTech") {
                return `RT ${paddedCounter}`;
            } else if (storeName === "Allrenewed") {
                return `AR ${paddedCounter}`;
            } else {
                // Default format if store doesn't match known patterns
                return `#${paddedCounter}`;
            }
        },

        normalizeFnsku(fnsku) {
            if (!fnsku) return fnsku;

            const trimmed = fnsku.trim();
            console.log("Normalizing FNSKU:", {
                original: trimmed,
                length: trimmed.length,
            });

            // If it contains a dash, this is a composite FNSKU (e.g., 072BC8NV3-AllRenewed-...)
            // Do NOT normalize in that case
            if (trimmed.includes("-")) {
                console.log("FNSKU has dash - skipping normalization");
                return trimmed;
            }

            // Only normalize if:
            // - Longer than 10 characters
            // - Matches 2 chars + X/digit pattern
            // - After stripping, the remaining string is exactly 10 alphanumeric characters
            if (trimmed.length > 10 && /^[A-Z0-9]{2}[X0-9]/i.test(trimmed)) {
                const stripped = trimmed.substring(2);

                if (/^[A-Z0-9]{10}$/i.test(stripped)) {
                    console.log("FNSKU normalized:", {
                        original: trimmed,
                        normalized: stripped,
                        originalLength: trimmed.length,
                        normalizedLength: stripped.length,
                    });
                    return stripped;
                }
            }

            console.log("FNSKU not normalized - pattern did not match");
            return trimmed;
        },

        // Store dropdown functions
        async fetchStores() {
            try {
                const response = await axios.get(
                    `${API_BASE_URL}/api/stockroom/stores`,
                    {
                        withCredentials: true,
                    },
                );
                this.stores = response.data;
            } catch (error) {
                console.error("Error fetching stores:", error);
            }
        },

        changeStore() {
            this.currentPage = 1;
            this.fetchInventory();
        },

        // Validate the item count against serials
        validateItemCount(item) {
            if (!item) return true;

            // If no serials, just return true
            if (!item.serials || item.serials.length === 0) {
                return true;
            }

            // Check if the number of serials matches the unit_count
            const serialCount = item.serials.length;
            const unitCount = item.unit_count || item.box_count || 0;

            return serialCount === unitCount;
        },

        convertItemCondition(
            itemCondition,
            storeName,
            asin = null,
            originalGrading = null,
            asinStatus = null,
        ) {
            // Normalize store name check
            const isAllrenewed = ["allrenewed", "all renewed"].includes(
                storeName?.toLowerCase(),
            );

            console.log("Converting grade:", {
                itemCondition,
                storeName,
                isAllrenewed,
                asin,
                originalGrading,
                asinStatus,
            });

            let convertedGrade;

            switch (itemCondition) {
                case "UsedLikeNew":
                    convertedGrade = "Used - Like New";
                    break;

                case "UsedVeryGood":
                    convertedGrade = isAllrenewed
                        ? "Refurbished - Excellent"
                        : "Used - Very Good";
                    break;

                case "UsedGood":
                    convertedGrade = isAllrenewed
                        ? "Refurbished - Good"
                        : "Used - Good";
                    break;

                case "UsedAcceptable":
                    convertedGrade = isAllrenewed
                        ? "Refurbished - Acceptable"
                        : "Used - Acceptable";
                    break;

                case "New":
                    if (isAllrenewed && asinStatus) {
                        // Check ASIN status for Allrenewed store
                        if (asinStatus.toLowerCase() === "renewed") {
                            convertedGrade = "Refurbished - Excellent";
                        } else {
                            // If ASIN status is not 'renewed', return original grading
                            convertedGrade = originalGrading || "New";
                        }
                    } else {
                        // For non-Allrenewed stores, return original grading
                        convertedGrade = originalGrading || "New";
                    }
                    break;

                default:
                    // Handle unexpected condition values
                    convertedGrade = originalGrading || itemCondition;
            }

            console.log(
                "Grade converted from",
                itemCondition,
                "to",
                convertedGrade,
            );
            return convertedGrade;
        },

        // Enhanced helper method to get display grading for any item
        getDisplayGrading(item, storeName = null, productData = null) {
            if (!item) return "";

            const grading = item.grading || item.condition;
            const store = storeName || item.storename || this.selectedStore;
            const asin = item.ASIN || item.asin;

            // Get ASIN status from item or product data
            const asinStatus =
                item.asinStatus || productData?.asinStatus || null;

            // If display_grading already exists, use it
            if (item.display_grading) {
                return item.display_grading;
            }

            // Otherwise, convert on the fly
            return this.convertItemCondition(
                grading,
                store,
                asin,
                grading,
                asinStatus,
            );
        },

        // Apply grade conversion to inventory items (call this after fetching data)
        applyGradeConversion(items) {
            console.log("Applying grade conversion to", items.length, "items");

            return items.map((item) => {
                console.log(
                    "Processing item:",
                    item.ASIN || item.asin,
                    "asinStatus:",
                    item.asinStatus,
                );

                // Convert FNSKUs grades
                if (item.fnskus && item.fnskus.length > 0) {
                    item.fnskus = item.fnskus.map((fnsku) => {
                        const convertedGrade = this.convertItemCondition(
                            fnsku.grading,
                            fnsku.storename || item.storename,
                            item.ASIN,
                            fnsku.grading,
                            item.asinStatus, // Pass asinStatus to conversion
                        );

                        console.log(
                            "FNSKU grade converted:",
                            fnsku.grading,
                            "->",
                            convertedGrade,
                        );

                        return {
                            ...fnsku,
                            display_grading: convertedGrade,
                        };
                    });
                }

                // Convert serials grades
                if (item.serials && item.serials.length > 0) {
                    item.serials = item.serials.map((serial) => {
                        const convertedGrade = this.convertItemCondition(
                            serial.grading,
                            serial.storename || item.storename,
                            item.ASIN,
                            serial.grading,
                            item.asinStatus, // Pass asinStatus to conversion
                        );

                        console.log(
                            "Serial grade converted:",
                            serial.grading,
                            "->",
                            convertedGrade,
                        );

                        return {
                            ...serial,
                            display_grading: convertedGrade,
                        };
                    });
                }

                return item;
            });
        },

        // Add this method to your methods section:
        calculateInventoryCounts() {
            let totalCount = 0; // Number of rows/records
            let qohCount = 0; // Sum of all quantities in stockroom
            let fbmCount = 0; // Amazon FBM
            let fbaCount = 0; // Amazon FBA

            if (Array.isArray(this.inventory) && this.inventory.length > 0) {
                this.inventory.forEach((item) => {
                    // ✅ CORRECT: Count each product row
                    totalCount += 1;

                    // ✅ CORRECT: Sum of all item_count (which includes QuantityInside)
                    qohCount += parseInt(item.item_count || 0);

                    // Amazon inventory counts (separate from stockroom)
                    const fbmAvailable = parseInt(item.FBMAvailable || 0);
                    const fbaAvailable = parseInt(item.FbaAvailable || 0);

                    fbmCount += fbmAvailable;
                    fbaCount += fbaAvailable;
                });
            }

            this.inventoryCounts = {
                total: totalCount, // Number of product records in table
                qoh: qohCount, // Total quantity in stockroom (with QuantityInside)
                fbm: fbmCount, // Amazon FBM inventory
                fba: fbaCount, // Amazon FBA inventory
            };

            console.log("Inventory counts calculated:", this.inventoryCounts);
            console.log("- Total product records:", totalCount);
            console.log(
                "- Total QOH (quantity with QuantityInside):",
                qohCount,
            );
        },

        getDisplayTitle(item) {
            if (!item) return "";
            return (
                item.display_title || item.system_title || item.AStitle || ""
            );
        },

        // etchInventory with count validation
        async fetchInventory(forceFresh = false) {
            this.loading = true;
            try {
                console.log(
                    "Starting fetchInventory...",
                    forceFresh ? "(FORCE FRESH)" : "",
                );

                const response = await axios.get(
                    `${API_BASE_URL}/api/stockroom/products`,
                    {
                        params: {
                            search: this.searchQuery,
                            page: this.currentPage,
                            per_page: this.perPage,
                            store: this.selectedStore,
                            _t: forceFresh ? Date.now() : undefined, // Cache buster for forced refresh
                        },
                        withCredentials: true,
                        headers: {
                            "Cache-Control": "no-cache",
                            Pragma: "no-cache",
                            Expires: "0",
                        },
                    },
                );

                console.log("API Response received:", response.data);

                // Initialize items with checked property and useDefaultImage flag
                let inventoryItems = (response.data.data || []).map((item) => {
                    const itemWithFlags = {
                        ...item,
                        checked: false,
                        serials: item.serials || [],
                        fnskus: item.fnskus || [],
                        useDefaultImage: false,
                        countValid: true,

                        quantity_inside: item.quantity_inside || 1,
                        unit_count: item.unit_count || item.box_count || 0,
                        item_count: item.item_count || 0,
                        box_count: item.box_count || item.unit_count || 0,

                        display_title:
                            item.display_title ||
                            item.system_title ||
                            item.AStitle ||
                            "",
                        system_title: item.system_title || "",
                        AStitle: item.AStitle || "",
                    };

                    // Validate the item count
                    itemWithFlags.countValid =
                        this.validateItemCount(itemWithFlags);

                    return itemWithFlags;
                });

                // Apply grade conversion to all items
                this.inventory = this.applyGradeConversion(inventoryItems);

                console.log(
                    "Inventory items processed:",
                    this.inventory.length,
                );

                this.totalPages = response.data.last_page || 1;

                // IMPORTANT: Calculate inventory counts AFTER setting this.inventory
                this.calculateInventoryCounts();

                console.log("✅ Inventory refreshed successfully");
            } catch (error) {
                console.error("Error fetching inventory data:", error);

                // Initialize with empty data on error
                this.inventory = [];
                this.inventoryCounts = {
                    total: 0,
                    qoh: 0,
                    fbm: 0,
                    fba: 0,
                };

                if (SoundService && SoundService.error) {
                    SoundService.error();
                }
            } finally {
                this.loading = false;
            }
        },

        // Pagination methods
        changePerPage() {
            this.currentPage = 1;
            this.fetchInventory();
        },
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.fetchInventory();
            }
        },
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.fetchInventory();
            }
        },

        // Inventory selection methods
        toggleAll() {
            this.inventory.forEach((item) => (item.checked = this.selectAll));
        },

        toggleDetails(index, item) {
            const updated = { ...this.expandedRows };
            const opening = !updated[index];
            updated[index] = opening;
            this.expandedRows = updated;

            // if opening, prefetch summaries for all FNSKUs under this product
            if (opening && item?.fnskus?.length) {
                item.fnskus.forEach((f) => {
                    const raw = f.FNSKU || f; // your data sometimes stores string or object
                    if (raw) this.loadFnskuSummary(raw, this.selectedStore);
                });
            }
        },

        toggleDetailsVisibility() {
            this.showDetails = !this.showDetails;
        },

        // Sorting method
        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortOrder = this.sortOrder === "asc" ? "desc" : "asc";
            } else {
                this.sortColumn = column;
                this.sortOrder = "asc";
            }
        },

        // Process modal functions
        openProcessModal(item) {
            const processedItem = this.applyGradeConversion([item])[0];
            this.currentProcessItem = processedItem;

            // DEBUG: Log the item structure
            console.log("Opening process modal for item:", processedItem);
            console.log("Item serials:", processedItem.serials);
            console.log(
                "Checking for mergeID in serials:",
                processedItem.serials?.map((s) => ({
                    ProductID: s.ProductID,
                    mergeID: s.mergeID,
                    rtcounter: s.rtcounter,
                })),
            );

            this.showProcessModal = true;
            this.processShipmentType = "For Dispense";
            this.processTrackingNumber = "";
            this.processNotes = "";
            this.processLocation = "";
            this.selectedItems = [];
            this.selectAllItems = false;

            // Store the parent product ID (ASIN level) - hidden from UI
            this.currentProductId = item.ProductID || null;
            this.currentProductAsin = item.ASIN || null;
            this.currentProductTitle = item.AStitle || "";

            // If the item has just one serial number, pre-select it and show its location
            if (item.serials && item.serials.length === 1) {
                const singleSerial = item.serials[0];
                this.selectedItems = [singleSerial.ProductID];
                this.processLocation = singleSerial.warehouselocation || "";

                // Use nextTick to ensure the input is rendered before focusing
                this.$nextTick(() => {
                    // Focus and select all text in the location field for easy editing
                    const locationInput = document.querySelector(
                        '.process-modal .form-control[placeholder="e.g., L123A or Floor"]',
                    );
                    if (locationInput) {
                        locationInput.focus();
                        locationInput.select();
                    }
                });
            }
        },

        closeProcessModal() {
            this.showProcessModal = false;
            this.currentProcessItem = null;
            this.selectedItems = [];
        },

        // Toggle selection of all items
        toggleAllItems() {
            if (this.selectAllItems) {
                // Select all items
                this.selectedItems = this.currentProcessItem.serials.map(
                    (serial) => serial.ProductID,
                );

                // Clear location field when multiple items are selected
                if (this.selectedItems.length > 1) {
                    this.processLocation = "";
                }
            } else {
                // Deselect all items
                this.selectedItems = [];
                this.processLocation = "";
            }
        },

        // Submit the process
        async submitProcess() {
            if (!this.isProcessFormValid) return;

            try {
                // Start loading state
                this.isProcessing = true;

                // Prepare data for API
                const processData = {
                    shipmentType: this.processShipmentType,
                    trackingNumber: this.processTrackingNumber,
                    notes: this.processNotes,
                    items: this.selectedItems,
                };

                // Send to API
                const response = await axios.post(
                    "/api/stockroom/process-items",
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

                if (response.data.success) {
                    // Show success message
                    alert(
                        `Successfully processed ${this.selectedItems.length} items`,
                    );
                    this.closeProcessModal();
                    // Refresh inventory
                    this.fetchInventory();
                } else {
                    // Show error message
                    alert(
                        `Error: ${
                            response.data.message || "Failed to process items"
                        }`,
                    );
                }
            } catch (error) {
                console.error("Error processing items:", error);
                alert("Failed to process items. Please try again.");
            } finally {
                this.isProcessing = false;
            }
        },

        // Update location for a single selected item
        async updateSelectedLocation() {
            if (!this.hasSelectedItems) {
                alert("Please select at least one item to update location.");
                return;
            }

            if (!this.processLocation) {
                alert("Please enter a new location.");
                return;
            }

            // Validate location format
            const locationRegex = /^L\d{3}[A-G]$/i;
            const isValid =
                locationRegex.test(this.processLocation.trim()) ||
                this.processLocation.trim() === "Floor" ||
                this.processLocation.trim() === "L800G";

            if (!isValid) {
                alert("Invalid Location Format (use L###X, Floor, or L800G)");
                return;
            }

            try {
                // Show loading state
                this.isProcessing = true;

                // Prepare update data
                const updateData = {
                    itemId: this.singleItemSelected
                        ? this.selectedItems[0]
                        : null, // For backward compatibility
                    itemIds: this.selectedItems,
                    newLocation: this.processLocation,
                };

                console.log("Sending update data:", updateData); // Add this for debugging

                // Send to API
                const response = await axios.post(
                    `${API_BASE_URL}/api/stockroom/update-location`,
                    updateData,
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

                if (response.data.success) {
                    // Show success message with item count
                    const itemCount = this.selectedItems.length;
                    const itemText = itemCount === 1 ? "item" : "items";
                    alert(
                        `Location updated successfully for ${itemCount} ${itemText}`,
                    );

                    this.closeProcessModal();
                    // Refresh inventory
                    this.fetchInventory();
                } else {
                    alert(
                        `Error: ${
                            response.data.message || "Failed to update location"
                        }`,
                    );
                }
            } catch (error) {
                console.error("Error updating location:", error);
                if (error.response && error.response.data) {
                    console.error("Server response:", error.response.data);
                    alert(
                        `Failed to update location: ${
                            error.response.data.message || "Unknown error"
                        }`,
                    );
                } else {
                    alert("Failed to update location. Please try again.");
                }
            } finally {
                this.isProcessing = false;
            }
        },

        // printer functions

        savePrinterPreference(printerId) {
            try {
                // Convert to string to ensure consistency
                const printerIdString = printerId.toString();
                localStorage.setItem("preferred_printer_id", printerIdString);
                this.rememberedPrinterId = printerIdString;
                console.log("Saved printer preference:", printerIdString);
            } catch (error) {
                console.error("Error saving printer preference:", error);
            }
        },

        /**
         * Load printer preference from localStorage
         */
        loadPrinterPreference() {
            try {
                const savedPrinterId = localStorage.getItem(
                    "preferred_printer_id",
                );
                if (savedPrinterId) {
                    this.rememberedPrinterId = savedPrinterId;
                    console.log("Loaded printer preference:", savedPrinterId);
                    return savedPrinterId;
                }
            } catch (error) {
                console.error("Error loading printer preference:", error);
            }
            return null;
        },

        /**
         * Clear printer preference
         */
        clearPrinterPreference() {
            try {
                localStorage.removeItem("preferred_printer_id");
                this.rememberedPrinterId = null;
                this.selectedPrinterForPrint = null; // Clear the dropdown selection
                console.log("Cleared printer preference");

                // Don't show alert if called automatically due to invalid printer
                if (this.showPrinterSelectionModal) {
                    alert(
                        "Printer preference cleared. Please select a printer.",
                    );
                }
            } catch (error) {
                console.error("Error clearing printer preference:", error);
            }
        },

        // Print selected items
        async printSelectedItems() {
            if (!this.hasSelectedItems) {
                alert("Please select at least one item to print.");
                return;
            }

            // Get serial numbers for selected items
            this.selectedItemsForPrint = [];

            for (const itemId of this.selectedItems) {
                const serial = this.currentProcessItem.serials.find(
                    (s) => s.ProductID === itemId,
                );

                if (serial && serial.serialnumber) {
                    this.selectedItemsForPrint.push({
                        productId: itemId,
                        serialNumber: serial.serialnumber,
                        rtCounter: serial.rtcounter,
                        fnsku: serial.FNSKUviewer,
                    });
                }
            }

            if (this.selectedItemsForPrint.length === 0) {
                alert("No valid serial numbers found for selected items.");
                return;
            }

            console.log("Items to print:", this.selectedItemsForPrint);

            // IMPORTANT: Fetch available printers FIRST
            await this.fetchAvailablePrinters();

            // THEN auto-select remembered printer AFTER printers are loaded
            const rememberedPrinterId = this.loadPrinterPreference();

            if (rememberedPrinterId) {
                console.log(
                    "Checking for remembered printer:",
                    rememberedPrinterId,
                );

                // Check if remembered printer exists in available printers
                // Handle both single printer IDs and married printer pairs (comma-separated)
                const printerExists = this.availablePrinters.some((p) => {
                    // Check single printer ID
                    if (p.printerid == rememberedPrinterId) {
                        return true;
                    }
                    // Check married printer pair
                    if (rememberedPrinterId.includes(",")) {
                        const ids = rememberedPrinterId.split(",");
                        return ids.includes(p.printerid.toString());
                    }
                    return false;
                });

                // Also check married printer groups
                const groupExists = this.marriedPrinterGroups.some(
                    (g) => g.value === rememberedPrinterId,
                );

                if (printerExists || groupExists) {
                    this.selectedPrinterForPrint = rememberedPrinterId;
                    console.log(
                        "Auto-selected remembered printer:",
                        rememberedPrinterId,
                    );

                    // Use Vue's nextTick to ensure the DOM is updated
                    this.$nextTick(() => {
                        console.log(
                            "Selected printer in dropdown:",
                            this.selectedPrinterForPrint,
                        );
                    });
                } else {
                    console.log(
                        "Remembered printer not found in available printers:",
                        rememberedPrinterId,
                    );
                    // Clear invalid remembered printer
                    this.clearPrinterPreference();
                }
            } else {
                console.log("No remembered printer found");
            }

            // Show printer selection modal
            this.showPrinterSelectionModal = true;
        },

        // Close printer selection modal
        closePrinterSelectionModal() {
            this.showPrinterSelectionModal = false;
            this.selectedPrinterForPrint = null;
            this.selectedItemsForPrint = [];
            this.printSmallLabelOnly = false;
        },

        // Confirm and execute print with selected printer
        async confirmPrintSelected() {
            if (!this.selectedPrinterForPrint) {
                alert("Please select a printer first.");
                return;
            }

            if (this.selectedItemsForPrint.length === 0) {
                alert("No items to print.");
                return;
            }

            this.isProcessing = true;

            try {
                let successCount = 0;
                let failCount = 0;
                const errors = [];

                // Extract the first printer ID as an integer
                const printerIds = this.selectedPrinterForPrint.includes(",")
                    ? this.selectedPrinterForPrint.split(",")
                    : [this.selectedPrinterForPrint];

                const primaryPrinterId = parseInt(printerIds[0]);

                // Save printer preference after successful selection
                this.savePrinterPreference(this.selectedPrinterForPrint);

                // Get printer names for display
                const printerNames = printerIds
                    .map((id) => {
                        const printer = this.availablePrinters.find(
                            (p) => p.printerid == id,
                        );
                        return printer ? printer.printername_short : id;
                    })
                    .join(" & ");

                console.log(`Starting batch print to ${printerNames}...`);
                console.log(
                    `Print mode: ${this.printSmallLabelOnly ? "Small Label Only" : "Full Label with Instruction Card"}`,
                );

                // Print each selected item
                for (const item of this.selectedItemsForPrint) {
                    try {
                        const result = await this.printLabelWithSerial(
                            item.serialNumber,
                            primaryPrinterId,
                            this.printSmallLabelOnly,
                        );

                        if (result.success) {
                            successCount++;
                        } else {
                            failCount++;
                            errors.push(
                                `${item.serialNumber}: ${result.message}`,
                            );
                        }
                    } catch (error) {
                        failCount++;
                        errors.push(`${item.serialNumber}: ${error.message}`);
                    }
                }

                let message = `Printing completed to ${printerNames}:\n${successCount} successful`;
                if (failCount > 0) {
                    message += `\n${failCount} failed`;
                    if (errors.length > 0) {
                        message += `\n\nErrors:\n${errors.slice(0, 3).join("\n")}`;
                        if (errors.length > 3) {
                            message += `\n...and ${errors.length - 3} more`;
                        }
                    }
                }

                alert(message);

                this.closePrinterSelectionModal();

                if (failCount === 0) {
                    this.closeProcessModal();
                }

                this.fetchInventory();
            } catch (error) {
                console.error("Error printing selected items:", error);
                alert("Error printing items: " + error.message);
            } finally {
                this.isProcessing = false;
            }
        },

        // Print label using serial number (uses existing printer controller)
        async printLabelWithSerial(
            serialNumber,
            printerId,
            smallLabelOnly = false,
        ) {
            try {
                console.log("🖨️ printLabelWithSerial called with:", {
                    serialNumber,
                    printerId,
                    smallLabelOnly,
                    smallLabelOnlyType: typeof smallLabelOnly,
                });

                const checkResponse = await axios.post(
                    `${API_BASE_URL}/api/printer/check-serial`,
                    { serial_number: serialNumber },
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
                    !checkResponse.data.success ||
                    !checkResponse.data.meets_print_conditions
                ) {
                    return {
                        success: false,
                        message:
                            checkResponse.data.message ||
                            "Item not ready for printing",
                    };
                }

                const printPayload = {
                    serial_number: serialNumber,
                    printer_id: printerId,
                    print_data: checkResponse.data,
                    small_label_only: smallLabelOnly,
                };

                console.log(
                    "✅ Check passed, sending to print with payload:",
                    printPayload,
                );
                console.log(
                    "📦 small_label_only value being sent:",
                    smallLabelOnly,
                    "(type:",
                    typeof smallLabelOnly,
                    ")",
                );

                const printResponse = await axios.post(
                    `${API_BASE_URL}/api/printer/print-label`,
                    printPayload,
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

                console.log("✅ Print response received:", printResponse.data);

                return printResponse.data;
            } catch (error) {
                console.error("❌ Error printing label:", error);
                return {
                    success: false,
                    message: error.response?.data?.message || error.message,
                };
            }
        },

        // Fetch available printers
        async fetchAvailablePrinters() {
            this.loadingPrinters = true;
            try {
                console.log("Fetching printers from API...");

                const response = await axios.get(
                    `${API_BASE_URL}/api/printer/get-printers`,
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

                console.log("Printer API response:", response.data);

                if (
                    response.data &&
                    response.data.success &&
                    response.data.printers
                ) {
                    this.availablePrinters = response.data.printers;
                    console.log(
                        "Available printers loaded:",
                        this.availablePrinters.length,
                    );

                    if (this.availablePrinters.length === 0) {
                        alert(
                            "No active printers found. Please check printer configuration.",
                        );
                    }
                } else {
                    console.error(
                        "Unexpected printer API response format:",
                        response.data,
                    );
                    this.availablePrinters = [];
                    alert(
                        "No printers available. Please check printer settings.",
                    );
                }
            } catch (error) {
                console.error("Error fetching printers:", error);
                console.error("Error response:", error.response?.data);

                if (error.response) {
                    alert(
                        `Failed to fetch printers: ${error.response.data.message || error.response.statusText}`,
                    );
                } else {
                    alert(
                        "Failed to fetch printers. Please check your connection.",
                    );
                }

                this.availablePrinters = [];
            } finally {
                this.loadingPrinters = false;
            }
        },

        // Validate serial number
        validateSerialNumber() {
            const serial = this.serialNumber.trim();

            // Skip validation if empty (it's optional)
            if (!serial) return true;

            // Check for valid serial format using regex
            const validFormat = /^[a-zA-Z0-9]+$/.test(serial);

            // Check if it contains X00
            const containsX00 = serial.includes("X00");

            return validFormat && !containsX00;
        },

        // Validate FNSKU and check if it's a location code
        validateFnsku() {
            const fnsku = this.fnsku.trim();

            // Skip validation if empty (when serial is provided)
            if (!fnsku) return true;

            // Check if it matches a location pattern
            const isLocation = /^L\d{3}[A-G]$/i.test(fnsku);

            // If it looks like a location code, mark it as invalid for FNSKU field
            return !isLocation;
        },

        // Check FNSKU availability
        async checkFnskuAvailability() {
            const fnsku = this.fnsku.trim();
            console.log("checkFnskuAvailability called with:", fnsku);

            // Skip check if empty or appears to be a location
            if (!fnsku || /^L\d{3}[A-G]$/i.test(fnsku)) {
                this.fnskuValid = false;
                console.log("FNSKU check skipped - empty or location pattern");
                return false;
            }

            try {
                this.fnskuChecking = true;

                console.log("Sending API request to check FNSKU:", fnsku);

                // Call API to check FNSKU status
                const response = await axios.get(
                    `${API_BASE_URL}/api/stockroom/check-fnsku`,
                    {
                        params: { fnsku: fnsku },
                    },
                );

                this.fnskuChecking = false;

                // Log the response for debugging
                console.log("FNSKU check API response:", response.data);

                // Update validity based on response
                if (
                    response.data.exists &&
                    response.data.status === "available"
                ) {
                    this.fnskuValid = true;
                    this.fnskuStatus = "available";

                    // If FNSKU was normalized, show the normalized version in the input
                    if (
                        response.data.normalized_fnsku &&
                        response.data.normalized_fnsku !==
                            response.data.original_fnsku
                    ) {
                        console.log(
                            "Server returned different normalized FNSKU:",
                            response.data.normalized_fnsku,
                        );
                        this.fnsku = response.data.normalized_fnsku;
                    }

                    return true;
                } else {
                    this.fnskuValid = false;
                    this.fnskuStatus = response.data.exists
                        ? response.data.status
                        : "not_found";

                    console.log("FNSKU not available:", {
                        exists: response.data.exists,
                        status: response.data.status,
                        normalized: response.data.normalized_fnsku,
                    });

                    // Still update the FNSKU field with normalized version for consistency
                    if (
                        response.data.normalized_fnsku &&
                        response.data.normalized_fnsku !==
                            response.data.original_fnsku
                    ) {
                        this.fnsku = response.data.normalized_fnsku;
                    }

                    return false;
                }
            } catch (error) {
                console.error("Error checking FNSKU:", error);
                console.log(error, error.status === 409);
                if (error.status === 409) {
                    this.fnskuStatus = "limit_reached";
                } else {
                    this.fnskuStatus = "error";
                }
                this.fnskuChecking = false;
                this.fnskuValid = false;

                return false;
            }
        },

        async checkSerialExists(serial) {
            if (!serial || serial.length < 3) {
                this.showFnskuField = false;
                this.serialExists = false;
                this.serialCheckMessage = "";
                return;
            }

            try {
                this.checkingSerial = true;

                const response = await axios.get(
                    `${API_BASE_URL}/api/stockroom/check-serial`,
                    {
                        params: { serial: serial },
                        withCredentials: true,
                    },
                );

                console.log("Serial check response:", response.data);

                if (response.data.exists) {
                    // ✅ Serial EXISTS in system
                    this.serialExists = true;
                    this.showFnskuField = false; // Hide FNSKU field
                    this.fnsku = ""; // Clear FNSKU input
                    this.serialCheckMessage = `✓ Item found in ${response.data.location}`;

                    console.log("✅ Serial exists - hiding FNSKU field");
                } else {
                    // ❌ Serial DOESN'T exist (new item)
                    this.serialExists = false;
                    this.showFnskuField = true; // Show FNSKU field
                    this.serialCheckMessage = "⚠ New item - FNSKU required";

                    console.log("❌ Serial not found - showing FNSKU field");
                }
            } catch (error) {
                console.error("Error checking serial:", error);
                // On error, show FNSKU field to be safe
                this.showFnskuField = true;
                this.serialCheckMessage = "";
            } finally {
                this.checkingSerial = false;
            }
        },

        // Input field handlers with sound
        async handleSerialInput() {
            // First validate serial number
            const isValid = this.validateSerialNumber();

            if (!isValid) {
                this.$refs.scanner.showScanError(
                    "Invalid Serial Number - must be alphanumeric and not contain X00",
                );
                this.$refs.serialNumberInput.select();
                SoundService.error();
                return;
            }

            // ✅ Check if serial exists in system
            if (this.serialNumber.trim().length >= 5) {
                await this.checkSerialExists(this.serialNumber.trim());
            }

            // In auto mode with valid input
            if (!this.showManualInput && this.serialNumber.trim().length > 5) {
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }

                this.autoVerifyTimeout = setTimeout(() => {
                    SoundService.success();

                    // ✅ If serial exists, skip FNSKU and go to location
                    // ✅ If serial doesn't exist, go to FNSKU field
                    if (this.serialExists) {
                        this.focusNextField("locationInput");
                    } else {
                        this.focusNextField("fnskuInput");
                    }
                }, 500);
            }
        },

        async handleFnskuInput() {
            console.log("handleFnskuInput called with:", this.fnsku);

            // Normalize the FNSKU and update the input field automatically
            const originalFnsku = this.fnsku;
            const normalizedFnsku = this.normalizeFnsku(originalFnsku);

            console.log("Input normalization result:", {
                original: originalFnsku,
                normalized: normalizedFnsku,
                changed: originalFnsku !== normalizedFnsku,
            });

            // Update the input field to show the normalized FNSKU
            this.fnsku = normalizedFnsku;

            // First validate FNSKU
            const isValid = this.validateFnsku();

            if (!isValid) {
                // If it looks like a location, show a specific message
                this.$refs.scanner.showScanError(
                    "This appears to be a location code. Please enter it in the Location field.",
                );
                this.$refs.fnskuInput.select();
                SoundService.error();
                return;
            }

            // In auto mode with valid input, check availability and proceed
            if (!this.showManualInput && this.fnsku.trim().length > 5) {
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }

                this.autoVerifyTimeout = setTimeout(async () => {
                    console.log(
                        "About to check FNSKU availability for:",
                        this.fnsku,
                    );

                    // Check FNSKU availability
                    const isAvailable = await this.checkFnskuAvailability();

                    if (isAvailable) {
                        // Play success sound if FNSKU is valid and available
                        SoundService.success();

                        // Focus on location field
                        this.focusNextField("locationInput");
                    } else {
                        // Show appropriate error message based on status
                        let errorMessage = "Unknown FNSKU status";

                        switch (this.fnskuStatus) {
                            case "not_found":
                                errorMessage = "FNSKU not found in database";
                                break;
                            case "unavailable":
                                errorMessage =
                                    "FNSKU exists but is not available";
                                break;
                            case "error":
                                errorMessage = "Error checking FNSKU status";
                                break;
                            case "limit_reached":
                                errorMessage = "FNSKU usage limit reached";
                                break;
                        }

                        console.error("FNSKU check failed:", {
                            fnsku: this.fnsku,
                            status: this.fnskuStatus,
                        });
                        this.$refs.scanner.showScanError(errorMessage);
                        SoundService.error();
                    }
                }, 500);
            }
        },

        // Fixed handleLocationInput method
        handleLocationInput() {
            // Only perform validation in auto mode
            if (!this.showManualInput) {
                // Validate location format
                const locationRegex = /^L\d{3}[A-G]$/i;
                const isValid =
                    locationRegex.test(this.locationInput.trim()) ||
                    this.locationInput.trim() === "Floor" ||
                    this.locationInput.trim() === "L800G";

                if (!isValid && this.locationInput.trim() !== "") {
                    this.$refs.scanner.showScanError(
                        "Invalid Location Format (use L###X, Floor, or L800G)",
                    );
                    this.$refs.locationInput.select();
                    SoundService.error();
                    return;
                }

                // Only in auto mode, process scan after valid location input
                if (isValid && this.locationInput.trim().length > 0) {
                    if (this.autoVerifyTimeout) {
                        clearTimeout(this.autoVerifyTimeout);
                    }

                    this.autoVerifyTimeout = setTimeout(() => {
                        // Play success sound for valid location
                        SoundService.success();

                        // Process the scan
                        this.processScan();
                    }, 500);
                }
            }
        },

        // Focus the next input field
        focusNextField(fieldRef) {
            this.$nextTick(() => {
                const nextField = this.$refs[fieldRef];
                if (nextField) {
                    nextField.focus();
                }
            });
        },

        // Process scan with validation - UPDATED with notification count refresh
        async processScan(scannedCode = null) {
            try {
                let scanSerial, scanFnsku, scanLocation;

                if (scannedCode) {
                    // Hardware scanner
                    scanSerial = scannedCode;
                    scanLocation = this.locationInput || "";

                    // Check if serial exists first
                    await this.checkSerialExists(scanSerial);

                    // If serial exists, don't need FNSKU
                    scanFnsku = this.serialExists ? "" : this.fnsku;
                } else {
                    // Manual input
                    scanSerial = this.serialNumber;
                    scanFnsku = this.showFnskuField ? this.fnsku : ""; // Only use FNSKU if field is shown
                    scanLocation = this.locationInput;

                    if (!scanSerial) {
                        this.$refs.scanner.showScanError(
                            "Serial Number is required",
                        );
                        SoundService.error();
                        this.focusNextField("serialNumberInput");
                        return;
                    }
                }

                // Validate serial
                if (
                    scanSerial &&
                    (!/^[a-zA-Z0-9]+$/.test(scanSerial) ||
                        scanSerial.includes("X00"))
                ) {
                    this.$refs.scanner.showScanError(
                        "Invalid Serial Number - must be alphanumeric and not contain X00",
                    );
                    SoundService.error();
                    return;
                }

                // Validate location
                const locationRegex = /^L\d{3}[A-G]$/i;
                if (
                    scanLocation &&
                    !locationRegex.test(scanLocation) &&
                    scanLocation !== "Floor" &&
                    scanLocation !== "L800G"
                ) {
                    this.$refs.scanner.showScanError(
                        "Invalid Location Format (use L###X, Floor, or L800G)",
                    );
                    SoundService.error();
                    return;
                }

                const imageData = this.$refs.scanner.capturedImages.map(
                    (img) => img.data,
                );

                const scanData = {
                    SerialNumber: scanSerial,
                    FNSKU: scanFnsku, // Will be empty if serial exists
                    Location: scanLocation,
                    Images: imageData,
                };

                console.log("📤 Sending scan data:", {
                    serial: scanSerial,
                    fnsku: scanFnsku || "(not provided - serial exists)",
                    location: scanLocation,
                    serialExists: this.serialExists,
                });

                this.$refs.scanner.startLoading("Processing Scan");

                const response = await axios.post(
                    "/api/stockroom/process-scan",
                    scanData,
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

                this.$refs.scanner.stopLoading();
                const data = response.data;

                if (data.success) {
                    this.$refs.scanner.showScanSuccess(
                        data.item || "Item scanned successfully",
                    );
                    SoundService.successScan(true);

                    this.$refs.scanner.addSuccessScan({
                        Serial: scanSerial,
                        FNSKU: scanFnsku || "(existing)",
                        Location: scanLocation,
                    });

                    this.$refs.scanner.capturedImages = [];

                    // Real-time updates
                    console.log("🎯 Scan successful - triggering updates...");

                    try {
                        await this.fetchCountWithRetry(3);
                        await this.fetchInventory();

                        if (
                            this.showNewScannedModal &&
                            this.$refs.newScannedModal?.fetchItems
                        ) {
                            await this.$refs.newScannedModal.fetchItems();
                        }

                        this.$nextTick(() => {
                            this.$forceUpdate();
                        });
                    } catch (error) {
                        console.error("⚠️ Error during refresh:", error);
                    }
                } else {
                    this.$refs.scanner.showScanError(
                        data.message || "Error processing scan",
                    );
                    SoundService.scanRejected(true);

                    this.$refs.scanner.addErrorScan(
                        {
                            Serial: scanSerial,
                            FNSKU: scanFnsku || "(not provided)",
                            Location: scanLocation,
                        },
                        data.reason || "error",
                    );

                    this.$refs.scanner.capturedImages = [];
                }

                // Clear input fields
                this.serialNumber = "";
                this.fnsku = "";
                this.locationInput = "";
                this.showFnskuField = false;
                this.serialExists = false;
                this.serialCheckMessage = "";
                this.focusNextField("serialNumberInput");
            } catch (error) {
                this.$refs.scanner.stopLoading();
                console.error("Error processing scan:", error);
                if (error.status === 409) {
                    this.$refs.scanner.showScanError(
                        "FNSKU has already reached its usage limit.",
                    );
                } else {
                    this.$refs.scanner.showScanError(
                        "Network or server errorss",
                    );
                }
                SoundService.scanRejected(true);
            }
        },

        // Updated mergeSelectedItems function with correct API URL format
        async mergeSelectedItems() {
            if (this.selectedItems.length < 2) {
                alert("Please select at least two items to merge.");
                return;
            }

            // Check if merge is allowed (2-pack or 4-pack only)
            if (!this.canMergeSelected) {
                alert(this.mergeButtonTooltip);
                return;
            }

            let productTitle = "";
            let productAsin = "";
            let productStore = "";
            let selectedSerials = [];
            let selectedFnsku = "";

            if (this.currentProcessItem) {
                productTitle = this.currentProcessItem.AStitle || "";
                productAsin = this.currentProcessItem.ASIN || "";
                productStore = this.currentProcessItem.storename || "";

                selectedSerials = this.currentProcessItem.serials
                    .filter((serial) =>
                        this.selectedItems.includes(serial.ProductID),
                    )
                    .map((serial) => serial.serialnumber);

                if (
                    this.currentProcessItem.fnskus &&
                    this.currentProcessItem.fnskus.length > 0
                ) {
                    let rawFnsku =
                        this.currentProcessItem.fnskus[0].FNSKU ||
                        this.currentProcessItem.fnskus[0];
                    selectedFnsku = this.normalizeFnsku(rawFnsku);
                }
            }

            if (!productTitle) {
                alert("Could not determine product title for merging.");
                return;
            }

            const quantityInside = this.currentProcessItem.quantity_inside || 1;
            const targetPackSize = this.selectedItems.length * quantityInside;

            if (
                confirm(
                    `Are you sure you want to merge ${this.selectedItems.length} items into a ${targetPackSize}-pack of "${productTitle}"?\n\nNote: All items must have the same ASIN, Color, QuantityInside, Store, and Condition.`,
                )
            ) {
                try {
                    this.isProcessing = true;

                    const mergeData = {
                        items: this.selectedItems,
                        title: productTitle,
                        asin: productAsin,
                        store: productStore,
                        serialNumbers: selectedSerials,
                    };

                    if (selectedFnsku && selectedFnsku.trim() !== "") {
                        mergeData.fnsku = selectedFnsku;
                    }

                    console.log("Sending merge data:", mergeData);

                    const response = await axios.post(
                        `${API_BASE_URL}/api/stockroom/merge-items`,
                        mergeData,
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

                    if (response.data.success) {
                        const newRtNumber = response.data.newrt;
                        const productId = response.data.productid;
                        const mergedTitle = response.data.title || productTitle;
                        const mergedFnsku =
                            response.data.fnsku || selectedFnsku;

                        let storeNameForRt =
                            response.data.store || productStore;
                        const formattedRt = this.formatRTNumber(
                            newRtNumber,
                            storeNameForRt,
                        );

                        alert(
                            `✅ Items successfully merged into ${formattedRt}: ${mergedTitle}${
                                mergedFnsku ? ` (FNSKU: ${mergedFnsku})` : ""
                            }`,
                        );

                        // Close modal first
                        this.closeProcessModal();

                        // Auto-refresh inventory immediately
                        await this.fetchInventory();

                        // Ask if user wants to print
                        if (
                            confirm(
                                "Do you want to print a label for the newly created item?",
                            )
                        ) {
                            await this.printLabel(productId);
                        }
                    } else {
                        alert(
                            `❌ Error: ${
                                response.data.message || "Failed to merge items"
                            }`,
                        );
                    }
                } catch (error) {
                    console.error("Error merging items:", error);

                    if (error.response?.data?.reason === "invalid_pack_size") {
                        alert(error.response.data.message);
                    } else if (
                        error.response?.data?.reason === "incompatible_items"
                    ) {
                        const errorData = error.response.data;
                        const incompatibleItems =
                            errorData.incompatible_items || [];

                        let errorMessage =
                            "❌ Cannot merge items - Incompatible products detected:\n\n";

                        const errorsBySerial = {};
                        incompatibleItems.forEach((issue) => {
                            if (!errorsBySerial[issue.serial]) {
                                errorsBySerial[issue.serial] = [];
                            }
                            errorsBySerial[issue.serial].push(issue);
                        });

                        for (const [serial, issues] of Object.entries(
                            errorsBySerial,
                        )) {
                            errorMessage += `📦 Serial: ${serial}\n`;
                            issues.forEach((issue) => {
                                errorMessage += `   • ${issue.reason}: Expected "${issue.expected}", Got "${issue.actual}"\n`;
                            });
                            errorMessage += "\n";
                        }

                        errorMessage +=
                            "💡 Tip: You can only merge items with the same ASIN, Color, QuantityInside, Store, and Condition.";
                        alert(errorMessage);
                    } else if (
                        error.response?.data?.reason ===
                            "fnsku_condition_mismatch" ||
                        error.response?.data?.reason === "fnsku_store_mismatch"
                    ) {
                        alert(
                            `❌ FNSKU Validation Failed:\n\n${error.response.data.message}\n\nPlease ensure the FNSKU matches the items' store and condition.`,
                        );
                    } else if (
                        error.response?.data?.reason ===
                        "no_pack_fnsku_available"
                    ) {
                        const searchDetails =
                            error.response.data.search_details || {};
                        const required = searchDetails.required || {};

                        let errorMessage =
                            "❌ Cannot merge - No matching FNSKU found.\n\n";
                        errorMessage += "Required FNSKU must have:\n";
                        errorMessage += `• Pack Size: ${required.quantity_inside}-pack\n`;
                        errorMessage += `• Color: ${required.color || "Any"}\n`;
                        errorMessage += `• Condition: ${required.condition}\n`;
                        errorMessage += `• Store: ${required.storename}\n`;
                        errorMessage += "• Status: Available with units\n\n";
                        errorMessage +=
                            "Please create an FNSKU matching ALL these criteria before merging.";

                        alert(errorMessage);
                    } else if (error.response?.data?.message) {
                        alert(`❌ Error: ${error.response.data.message}`);
                    } else {
                        alert("❌ Failed to merge items. Please try again.");
                    }
                } finally {
                    this.isProcessing = false;
                }
            }
        },

        /**
         * Unmerge a merged item
         */
        async unmergeItem() {
            if (!this.canUnmerge) {
                alert("Please select exactly one merged item to unmerge.");
                return;
            }

            const selectedProductId = this.selectedItems[0];

            const selectedSerial = this.currentProcessItem.serials.find(
                (serial) => serial.ProductID === selectedProductId,
            );

            if (!selectedSerial) {
                alert("Could not find selected item information.");
                return;
            }

            const rtNumber = this.formatRTNumber(
                selectedSerial.rtcounter,
                selectedSerial.storename,
            );

            if (
                !confirm(
                    `Are you sure you want to unmerge item ${rtNumber}?\n\n` +
                        `This will:\n` +
                        `• Delete the merged item\n` +
                        `• Restore all original items back to Stockroom\n` +
                        `• Return FNSKU units\n\n` +
                        `This action cannot be undone.`,
                )
            ) {
                return;
            }

            try {
                this.isUnmerging = true;

                const response = await axios.post(
                    `${API_BASE_URL}/api/stockroom/unmerge-item`,
                    {
                        productId: selectedProductId,
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

                if (response.data.success) {
                    alert(
                        `✅ ${response.data.message}\n\n` +
                            `${response.data.restored_count} original items have been restored to Stockroom.`,
                    );

                    // Close modal first
                    this.closeProcessModal();

                    // Auto-refresh inventory immediately
                    await this.fetchInventory();
                } else {
                    alert(`❌ Error: ${response.data.message}`);
                }
            } catch (error) {
                console.error("Error unmerging item:", error);

                if (error.response?.data?.message) {
                    alert(`❌ Error: ${error.response.data.message}`);
                } else {
                    alert("❌ Failed to unmerge item. Please try again.");
                }
            } finally {
                this.isUnmerging = false;
            }
        },

        // Print label method
        async printLabel(productId) {
            this.loading = true;
            try {
                const response = await axios.post(
                    "/api/stockroom/print-label",
                    {
                        productId: productId,
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

                if (response.data.status === "success") {
                    alert("Label printing started.");
                } else {
                    alert("Error: " + response.data.message);
                }
            } catch (error) {
                console.error("Error printing label:", error);
                alert("Failed to print label. Please try again.");
            } finally {
                this.loading = false;
            }
        },

        // Scanner event handlers
        handleScanProcess() {
            this.processScan();
        },

        handleHardwareScan(scannedCode) {
            // Check if the scanned code looks like an FNSKU
            if (scannedCode && /^[A-Z0-9]{10,}$/i.test(scannedCode)) {
                // If it's an FNSKU, normalize it and put it in the FNSKU field
                const normalizedFnsku = this.normalizeFnsku(scannedCode);
                this.fnsku = normalizedFnsku;

                // Focus on the location field next
                this.$nextTick(() => {
                    this.focusNextField("locationInput");
                });
            } else {
                // For other codes, process the scan normally
                this.processScan(scannedCode);
            }
        },

        handleModeChange(event) {
            this.showManualInput = event.manual;
        },

        handleScannerOpened() {
            this.showManualInput = this.$refs.scanner.showManualInput;

            // Reset fields
            this.serialNumber = "";
            this.fnsku = "";
            this.locationInput = "";
            this.showFnskuField = false; // ✅ Hide FNSKU initially
            this.serialExists = false;
            this.serialCheckMessage = "";

            // Focus on first field
            this.$nextTick(() => {
                if (this.$refs.serialNumberInput) {
                    this.$refs.serialNumberInput.focus();
                }
            });
        },

        handleScannerClosed() {
            // Refresh inventory when scanner is closed
            this.fetchInventory();
        },

        handleScannerReset() {
            this.serialNumber = "";
            this.fnsku = "";
            this.locationInput = "";
            this.showFnskuField = false;
            this.serialExists = false;
            this.serialCheckMessage = "";
        },

        // Methods for handling responsiveness
        handleResize() {
            // If we're on mobile and dropdowns are open, we might want to close them
            if (this.isMobile) {
                const hasOpenDropdowns = Object.values(
                    this.serialDropdowns,
                ).some((isOpen) => isOpen);
                if (hasOpenDropdowns) {
                    this.serialDropdowns = {};
                }
            }
        },

        closeDropdownsOnClickOutside(event) {
            // Check if click is outside any dropdown
            const isOutside = !event.target.closest(".serial-dropdown");
            if (isOutside) {
                this.serialDropdowns = {};
            }
        },

        async postItemstoAmzn() {
            if (!this.hasSelectedItems) {
                alert("Please select at least one item to post to Amazon.");
                return;
            }
            console.log(this.selectedItems);
            this.loading = true;
            try {
                const response = await axios.post(
                    "/api/stockroom/post-items-to-amazon",
                    {
                        selectedItems: this.selectedItems,
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

                if (response.data.status === "success") {
                    alert("Item Posted.");
                } else {
                    alert("Error: " + response.data.message);
                }
            } catch (error) {
                console.error("Error printing label:", error);
                alert("Failed to print label. Please try again.");
            } finally {
                this.loading = false;
            }
        },

        openPostAmazonModal() {
            if (!this.hasSelectedItems) {
                alert("Please select at least one item.");
                return;
            }
            $(this.$refs.postAmazonModal).modal("show");
        },
        closePostAmazonModal() {
            $(this.$refs.postAmazonModal).modal("hide");
        },
        async submitPostToAmazon() {
            this.isPosting = true;
            try {
                const response = await axios.post(
                    "/api/stockroom/post-items-to-amazon",
                    {
                        selectedItems: this.selectedItems,
                        ...this.postForm,
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

                if (response.data.status === "success") {
                    alert("Items successfully posted to Amazon.");
                } else {
                    alert(
                        "Error: " + (response.data.message || "Unknown error."),
                    );
                }
            } catch (err) {
                console.error(err);
                alert("An error occurred.");
            } finally {
                this.isPosting = false;
            }
        },

        // NEW SCANNED ITEMS METHODS - Updated for modal integration
        async fetchNewScannedCount() {
            try {
                const now = new Date();
                const usDate = new Date(
                    now.toLocaleString("en-US", {
                        timeZone: "America/Los_Angeles",
                    }),
                );
                const today = usDate.toISOString().split("T")[0];

                console.log(
                    "🔄 Fetching new scanned count for US date:",
                    today,
                );

                const response = await axios.get(
                    `${API_BASE_URL}/api/stockroom/new-scanned-count`,
                    {
                        params: {
                            date: today,
                            _t: Date.now(), // Cache buster
                        },
                        withCredentials: true,
                        timeout: 10000,
                    },
                );

                console.log("📦 API Response:", response.data);

                // CRITICAL: Ensure it's a number
                const newCount = parseInt(response.data.count, 10) || 0;

                console.log(
                    "✅ Parsed count:",
                    newCount,
                    "Type:",
                    typeof newCount,
                );

                // Force Vue to detect the change
                this.$set(this, "newScannedCount", newCount);

                console.log(
                    "🎯 After $set, newScannedCount =",
                    this.newScannedCount,
                );

                // Double-force the update
                this.$nextTick(() => {
                    this.$forceUpdate();
                    console.log(
                        "🔄 Force update complete, count is now:",
                        this.newScannedCount,
                    );
                });

                return newCount;
            } catch (error) {
                console.error("❌ Error fetching new scanned count:", error);
                // Don't reset to 0 on error
                if (
                    this.newScannedCount === undefined ||
                    this.newScannedCount === null
                ) {
                    this.$set(this, "newScannedCount", 0);
                }
                return this.newScannedCount;
            }
        },

        // Add this method to refresh the notification count after scanning
        async refreshNewScannedCount() {
            try {
                // FIXED: Use US timezone for date calculation
                const now = new Date();
                const usDate = new Date(
                    now.toLocaleString("en-US", {
                        timeZone: "America/Los_Angeles",
                    }),
                );
                const today = usDate.toISOString().split("T")[0];

                console.log("Refreshing new scanned count for US date:", today);

                const response = await axios.get(
                    `${API_BASE_URL}/api/stockroom/new-scanned-count`,
                    {
                        params: { date: today },
                        withCredentials: true,
                        timeout: 10000,
                    },
                );

                // FIXED: Ensure count is always a number
                const newCount = parseInt(response.data.count) || 0;
                this.newScannedCount = newCount;

                console.log(
                    "Refreshed new scanned count to:",
                    this.newScannedCount,
                );
            } catch (error) {
                console.error("Error refreshing new scanned count:", error);
                // Don't change the count on error - prevents badge from disappearing
            }
        },

        // Simplified modal methods for new modal integration
        openNewScannedModal() {
            this.showNewScannedModal = true;
        },

        closeNewScannedModal() {
            this.showNewScannedModal = false;
        },

        // Handler for when modal updates the count
        handleCountUpdate() {
            this.refreshNewScannedCount();
        },

        // ds700s
        openDs7Oos() {
            this.showDs7Oos = true;
        },
        handleDs7OosSave(payload) {
            // persist settings / call API, then close
            console.log("Saving DS7 & OOS settings:", payload);
            this.ui.ds7oos.show = false;
        },
        fnskuSummaryFor(f) {
            const key = this.normalizeFnsku(f.FNSKU || f);
            return this.fnskuSummaries[key] || {};
        },
        async loadFnskuSummary(fnsku, location) {
            const key = this.normalizeFnsku(fnsku);
            if (this.fnskuSummaries[key]) return; // cache-hit

            const resp = await axios.get(
                `${API_BASE_URL}/api/stockroom/products/by-fnsku`,
                {
                    params: {
                        per_page: 1,
                        search: key,
                        location: location || this.selectedStore || "Stockroom",
                    },
                    withCredentials: true,
                },
            );

            const row = (resp.data?.data || [])[0];
            if (row) this.$set(this.fnskuSummaries, key, row);
        },

        async moveBackToLabeling() {
            if (!this.hasSelectedItems) {
                await Swal.fire({
                    icon: "warning",
                    title: "No Selection",
                    text: "Please select items to move back to Labeling",
                    confirmButtonColor: "#3085d6",
                });
                return;
            }

            // Confirm before moving with SweetAlert
            const result = await Swal.fire({
                title: "Confirm Move",
                html: `Move <strong>${this.selectedItems.length}</strong> item(s) back to Labeling?<br><br>This will reset their validation status to pending.`,
                icon: "question",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Yes, Move",
                cancelButtonText: "Cancel",
            });

            if (!result.isConfirmed) {
                return;
            }

            // Show loading indicator
            Swal.fire({
                title: "Moving Items...",
                html: "Please wait while we move the items back to Labeling",
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                },
            });

            this.isMovingToLabeling = true;

            try {
                const response = await axios.post(
                    `${API_BASE_URL}/api/stockroom/move-back-to-labeling`,
                    {
                        itemIds: this.selectedItems,
                        reason: this.processNotes || null,
                    },
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                        },
                    },
                );

                if (response.data.success) {
                    await Swal.fire({
                        icon: "success",
                        title: "Success!",
                        text: response.data.message,
                        confirmButtonColor: "#3085d6",
                    });

                    // Clear selection and close modal
                    this.selectedItems = [];
                    this.showProcessModal = false;

                    // Refresh inventory with force fresh to clear cache
                    await this.fetchInventory(true);
                } else {
                    throw new Error(
                        response.data.message || "Failed to move items",
                    );
                }
            } catch (error) {
                console.error("Error moving items to Labeling:", error);

                await Swal.fire({
                    icon: "error",
                    title: "Error",
                    text:
                        error.response?.data?.message ||
                        "Failed to move items back to Labeling",
                    confirmButtonColor: "#3085d6",
                });
            } finally {
                this.isMovingToLabeling = false;
            }
        },
    },
    watch: {
        searchQuery() {
            this.currentPage = 1;
            this.fetchInventory();
        },
        // Watch for changes to selectedItems to update location field
        selectedItems(newValue) {
            // If exactly one item is selected, try to get its current location
            if (
                newValue.length === 1 &&
                this.currentProcessItem &&
                this.currentProcessItem.serials
            ) {
                const selectedSerial = this.currentProcessItem.serials.find(
                    (serial) => serial.ProductID === newValue[0],
                );
                if (selectedSerial) {
                    this.processLocation =
                        selectedSerial.warehouselocation || "";
                }
            } else if (newValue.length > 1) {
                // Clear location when multiple items are selected
                this.processLocation = "";
            }
        },
    },
    mounted() {
        // Configure axios
        axios.defaults.baseURL = window.location.origin;
        axios.defaults.withCredentials = true;

        // Set CSRF token
        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) {
            axios.defaults.headers.common["X-CSRF-TOKEN"] =
                token.getAttribute("content");
        }

        // Add Font Awesome if not already included
        if (!document.querySelector('link[href*="font-awesome"]')) {
            const fontAwesome = document.createElement("link");
            fontAwesome.rel = "stylesheet";
            fontAwesome.href =
                "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css";
            document.head.appendChild(fontAwesome);
        }

        // Set the default image to our SVG
        this.defaultImagePath = this.createDefaultImageSVG();

        // Fetch stores for dropdown
        this.fetchStores();

        // Fetch initial data
        this.fetchInventory();

        // NEW SCANNED ITEMS - Fetch initial count and set up refresh
        this.fetchCountWithRetry();

        // FIXED: Set up interval with error handling and US timezone awareness
        this.countRefreshInterval = setInterval(async () => {
            try {
                await this.refreshNewScannedCount();
            } catch (error) {
                console.error("Scheduled count refresh failed:", error);
                // Try with retry mechanism as fallback
                this.fetchCountWithRetry(2);
            }
        }, 30000); // Every 30 seconds

        // NEW: Set up daily reset at midnight US time
        this.setupDailyReset();

        // Listen for window resize to update isMobile
        window.addEventListener("resize", this.handleResize);

        // Initialize serialDropdowns
        this.inventory.forEach((_, index) => {
            this.$set(this.serialDropdowns, index, false);
        });

        // Close dropdowns when clicking outside
        document.addEventListener("click", this.closeDropdownsOnClickOutside);
    },
    beforeUnmount() {
        // Clean up any timeouts
        if (this.autoVerifyTimeout) {
            clearTimeout(this.autoVerifyTimeout);
        }

        // Clear the refresh interval for new scanned count
        if (this.countRefreshInterval) {
            clearInterval(this.countRefreshInterval);
        }

        window.removeEventListener("resize", this.handleResize);
        document.removeEventListener(
            "click",
            this.closeDropdownsOnClickOutside,
        );
    },
};
