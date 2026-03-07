import { eventBus } from "../../components/eventbus";
import ScannerComponent from "../../components/Scanner.vue";
import { SoundService } from "../../components/Sound_service";
import "../../../css/modules.css";
import "./returnscanner.css";
import { DEFAULT_IMAGE } from "../../constant";
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "ReturnScannerModule",
    components: { ScannerComponent },
    data() {
        return {
            inventory: [],
            returnHistory: [],
            stores: [],
            loading: true,
            selectedStore: "",
            sortColumn: "",
            sortOrder: "asc",
            showDetails: false,
            expandedRows: {},
            serialDropdowns: {},
            returnId: "",
            serialNumber: "",
            locationInput: "",
            showManualInput: false,
            
            // Multi-serial tracking
            isMultiSerial: false,
            totalSerials: 1,
            dualSerialProduct: false,
            
            // Serial 2
            secondSerialNumber: "",
            secondSerialLabel: "Second Serial",
            showSecondSerialInput: false,
            
            // Serial 3
            thirdSerialNumber: "",
            thirdSerialLabel: "Third Serial",
            showThirdSerialInput: false,
            
            // Serial 4
            fourthSerialNumber: "",
            fourthSerialLabel: "Fourth Serial",
            showFourthSerialInput: false,
            
            scannedSerialPosition: null,
            productId: null,
            fnskuViewer: "",
            asin: "",
            originalProductLocation: "",
            showReturnIdField: false,
            autoVerifyTimeout: null,
            defaultImagePath: DEFAULT_IMAGE,
            showImageModal: false,
            modalImages: [],
            currentImageIndex: 0,
            viewDetailsModal: false,
            item: {},
            basePath: "/images/thumbnails/",
            
            // Capture flow states:
            // 0 = Input mode (show serial inputs + location)
            // 1 = Capturing for serial 1
            // 2 = Capturing for serial 2
            // 3 = Capturing for serial 3
            // 4 = Capturing for serial 4
            currentCaptureStep: 0,
            
            capturedImagesForSerial1: [],
            capturedImagesForSerial2: [],
            capturedImagesForSerial3: [],
            capturedImagesForSerial4: [],
            
            // Track which serials have been "processed" (capture done/skipped)
            serial1CaptureComplete: false,
            serial2CaptureComplete: false,
            serial3CaptureComplete: false,
            serial4CaptureComplete: false,
            
            allSerials: [],
            otherSerials: [],
            maxImagesPerSerial: 12,


            regularImages: [],
            capturedImages: [],
            activeTab: 'regular',
            currentImageSet: [],
            ProductTitle: '',

            isProcessing: false,       // Flag to prevent multiple simultaneous scans
            lastScanTime: 0,           // Timestamp of last scan
            scanCooldown: 2000, 

            //pagination
            currentPage: 1,
            totalRecords: 0,
            perPage: 10,
            first: 0 //paginator internal state
        
        };
    },
    computed: {
        searchQuery() { return eventBus.searchQuery; },
        sortedInventory() {
            if (!this.sortColumn) return this.inventory;
            return [...this.inventory].sort((a, b) => {
                const vA = a[this.sortColumn], vB = b[this.sortColumn];
                if (typeof vA === "number" && typeof vB === "number") return this.sortOrder === "asc" ? vA - vB : vB - vA;
                return this.sortOrder === "asc" ? String(vA || "").localeCompare(String(vB || "")) : String(vB || "").localeCompare(String(vA || ""));
            });
        },
        isMobile() { return window.innerWidth <= 768; },
        hasIdentifier() { return this.serialNumber.trim() !== "" || this.returnId.trim() !== ""; },
        
        // Check if all required captures are complete
        allCapturesComplete() {
            // Serial 1 must always be complete
            if (!this.serial1CaptureComplete) return false;
            // Check other serials only if they're shown and have values
            if (this.showSecondSerialInput && this.secondSerialNumber?.trim() && !this.serial2CaptureComplete) return false;
            if (this.showThirdSerialInput && this.thirdSerialNumber?.trim() && !this.serial3CaptureComplete) return false;
            if (this.showFourthSerialInput && this.fourthSerialNumber?.trim() && !this.serial4CaptureComplete) return false;
            return true;
        },
    },
    methods: {
        
            handleShowDetailsModal(item) {
        this.item = item;
        this.viewDetailsModal = true;
    },

    isDuplicateSerial(value, ownIndex) {
    if (!value?.trim()) return false;
    const v = value.trim().toLowerCase();
    return [
        { i: 1, v: this.serialNumber },
        { i: 2, v: this.secondSerialNumber },
        { i: 3, v: this.thirdSerialNumber },
        { i: 4, v: this.fourthSerialNumber },
    ].some(s => s.i !== ownIndex && s.v && s.v.trim().toLowerCase() === v);
},

// Blocks processScan if any two serials are identical
hasDuplicateSerials() {
    const serials = this.getActiveSerials().map(s => s.trim().toLowerCase());
    return new Set(serials).size !== serials.length;
},



    
    // ADD THIS METHOD - View return details (used in mobile view)
    viewReturnDetails(item) {
        if (!item) return;
        
        const rtNumber = item.rtcounter ? this.formatRTNumber(item.rtcounter, item.storename || "") : "N/A";
        const returnId = item.LPN || "N/A";
        const returnDate = this.formatDate(item.LPNDATE || null);
        const serial = item.serialnumber || "N/A";
        const secondSerial = item.serialnumberb || "";
        const location = item.warehouselocation || "Floor";
        const status = this.formatStatus(item.returnstatus || "unknown");
        const fnsku = item.FNSKUviewer || "N/A";
        const asin = item.ASINviewer || "N/A";
        const buyer = item.BuyerName || item.costumer_name || "Unknown";
        
        alert(
            `Return Details\n` +
            `RT#: ${rtNumber}\n` +
            `Return ID: ${returnId}\n` +
            `Return Date: ${returnDate}\n` +
            `Serial: ${serial}\n` +
            `${secondSerial ? "Second Serial: " + secondSerial + "\n" : ""}` +
            `Location: ${location}\n` +
            `Status: ${status}\n` +
            `FNSKU: ${fnsku}\n` +
            `ASIN: ${asin}\n` +
            `Buyer: ${buyer}`
        );
    },
    
    // ADD THIS METHOD - Toggle details visibility
    toggleDetails(index) {
        const updatedExpandedRows = { ...this.expandedRows };
        updatedExpandedRows[index] = !updatedExpandedRows[index];
        this.expandedRows = updatedExpandedRows;
    },
    
    // ADD THIS METHOD - Toggle details visibility (global)
    toggleDetailsVisibility() {
        this.showDetails = !this.showDetails;
    },
    
    // ADD THIS METHOD - Sort by column
    sortBy(column) {
        if (this.sortColumn === column) {
            this.sortOrder = this.sortOrder === "asc" ? "desc" : "asc";
        } else {
            this.sortColumn = column;
            this.sortOrder = "asc";
        }
    },
    
    // ADD THIS METHOD - Toggle all selection
    toggleAll() {
        this.inventory.forEach((item) => (item.checked = this.selectAll));
    },
    
    // ADD THIS METHOD - Clear return ID
    clearReturnId() {
        this.returnId = "";
        this.$refs.returnIdInput?.focus();
        SoundService?.click?.() || SoundService?.success?.();
    },
    
    // ADD THIS METHOD - Transform data for gallery
    transformDataForGallery(data) {
        if (!data) return {};
        
        if (data.capturedImages && data.capturedImages.capturedimg1) {
            const transformedData = { ...data };
            const companyFolder = data.company || "Airstaffs";
            
            for (let i = 1; i <= 12; i++) {
                const capturedImg = data.capturedImages[`capturedimg${i}`];
                if (capturedImg) {
                    transformedData[`img${i}`] = `/images/product_images/${companyFolder}/${capturedImg}`;
                } else {
                    transformedData[`img${i}`] = null;
                }
            }
            
            for (let i = 13; i <= 15; i++) {
                transformedData[`img${i}`] = null;
            }
            
            return transformedData;
        }
        
        return data;
    },
    
    // ADD THIS METHOD - Count additional images
    countAdditionalImages(item) {
        if (!item) return 0;
        
        let count = 0;
        for (let i = 2; i <= 15; i++) {
            const fieldName = `img${i}`;
            if (item[fieldName] && item[fieldName] !== "NULL" && item[fieldName].trim() !== "") {
                count++;
            }
        }
        
        return count;
    },
    
    // ADD THIS METHOD - Get FNSKU display title
    getFnskuDisplayTitle(fnskuItem) {
        if (!fnskuItem) return "—";
        
        if (fnskuItem.astitle && fnskuItem.astitle.trim() !== "") {
            return fnskuItem.astitle;
        }
        
        if (fnskuItem.system_title && fnskuItem.system_title.trim() !== "") {
            return fnskuItem.system_title;
        }
        
        if (fnskuItem.internal && fnskuItem.internal.trim() !== "") {
            return fnskuItem.internal;
        }
        
        return "—";
    },

        handleImageError(e) { e.target.src = this.defaultImagePath; e.target.onerror = null; },
        isValidImage(path) { return path && path !== "NULL" && path.trim() !== ""; },
        
        countImages(item, prefix, start, end, container = null) {
            if (!item) return 0;
            const src = container ? item[container] : item;
            if (!src) return 0;
            let c = 0;
            for (let i = start; i <= end; i++) if (this.isValidImage(src[`${prefix}${i}`])) c++;
            return c;
        },
        countRegularImages(item) { return this.countImages(item, "img", 2, 15); },
        countCapturedImages(item) {
            if (!item?.capturedImages) return 0;
            let c = 0;
            for (let i = 1; i <= 12; i++) if (this.isValidImage(item.capturedImages[`capturedimg${i}`])) c++;
            if (this.isValidImage(item.capturedImages.serialimg1)) c++;
            if (this.isValidImage(item.capturedImages.serialimg2)) c++;
            return c;
        },
        countAllImages(item) {
            if (!item) return 0;
            if (item.capturedImages) {
                let c = 0;
                for (let i = 1; i <= 12; i++) if (this.isValidImage(item.capturedImages[`capturedimg${i}`])) c++;
                if (c > 0) return c;
            }
            return this.countRegularImages(item);
        },
        
        openImageModal(item) {
            if (!item) return;
            this.regularImages = [];
            this.capturedImages = [];
            this.currentImageIndex = 0;
            this.ProductTitle = item.ProductTitle;
            const folder = item.company || "Airstaffs";
            for (let i = 1; i <= 15; i++) if (this.isValidImage(item[`img${i}`])) this.regularImages.push(`/images/thumbnails/${item[`img${i}`]}`);
            if (item.capturedImages && typeof item.capturedImages === "object") {
                for (let i = 1; i <= 12; i++) if (this.isValidImage(item.capturedImages[`capturedimg${i}`])) this.capturedImages.push(`/images/product_images/${folder}/${item.capturedImages[`capturedimg${i}`]}`);
                if (this.isValidImage(item.capturedImages.serialimg1)) this.capturedImages.push(`/images/product_images/${folder}/${item.capturedImages.serialimg1}`);
                if (this.isValidImage(item.capturedImages.serialimg2)) this.capturedImages.push(`/images/product_images/${folder}/${item.capturedImages.serialimg2}`);
            }
            if (!this.regularImages.length && !this.capturedImages.length) this.regularImages.push(this.defaultImage);
            this.activeTab = this.regularImages.length ? "regular" : "captured";
            this.currentImageSet = this.activeTab === "regular" ? this.regularImages : this.capturedImages;
            this.showImageModal = true;
            document.body.style.overflow = "hidden";
        },
        switchTab(tab) { this.activeTab = tab; this.currentImageIndex = 0; this.currentImageSet = tab === "regular" ? this.regularImages : this.capturedImages; },
        closeImageModal() { this.showImageModal = false; this.currentImageSet = []; this.regularImages = []; this.capturedImages = []; document.body.style.overflow = "auto"; },
        nextImage() { this.currentImageIndex = this.currentImageIndex < this.currentImageSet.length - 1 ? this.currentImageIndex + 1 : 0; },
        prevImage() { this.currentImageIndex = this.currentImageIndex > 0 ? this.currentImageIndex - 1 : this.currentImageSet.length - 1; },
        openScannerModal() { this.$refs.scanner.openScannerModal(); },
        
        toggleReturnIdField() {
            this.showReturnIdField = !this.showReturnIdField;
            if (!this.showReturnIdField) this.returnId = "";
            else this.$nextTick(() => this.$refs.returnIdInput?.focus());
        },
        
        formatDate(ds) { if (!ds) return "N/A"; try { const d = new Date(ds); return d.toLocaleDateString() + " " + d.toLocaleTimeString(); } catch { return "Invalid Date"; } },
        formatStatus(s) { return { pending: "Pending", processed: "Processed", rejected: "Rejected", returned: "Returned", missing: "Not Found" }[s] || s || "Unknown"; },
        formatRTNumber(rt, store) { if (!rt) return "N/A"; const p = String(rt).padStart(5, "0"); return store === "RenovarTech" ? `RT ${p}` : store === "Allrenewed" ? `AR ${p}` : `#${p}`; },
        
        async fetchStores() { this.loading = true; try { const r = await axios.get(`${API_BASE_URL}/api/returns/stores`, { withCredentials: true }); this.stores = r.data; } catch (e) { console.error(e); } finally { this.loading = false; } },
        changeStore() { this.currentPage = 1; this.fetchInventory(); },
          onPageChange(event) {
            this.first = event.first
            this.currentPage = event.page + 1; // convert to 1-based
            this.perPage     = event.rows;
            this.fetchInventory();
        },

        
        getDisplayTitle(item) {
            if (!item) return "—";
            return item.system_title?.trim() || item.internal?.trim() || item.AStitle?.trim() || item.ProductTitle?.trim() || "—";
        },
        
async fetchInventory() {
    this.loading = true;
    try {
        const r = await axios.get(`${API_BASE_URL}/api/returns/products`, { 
            params: { 
                search: this.searchQuery, 
                page: this.currentPage, 
                per_page: this.perPage, 
                location: "Returnlist",  // ✅ CRITICAL: Must be "Returnlist"
                include_images: true 
            }, 
            withCredentials: true 
        });
        
        // ✅✅✅ CRITICAL: Must populate BOTH arrays
        this.inventory = r.data.data;
        this.returnHistory = r.data.data.map(i => ({ 
            ...i, 
            capturedImages: i.capturedImages || {} 
        }));
        this.totalRecords = r.data.total;
        
        console.log("✅ Inventory loaded:", {
            items: this.inventory.length,
            historyItems: this.returnHistory.length,
            totalRecords: this.totalRecords
        });
        
    } catch (e) { 
        console.error("❌ Error loading inventory:", e); 
        this.inventory = []; 
        this.returnHistory = []; 
        this.totalRecords = 0; 
    } finally { 
        this.loading = false; 
    }
},

        // ========== MULTI-SERIAL DETECTION ==========
        async checkDualSerial() {
            if (!this.serialNumber) return false;
            try {
                this.$refs.scanner?.startLoading("Checking product...");
                const r = await axios.get(`${API_BASE_URL}/api/returns/check-serial`, { params: { serial: this.serialNumber }, withCredentials: true });
                this.$refs.scanner?.stopLoading();

                if (r.data.success) {
                    this.productId = r.data.productId || null;
                    this.fnskuViewer = r.data.fnskuViewer || "";
                    this.scannedSerialPosition = r.data.scannedSerialPosition || null;
                    this.isMultiSerial = r.data.isMultiSerial || false;
                    this.totalSerials = r.data.totalSerials || 1;
                    this.otherSerials = r.data.otherSerials || [];

                    console.log("✅ Product check result:", {
                        isMultiSerial: this.isMultiSerial,
                        totalSerials: this.totalSerials,
                        otherSerials: this.otherSerials
                    });

                    const isValid = s => s && String(s).trim() !== '' && String(s).toUpperCase() !== 'N/A';

                    // Reset all serial states
                    this.dualSerialProduct = false;
                    this.secondSerialNumber = ""; this.showSecondSerialInput = false;
                    this.thirdSerialNumber = ""; this.showThirdSerialInput = false;
                    this.fourthSerialNumber = ""; this.showFourthSerialInput = false;

                    if (this.isMultiSerial && this.otherSerials.length > 0) {
                        this.dualSerialProduct = true;
                        
                        this.otherSerials.forEach((s, i) => {
                            if (i === 0 && isValid(s.value)) { 
                                this.secondSerialNumber = s.value; 
                                this.secondSerialLabel = s.label || "Second Serial"; 
                                this.showSecondSerialInput = true;
                                console.log(`📌 Serial 2: ${s.value}`);
                            }
                            else if (i === 1 && isValid(s.value)) { 
                                this.thirdSerialNumber = s.value; 
                                this.thirdSerialLabel = s.label || "Third Serial"; 
                                this.showThirdSerialInput = true;
                                console.log(`📌 Serial 3: ${s.value}`);
                            }
                            else if (i === 2 && isValid(s.value)) { 
                                this.fourthSerialNumber = s.value; 
                                this.fourthSerialLabel = s.label || "Fourth Serial"; 
                                this.showFourthSerialInput = true;
                                console.log(`📌 Serial 4: ${s.value}`);
                            }
                        });
                        
                        // Highlight second serial input
                        this.$nextTick(() => {
                            if (this.$refs.secondSerialInput) {
                                this.$refs.secondSerialInput.classList.add("highlight-input");
                                this.$refs.secondSerialInput.select();
                                setTimeout(() => this.$refs.secondSerialInput?.classList.remove("highlight-input"), 3000);
                            }
                        });
                        
                        SoundService?.notification?.();
                        return true;
                    }
                    return false;
                }
                this.resetMultiSerialState();
                return false;
            } catch (e) { 
                console.error(e); 
                this.$refs.scanner?.stopLoading(); 
                this.resetMultiSerialState(); 
                return false; 
            }
        },

        resetMultiSerialState() {
            this.productId = null; 
            this.fnskuViewer = ""; 
            this.scannedSerialPosition = null;
            this.isMultiSerial = false; 
            this.totalSerials = 1; 
            this.dualSerialProduct = false;
            this.secondSerialNumber = ""; 
            this.thirdSerialNumber = ""; 
            this.fourthSerialNumber = "";
            this.showSecondSerialInput = false; 
            this.showThirdSerialInput = false; 
            this.showFourthSerialInput = false;
            this.allSerials = []; 
            this.otherSerials = [];
            this.serial1CaptureComplete = false;
            this.serial2CaptureComplete = false;
            this.serial3CaptureComplete = false;
            this.serial4CaptureComplete = false;
        },

        // ========== INPUT HANDLERS ==========
        async handleReturnIdInput() {
            if (!this.showManualInput && this.returnId.trim().length > 5) {
                clearTimeout(this.autoVerifyTimeout);
                this.autoVerifyTimeout = setTimeout(() => { 
                    SoundService?.success?.(); 
                    this.focusNextField("serialNumberInput"); 
                }, 500);
            }
        },
        
       async handleSerialInput() {
        if (!/^[a-zA-Z0-9-]*$/.test(this.serialNumber.trim())) {
            this.$refs.scanner?.showScanError("Invalid Serial Number format");
            this.$refs.serialNumberInput?.select();
            SoundService?.error?.();
            return;
        }
        
        if (this.autoVerifyTimeout) {
            clearTimeout(this.autoVerifyTimeout);
            this.autoVerifyTimeout = null;
        }
        
        if (!this.showManualInput && this.serialNumber.trim().length > 5) {
            this.autoVerifyTimeout = setTimeout(async () => {
                await this.checkDualSerial();
          //      SoundService?.success?.(); // ✅ KEEP THIS - just a sound
                this.proceedToImageCapture(1);
                this.autoVerifyTimeout = null;
            }, 500);
        }
    },
        
        handleSecondSerialInput() {
            if (!/^[a-zA-Z0-9-]*$/.test(this.secondSerialNumber.trim())) { 
                this.$refs.scanner?.showScanError("Invalid format"); 
                this.$refs.secondSerialInput?.select(); 
                SoundService?.error?.(); 
                return; 
            }
            // Auto-proceed to capture when scanned
            if (!this.showManualInput && this.secondSerialNumber.trim().length > 5) {
                clearTimeout(this.autoVerifyTimeout);
                this.autoVerifyTimeout = setTimeout(() => {
                    SoundService?.success?.();
                    this.proceedToImageCapture(2);
                }, 500);
            }
        },
        
        handleThirdSerialInput() {
            if (!/^[a-zA-Z0-9-]*$/.test(this.thirdSerialNumber.trim())) { 
                this.$refs.scanner?.showScanError("Invalid format"); 
                this.$refs.thirdSerialInput?.select(); 
                SoundService?.error?.(); 
                return; 
            }
            if (!this.showManualInput && this.thirdSerialNumber.trim().length > 5) {
                clearTimeout(this.autoVerifyTimeout);
                this.autoVerifyTimeout = setTimeout(() => {
                    SoundService?.success?.();
                    this.proceedToImageCapture(3);
                }, 500);
            }
        },
        
        handleFourthSerialInput() {
            if (!/^[a-zA-Z0-9-]*$/.test(this.fourthSerialNumber.trim())) { 
                this.$refs.scanner?.showScanError("Invalid format"); 
                this.$refs.fourthSerialInput?.select(); 
                SoundService?.error?.(); 
                return; 
            }
            if (!this.showManualInput && this.fourthSerialNumber.trim().length > 5) {
                clearTimeout(this.autoVerifyTimeout);
                this.autoVerifyTimeout = setTimeout(() => {
                    SoundService?.success?.();
                    this.proceedToImageCapture(4);
                }, 500);
            }
        },
        
       handleLocationInput() {
        const loc = this.locationInput.trim();
        const valid = /^L\d{3}[A-G]$/i.test(loc) || loc === "Floor" || loc === "L800G";
        
        if (!valid && loc !== "") { 
            this.$refs.scanner?.showScanError("Invalid Location (L###X, Floor, or L800G)"); 
            this.$refs.locationInput?.select(); 
            SoundService?.error?.(); 
            return; 
        }
        
        if (this.autoVerifyTimeout) {
            clearTimeout(this.autoVerifyTimeout);
            this.autoVerifyTimeout = null;
        }
        
        if (!this.showManualInput && valid && loc.length > 0) {
            this.autoVerifyTimeout = setTimeout(() => { 
                SoundService?.success?.(); // ✅ KEEP THIS - just a sound
                this.processScan();
                this.autoVerifyTimeout = null;
            }, 500);
        }
    },
        
        focusNextField(ref) { this.$nextTick(() => this.$refs[ref]?.focus()); },

        // ========== IMAGE CAPTURE FLOW ==========
        proceedToImageCapture(n) {
            const serials = { 
                1: this.serialNumber, 
                2: this.secondSerialNumber, 
                3: this.thirdSerialNumber, 
                4: this.fourthSerialNumber 
            };
            
            if (!serials[n]?.trim()) { 
                this.$refs.scanner?.showScanError(`Enter serial ${n} first`); 
                SoundService?.error?.(); 
                return; 
            }
            
            console.log(`🎬 Start capture for Serial ${n}: ${serials[n]}`);
            this.currentCaptureStep = n;
            
            // Clear scanner's captured images for fresh capture
            if (this.$refs.scanner) this.$refs.scanner.capturedImages = [];
            SoundService?.success?.();
        },

        skipImageCapture(n) {
            console.log(`⏭️ Skip images for Serial ${n} (serial will still be processed)`);
            
            // Clear any captured images
            if (this.$refs.scanner) this.$refs.scanner.capturedImages = [];
            
            // Mark capture as complete with 0 images
            if (n === 1) { this.capturedImagesForSerial1 = []; this.serial1CaptureComplete = true; }
            if (n === 2) { this.capturedImagesForSerial2 = []; this.serial2CaptureComplete = true; }
            if (n === 3) { this.capturedImagesForSerial3 = []; this.serial3CaptureComplete = true; }
            if (n === 4) { this.capturedImagesForSerial4 = []; this.serial4CaptureComplete = true; }

            // Go back to input mode
            this.currentCaptureStep = 0;

            
            // Focus on next uncaptured serial or location
            this.$nextTick(() => this.focusNextInput(n));
            SoundService?.success?.();
        },

        finishImageCapture(n) {
            console.log(`✅ Finish capture for Serial ${n}`);
            
            const imgs = this.$refs.scanner?.capturedImages || [];
            const serials = { 
                1: this.serialNumber, 
                2: this.secondSerialNumber, 
                3: this.thirdSerialNumber, 
                4: this.fourthSerialNumber 
            };
            
            const processed = imgs.map(img => ({ 
                ...img, 
                serialIndex: n, 
                serial: img.serial || serials[n] 
            }));

            // Store images and mark complete
            if (n === 1) { this.capturedImagesForSerial1 = processed; this.serial1CaptureComplete = true; }
            if (n === 2) { this.capturedImagesForSerial2 = processed; this.serial2CaptureComplete = true; }
            if (n === 3) { this.capturedImagesForSerial3 = processed; this.serial3CaptureComplete = true; }
            if (n === 4) { this.capturedImagesForSerial4 = processed; this.serial4CaptureComplete = true; }

            // Clear scanner images
            if (this.$refs.scanner) this.$refs.scanner.capturedImages = [];
            
            // Go back to input mode
            this.currentCaptureStep = 0;
            
            // Focus on next uncaptured serial or location
            this.$nextTick(() => this.focusNextInput(n));
            SoundService?.success?.();
        },
        
        // Focus on the next input that needs attention
        focusNextInput(afterSerial) {
            // After serial 1, check if serial 2 needs capture
            if (afterSerial === 1 && this.showSecondSerialInput && this.secondSerialNumber?.trim() && !this.serial2CaptureComplete) {
                this.$refs.secondSerialInput?.focus();
                this.$refs.secondSerialInput?.select();
                return;
            }
            // After serial 2, check serial 3
            if (afterSerial <= 2 && this.showThirdSerialInput && this.thirdSerialNumber?.trim() && !this.serial3CaptureComplete) {
                this.$refs.thirdSerialInput?.focus();
                this.$refs.thirdSerialInput?.select();
                return;
            }
            // After serial 3, check serial 4
            if (afterSerial <= 3 && this.showFourthSerialInput && this.fourthSerialNumber?.trim() && !this.serial4CaptureComplete) {
                this.$refs.fourthSerialInput?.focus();
                this.$refs.fourthSerialInput?.select();
                return;
            }
            // All done, focus location
            this.$refs.locationInput?.focus();
        },

        // ========== HIDE SERIAL (X button) - Don't return this serial ==========
            async hideSecondSerial() {
                const result = await Swal.fire({
                    title: 'Remove Serial?',
                    text: `Remove "${this.secondSerialNumber}" from this return?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#f44336',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Remove',
                    cancelButtonText: 'Cancel',
                });
                if (!result.isConfirmed) return;
                console.log("❌ User chose NOT to return Serial 2");
                this.secondSerialNumber = "";
                this.capturedImagesForSerial2 = [];
                this.showSecondSerialInput = false;
                this.serial2CaptureComplete = false;
                this.currentCaptureStep = 0;
                if (this.$refs.scanner) this.$refs.scanner.capturedImages = [];
                this.$nextTick(() => this.focusNextInput(2));
                SoundService?.success?.();
            },
        
                async hideThirdSerial() {
                const result = await Swal.fire({
                    title: 'Remove Serial?',
                    text: `Remove "${this.thirdSerialNumber}" from this return?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#f44336',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Remove',
                    cancelButtonText: 'Cancel',
                });
                if (!result.isConfirmed) return;
                console.log("❌ User chose NOT to return Serial 3");
                this.thirdSerialNumber = "";
                this.capturedImagesForSerial3 = [];
                this.showThirdSerialInput = false;
                this.serial3CaptureComplete = false;
                this.currentCaptureStep = 0;
                if (this.$refs.scanner) this.$refs.scanner.capturedImages = [];
                this.$nextTick(() => this.focusNextInput(3));
                SoundService?.success?.();
            },
                                
            async hideFourthSerial() {
                const result = await Swal.fire({
                    title: 'Remove Serial?',
                    text: `Remove "${this.fourthSerialNumber}" from this return?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#f44336',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Remove',
                    cancelButtonText: 'Cancel',
                });
                if (!result.isConfirmed) return;
                console.log("❌ User chose NOT to return Serial 4");
                this.fourthSerialNumber = "";
                this.capturedImagesForSerial4 = [];
                this.showFourthSerialInput = false;
                this.serial4CaptureComplete = false;
                this.currentCaptureStep = 0;
                if (this.$refs.scanner) this.$refs.scanner.capturedImages = [];
                this.$nextTick(() => this.$refs.locationInput?.focus());
                SoundService?.success?.();
            },

        // ========== HELPER: Get active serials for display ==========
        getActiveSerials() {
            const serials = [];
            if (this.serialNumber?.trim()) serials.push(this.serialNumber);
            if (this.secondSerialNumber?.trim()) serials.push(this.secondSerialNumber);
            if (this.thirdSerialNumber?.trim()) serials.push(this.thirdSerialNumber);
            if (this.fourthSerialNumber?.trim()) serials.push(this.fourthSerialNumber);
            return serials;
        },

        // ========== PROCESS SCAN ==========
            async processScan() {
                console.log("🚀 PROCESS SCAN");

                if (this.hasDuplicateSerials()) {
                    this.$refs.scanner?.showScanError("Duplicate serial detected — please re-scan");
                    SoundService?.error?.();
                    return;
                }
                
                // ✅ Prevent duplicate scans
                if (this.isProcessing) {
                    console.warn("⚠️ Scan already in progress, ignoring duplicate");
                    return;
                }
                
                const now = Date.now();
                if (now - this.lastScanTime < this.scanCooldown) {
                    console.warn("⚠️ Scan too soon after last scan, ignoring");
                    this.$refs.scanner?.showScanError("Please wait before scanning again");
                    return;
                }
                
                this.isProcessing = true;
                this.lastScanTime = now;
                
                try {
                    // Collect all serials that have values
                    const serials = [];
                    if (this.serialNumber?.trim()) {
                        serials.push({ i: 1, v: this.serialNumber, imgs: this.capturedImagesForSerial1 });
                    }
                    if (this.secondSerialNumber?.trim()) {
                        serials.push({ i: 2, v: this.secondSerialNumber, imgs: this.capturedImagesForSerial2 });
                    }
                    if (this.thirdSerialNumber?.trim()) {
                        serials.push({ i: 3, v: this.thirdSerialNumber, imgs: this.capturedImagesForSerial3 });
                    }
                    if (this.fourthSerialNumber?.trim()) {
                        serials.push({ i: 4, v: this.fourthSerialNumber, imgs: this.capturedImagesForSerial4 });
                    }
                    
                    console.log("Serials to submit:", serials.map(s => ({ serial: s.v, images: s.imgs.length })));

                    if (!serials.length) { 
                        this.$refs.scanner?.showScanError("Serial required"); 
                        SoundService?.error?.(); 
                        return; 
                    }
                    
                    const loc = this.locationInput?.trim();
                    if (loc && !/^L\d{3}[A-G]$/i.test(loc) && loc !== "Floor" && loc !== "L800G") { 
                        this.$refs.scanner?.showScanError("Invalid location"); 
                        SoundService?.error?.(); 
                        return; 
                    }

                    // Build image data array
                    const imageData = [];
                    serials.forEach(s => s.imgs.forEach(img => imageData.push({ 
                        data: img.data, 
                        serialIndex: s.i, 
                        serial: s.v 
                    })));

                    this.$refs.scanner?.startLoading("Processing...");
                    
                    const r = await axios.post(`${API_BASE_URL}/api/returns/process-scan`, {
                        ReturnId: this.showReturnIdField ? this.returnId : null,
                        SerialNumber: this.serialNumber,
                        SecondSerial: this.secondSerialNumber || null,
                        ThirdSerial: this.thirdSerialNumber || null,
                        FourthSerial: this.fourthSerialNumber || null,
                        Location: loc || "L800G",
                        Store: this.selectedStore,
                        Images: imageData,
                        ProductID: this.productId,
                        FNSKUviewer: this.fnskuViewer,
                        TotalSerials: serials.length,
                        IsMultiSerial: serials.length > 1,
                    }, { 
                        withCredentials: true, 
                        headers: { 
                            "Content-Type": "application/json", 
                            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content 
                        } 
                    });

                    this.$refs.scanner?.stopLoading();
                    
                    if (r.data.success) {
                        // ✅ Show success message and play sound
                        this.$refs.scanner?.showScanSuccess(r.data.message || "Success");
                        SoundService?.successScan?.(true);
                        
                        // ✅ ONLY HERE: Increment success count
                        if (this.$refs.scanner && typeof this.$refs.scanner.addSuccessScan === 'function') {
                            this.$refs.scanner.addSuccessScan({
                                ReturnID: this.showReturnIdField ? this.returnId : 'N/A',
                                Serial: this.serialNumber,
                                SecondSerial: this.secondSerialNumber || 'N/A',
                                ThirdSerial: this.thirdSerialNumber || 'N/A',
                                FourthSerial: this.fourthSerialNumber || 'N/A',
                                Location: loc || "L800G",
                                FNSKU: this.fnskuViewer || 'N/A',
                                ImagesUploaded: imageData.length,
                                TotalSerials: serials.length
                            });
                            
                            console.log("✅ SUCCESS COUNT INCREMENTED");
                        }
                        
                        // ✅✅✅ CRITICAL: Upload images if any
                        if (r.data.createdItems?.length && imageData.length) {
                            console.log(`📤 Uploading ${imageData.length} images to ${r.data.createdItems.length} products`);
                            try {
                                await this.uploadImagesToProducts(r.data.createdItems, imageData);
                                console.log("✅ Images uploaded successfully");
                            } catch (uploadError) {
                                console.error("⚠️ Image upload failed:", uploadError);
                                // Don't fail the whole operation if images fail
                            }
                        }
                        
                        // ✅✅✅ CRITICAL: Clear fields BEFORE refresh
                        this.clearScanFields();
                        
                        // ✅✅✅ CRITICAL: Refresh inventory to show new items in history table
                        console.log("🔄 Refreshing inventory...");
                        await this.fetchInventory();
                        console.log("✅ Inventory refreshed - new items should appear in history");
                        
                    } else {
                        // ❌ Show error message and play sound
                        this.$refs.scanner?.showScanError(r.data.message || "Error");
                        SoundService?.scanRejected?.(true);
                        
                        // ❌ ONLY HERE: Increment failed count
                        if (this.$refs.scanner && typeof this.$refs.scanner.addErrorScan === 'function') {
                            this.$refs.scanner.addErrorScan({
                                ReturnID: this.showReturnIdField ? this.returnId : 'N/A',
                                Serial: this.serialNumber,
                                SecondSerial: this.secondSerialNumber || 'N/A',
                                ThirdSerial: this.thirdSerialNumber || 'N/A',
                                FourthSerial: this.fourthSerialNumber || 'N/A',
                                Location: loc || 'N/A',
                                FNSKU: this.fnskuViewer || 'N/A',
                            }, r.data.reason || 'error');
                            
                            console.log("❌ FAILED COUNT INCREMENTED");
                        }
                    }
                    
                } catch (e) {
                    this.$refs.scanner?.stopLoading();
                    console.error("❌ Error in processScan:", e);
                    
                    this.$refs.scanner?.showScanError(e.response?.status === 419 ? "Session expired" : "Network error");
                    SoundService?.scanRejected?.(true);
                    
                    // ❌ Network errors also increment failed count
                    if (this.$refs.scanner && typeof this.$refs.scanner.addErrorScan === 'function') {
                        this.$refs.scanner.addErrorScan({
                            ReturnID: this.showReturnIdField ? this.returnId : 'N/A',
                            Serial: this.serialNumber,
                            Location: this.locationInput || 'N/A',
                            FNSKU: this.fnskuViewer || 'N/A',
                        }, e.response?.status === 419 ? 'session_expired' : 'network_error');
                        
                        console.log("❌ FAILED COUNT INCREMENTED (network error)");
                    }
                    
                } finally {
                    // ✅ Reset processing flag after delay
                    setTimeout(() => {
                        this.isProcessing = false;
                    }, 500);
                }
                
                // ✅ Clear fields and focus
                this.clearScanFields();
                this.focusNextField(this.showReturnIdField ? "returnIdInput" : "serialNumberInput");
            },
        async uploadImagesToProducts(items, imageData) {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

            for (const item of items) {
                const imgs = imageData.filter(i => i.serial === item.serial);

                for (let i = 0; i < Math.min(imgs.length, 12); i++) {
                    try {
                        await axios.post(`${API_BASE_URL}/api/images/upload`, { 
                            _token: csrf,
                            productId: item.id,
                            imageIndex: i,
                            imageData: imgs[i].data,

                            step: 2,          // 🔥🔥🔥 ADD THIS
                            isSerial: false,
                            serialIndex: 0

                        }, { 
                            withCredentials: true,
                            headers: {
                                "Content-Type": "application/json",
                                "X-CSRF-TOKEN": csrf
                            }
                        });

                    } catch (e) {
                        console.error(`Image upload failed for ${item.serial}`, e.response?.data || e.message);
                    }
                }
            }
        },

        clearScanFields() {
            this.returnId = ""; 
            this.serialNumber = ""; 
            this.locationInput = "";
            this.secondSerialNumber = ""; 
            this.thirdSerialNumber = ""; 
            this.fourthSerialNumber = "";
            this.capturedImagesForSerial1 = []; 
            this.capturedImagesForSerial2 = []; 
            this.capturedImagesForSerial3 = []; 
            this.capturedImagesForSerial4 = [];
            this.serial1CaptureComplete = false;
            this.serial2CaptureComplete = false;
            this.serial3CaptureComplete = false;
            this.serial4CaptureComplete = false;
            this.currentCaptureStep = 0;
            this.resetMultiSerialState();
            if (this.$refs.scanner) this.$refs.scanner.capturedImages = [];
        },

        handleScanProcess() { this.processScan(); },
        handleHardwareScan(code) { this.processScan(code); },
        handleModeChange(e) { this.showManualInput = e.manual; },
        handleScannerOpened() { 
            this.showManualInput = this.$refs.scanner?.showManualInput; 
            this.clearScanFields(); 
            this.$nextTick(() => (this.showReturnIdField ? this.$refs.returnIdInput : this.$refs.serialNumberInput)?.focus()); 
        },
        handleScannerClosed() { this.fetchInventory(); },
        handleScannerReset() { this.clearScanFields(); },
        handleResize() { if (this.isMobile && Object.values(this.serialDropdowns).some(v => v)) this.serialDropdowns = {}; },
        closeDropdownsOnClickOutside(e) { if (!e.target.closest(".serial-dropdown")) this.serialDropdowns = {}; },
    },
    watch: {
        searchQuery() { this.currentPage = 1; this.first = 0; this.fetchInventory(); },
    },
    mounted() {
        axios.defaults.baseURL = window.location.origin;
        axios.defaults.withCredentials = true;
        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) axios.defaults.headers.common["X-CSRF-TOKEN"] = token.getAttribute("content");
        if (!document.querySelector('link[href*="font-awesome"]')) {
            const fa = document.createElement("link");
            fa.rel = "stylesheet";
            fa.href = "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css";
            document.head.appendChild(fa);
        }
        this.fetchStores();
        this.fetchInventory();
        window.addEventListener("resize", this.handleResize);
        document.addEventListener("click", this.closeDropdownsOnClickOutside);
        const handleKeyDown = (e) => {
            if (!this.showImageModal) return;
            if (e.key === "Escape") this.closeImageModal();
            if (e.key === "ArrowRight") this.nextImage();
            if (e.key === "ArrowLeft") this.prevImage();
        };
        window.addEventListener("keydown", handleKeyDown);
        this.handleKeyDown = handleKeyDown;
    },
    beforeUnmount() {
        if (this.autoVerifyTimeout) clearTimeout(this.autoVerifyTimeout);
        window.removeEventListener("resize", this.handleResize);
        document.removeEventListener("click", this.closeDropdownsOnClickOutside);
        if (this.handleKeyDown) window.removeEventListener("keydown", this.handleKeyDown);
    },
};