import { eventBus } from "../../components/eventBus";
import ScannerComponent from "../../components/Scanner.vue";
import { SoundService } from "../../components/Sound_service";
import "../../../css/modules.css";
import "./unreceived.css";
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "UnreceivedModule",
    components: {
        ScannerComponent,
    },
    data() {
        return {
            inventory: [],
            loading: true,
            currentPage: 1,
            totalPages: 1,
            perPage: 10,
            selectAll: false,
            expandedRows: {},
            sortColumn: "",
            sortOrder: "asc",
            showDetails: false,

            // Simplified scanner workflow - only tracking needed
            trackingNumber: "",
            trackingValid: false,
            trackingFound: false,
            productId: "",
            rtcounter: "",
            itemStatus: "", // Added for item status

            // For validation
            trackingNumberValid: true,

            // For auto verification
            autoVerifyTimeout: null,
            showManualInput: false,

            defaultImage:
                "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjZWVlIj48L3JlY3Q+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMTIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGFsaWdubWVudC1iYXNlbGluZT0ibWlkZGxlIiBmb250LWZhbWlseT0ibW9ub3NwYWNlLCBzYW5zLXNlcmlmIiBmaWxsPSIjOTk5Ij5JbWFnZTwvdGV4dD48L3N2Zz4=",
            
            // Modal state
            showImageModal: false,
            modalImages: [],
            currentImageIndex: 0,
        };
    },
    computed: {
        searchQuery() {
            return eventBus.searchQuery;
        },
        sortedInventory() {
            if (!this.sortColumn) return this.inventory;
            return [...this.inventory].sort((a, b) => {
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
    },
    methods: {
        handleImageError(event) {
            event.target.src = this.defaultImage;
            event.target.onerror = null;
        },

        openScannerModal() {
            this.$refs.scanner.openScannerModal();
        },

        countAdditionalImages(item) {
            if (!item) return 0;
            let count = 0;
            for (let i = 2; i <= 15; i++) {
                const fieldName = `img${i}`;
                if (
                    item[fieldName] &&
                    item[fieldName] !== "NULL" &&
                    item[fieldName].trim() !== ""
                ) {
                    count++;
                }
            }
            return count;
        },

        openImageModal(item) {
            if (!item) return;
            this.modalImages = [];
            this.currentImageIndex = 0;

            const imageFields = [
                "img2", "img3", "img4", "img5", "img6", "img7", "img8", 
                "img9", "img10", "img11", "img12", "img13", "img14", "img15"
            ];

            imageFields.forEach((field) => {
                if (
                    item[field] &&
                    item[field] !== "NULL" &&
                    item[field].trim() !== ""
                ) {
                    const imagePath = `/images/thumbnails/${item[field]}`;
                    this.modalImages.push(imagePath);
                }
            });

            if (this.modalImages.length === 0) {
                const defaultPath = `/images/thumbnails/${item.ProductID}.jpg`;
                this.modalImages.push(defaultPath);
            }

            this.showImageModal = true;
            document.body.style.overflow = "hidden";
        },

        closeImageModal() {
            this.showImageModal = false;
            this.modalImages = [];
            document.body.style.overflow = "auto";
        },

        nextImage() {
            if (this.currentImageIndex < this.modalImages.length - 1) {
                this.currentImageIndex++;
            } else {
                this.currentImageIndex = 0;
            }
        },

        prevImage() {
            if (this.currentImageIndex > 0) {
                this.currentImageIndex--;
            } else {
                this.currentImageIndex = this.modalImages.length - 1;
            }
        },

        todayDate() {
            const today = new Date();
            return today.toISOString().split("T")[0];
        },

        async fetchInventory() {
            this.loading = true;
            try {
                const response = await axios.get(
                    `${API_BASE_URL}/api/unreceived/products`,
                    {
                        params: {
                            search: this.searchQuery,
                            page: this.currentPage,
                            location: "Orders",
                        },
                    }
                );

                this.inventory = response.data.data;
                this.totalPages = response.data.last_page;
            } catch (error) {
                console.error("Error fetching inventory data:", error);
                SoundService.error();
            } finally {
                this.loading = false;
            }
        },

        // Simplified tracking input handler
        handleTrackingInput(event) {
            this.validateTrackingNumber();

            // Auto verify after short delay when typing
            if (this.trackingNumberValid && this.trackingNumber.length >= 5) {
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }

                this.autoVerifyTimeout = setTimeout(() => {
                    this.verifyAndProcessTracking();
                }, 500);
            }
        },

        validateTrackingNumber() {
            this.trackingNumberValid = this.trackingNumber.trim() !== "";
            if (!this.trackingNumberValid) {
                SoundService.error();
            }
            return this.trackingNumberValid;
        },

        // Combined verification and processing
        async verifyAndProcessTracking() {
            this.validateTrackingNumber();

            if (!this.trackingNumberValid) {
                this.$refs.scanner.showScanError(
                    "Please enter a valid tracking number"
                );
                SoundService.error();
                return;
            }

            // Start loading animation
            this.$refs.scanner.startLoading("Processing tracking number...");

            try {
                // Check if tracking exists in database
                const response = await axios.get(
                    `${API_BASE_URL}/api/unreceived/verify-tracking`,
                    {
                        params: { tracking: this.trackingNumber },
                    }
                );

                if (response.data.found) {
                    if (response.data.alreadyScanned) {
                        this.$refs.scanner.stopLoading();
                        SoundService.alreadyScanned();
                        this.$refs.scanner.showScanWarning(
                            `Item already scanned`
                        );
                        this.$refs.trackingInput.select();
                        return;
                    }

                    // Store product information including item status
                    this.productId = response.data.productId;
                    this.rtcounter = response.data.rtcounter;
                    this.itemStatus = response.data.itemStatus; // Store item status

                    // Immediately process the scan with auto-generated RPN and PRD
                    await this.processAutoScan();
                } else {
                    this.$refs.scanner.stopLoading();
                    this.$refs.scanner.showScanError(
                        "Tracking number not found in orders"
                    );
                    this.trackingFound = false;
                    SoundService.notFound();
                    this.$refs.trackingInput.select();
                }
            } catch (error) {
                this.$refs.scanner.stopLoading();
                console.error("Error verifying tracking:", error);
                this.$refs.scanner.showScanError(
                    "Error checking tracking number"
                );
                SoundService.error();
                this.$refs.trackingInput.select();
            }
        },

        // Auto process scan with generated RPN and PRD
        async processAutoScan() {
            try {
                if (!this.productId) {
                    this.$refs.scanner.stopLoading();
                    this.$refs.scanner.showScanError(
                        "Missing product ID, please try again"
                    );
                    SoundService.error();
                    return;
                }

                // Update loading message
                this.$refs.scanner.startLoading("Processing scan...");

                const csrfToken = document.querySelector(
                    'meta[name="csrf-token"]'
                ).content;

                // Auto-generate today's date for PRD
                const todayDate = this.todayDate();

                const scanData = {
                    _token: csrfToken,
                    trackingNumber: this.trackingNumber,
                    productId: this.productId,
                    rtcounter: this.rtcounter,
                    prdDate: todayDate, // Auto-generated
                    autoGenerate: true, // Flag to indicate auto-generation
                };

                // Get images from scanner component
                const images = this.$refs.scanner.capturedImages.map(
                    (img) => img.data
                );

                // Send data to API
                const response = await axios.post(
                    `${API_BASE_URL}/api/unreceived/process-scan`,
                    {
                        ...scanData,
                        Images: images,
                    },
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": csrfToken,
                        },
                    }
                );

                const data = response.data;

                this.$refs.scanner.stopLoading();

                if (data.success) {
                    // Show success notification with item status
                    const successMessage = `${data.item} - Status: ${this.itemStatus || 'Unknown'}`;
                    this.$refs.scanner.showScanSuccess(successMessage);
                    SoundService.successScan(true);

                    // Format PRD for display
                    const prdFormatted = data.prdGenerated || "PRD";

                    // Add to scan history with item status and CSS class
                    const statusDisplay = this.itemStatus || 'Unknown';
                    const statusClass = statusDisplay.toLowerCase() === 'working' ? 'status-working' : 'status-error';
                    
                    this.$refs.scanner.addSuccessScan({
                        Trackingnumber: this.trackingNumber,
                        RPN: data.rpnGenerated || 'Auto-generated',
                        PRD: prdFormatted,
                        Status: statusDisplay,
                        StatusClass: statusClass // Add CSS class for styling
                    });

                    // Reset and refresh
                    this.resetScannerState();
                    this.fetchInventory();
                } else {
                    this.$refs.scanner.showScanError(
                        data.message || "Error processing scan"
                    );
                    SoundService.scanRejected(true);

                    // Add to error scan history with CSS class
                    const statusDisplay = this.itemStatus || 'Unknown';
                    const statusClass = statusDisplay.toLowerCase() === 'working' ? 'status-working' : 'status-error';
                    
                    this.$refs.scanner.addErrorScan(
                        {
                            Trackingnumber: this.trackingNumber,
                            RPN: 'Failed',
                            PRD: 'Failed',
                            Status: statusDisplay,
                            StatusClass: statusClass // Add CSS class for styling
                        },
                        data.reason || "error"
                    );

                    this.$nextTick(() => {
                        if (this.$refs.trackingInput) {
                            this.$refs.trackingInput.select();
                        }
                    });
                }
            } catch (error) {
                this.$refs.scanner.stopLoading();
                console.error("Error processing scan:", error);
                this.$refs.scanner.showScanError("Network or server error");
                SoundService.scanRejected(true);

                this.$nextTick(() => {
                    if (this.$refs.trackingInput) {
                        this.$refs.trackingInput.select();
                    }
                });
            }
        },

        // Reset scanner state
        resetScannerState() {
            this.trackingNumber = "";
            this.trackingFound = false;
            this.productId = "";
            this.rtcounter = "";
            this.itemStatus = "";

            if (this.autoVerifyTimeout) {
                clearTimeout(this.autoVerifyTimeout);
                this.autoVerifyTimeout = null;
            }

            this.$nextTick(() => {
                if (this.$refs.trackingInput) {
                    this.$refs.trackingInput.focus();
                }
            });
        },

        // Scanner event handlers
        handleScanProcess() {
            this.verifyAndProcessTracking();
        },

        handleHardwareScan(scannedCode) {
            this.trackingNumber = scannedCode;
            this.verifyAndProcessTracking();
        },

        handleScannerOpened() {
            console.log("Scanner opened");
            this.showManualInput = this.$refs.scanner.showManualInput;
            this.resetScannerState();
        },

        handleScannerClosed() {
            console.log("Scanner closed");
            this.fetchInventory();
        },

        handleScannerReset() {
            console.log("Scanner reset");
            this.resetScannerState();
        },

        handleModeChange(event) {
            this.showManualInput = event.manual;
        },

        // Pagination methods
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

        changePerPage() {
            this.currentPage = 1;
            this.fetchInventory();
        },

        toggleAll() {
            this.inventory.forEach((item) => (item.checked = this.selectAll));
        },

        toggleDetails(index) {
            this.$set(this.expandedRows, index, !this.expandedRows[index]);
        },

        toggleDetailsVisibility() {
            this.showDetails = !this.showDetails;
        },

        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortOrder = this.sortOrder === "asc" ? "desc" : "asc";
            } else {
                this.sortColumn = column;
                this.sortOrder = "asc";
            }
        },
    },
    watch: {
        searchQuery() {
            this.currentPage = 1;
            this.fetchInventory();
        },
    },
    mounted() {
        axios.defaults.baseURL = window.location.origin;
        this.fetchInventory();
    },
};