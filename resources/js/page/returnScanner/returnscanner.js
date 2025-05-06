import { eventBus } from "../../components/eventBus";
import ScannerComponent from "../../components/Scanner.vue";
import { SoundService } from "../../components/Sound_service";
import "../../../css/modules.css";
const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "ReturnScannerModule",
    components: {
        ScannerComponent,
    },
    data() {
        return {
            inventory: [],
            stores: [],
            selectedStore: "",
            currentPage: 1,
            totalPages: 1,
            perPage: 10, // Default rows per page
            sortColumn: "",
            sortOrder: "asc",
            showDetails: false,

            // Scanner data
            returnId: "",
            serialNumber: "",
            locationInput: "",
            showManualInput: false,

            // For dual serial detection
            dualSerialProduct: false,
            secondSerialNumber: "",
            secondSerialLabel: "",

            // ReturnID toggle
            showReturnIdField: false,

            // Return history
            returnHistory: [],

            // For auto verification
            autoVerifyTimeout: null,
        };
    },
    computed: {
        // Check if serial or return ID is provided
        hasIdentifier() {
            return (
                this.serialNumber.trim() !== "" || this.returnId.trim() !== ""
            );
        },
    },
    methods: {
        // Open scanner modal
        openScannerModal() {
            this.$refs.scanner.openScannerModal();
        },

        // Toggle ReturnID field
        toggleReturnIdField() {
            this.showReturnIdField = !this.showReturnIdField;

            // If shown, focus on the field
            if (this.showReturnIdField) {
                this.$nextTick(() => {
                    if (this.$refs.returnIdInput) {
                        this.$refs.returnIdInput.focus();
                    }
                });
            }
        },

        // Format date for display
        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString() + " " + date.toLocaleTimeString();
        },

        // Format status for display
        formatStatus(status) {
            const statusMap = {
                pending: "Pending",
                processed: "Processed",
                rejected: "Rejected",
                missing: "Not Found",
            };
            return statusMap[status] || status;
        },

        // View return details
        viewReturnDetails(item) {
            alert(
                `Return Details for Serial: ${item.serial_number}\n${
                    item.secondSerial
                        ? "Second Serial: " + item.secondSerial + "\n"
                        : ""
                }Status: ${this.formatStatus(item.status)}\nLocation: ${
                    item.location
                }\nDate: ${this.formatDate(item.created_at)}`
            );
        },

        // Store dropdown functions
        async fetchStores() {
            try {
                const response = await axios.get(
                    `${API_BASE_URL}/api/returns/stores`,
                    {
                        withCredentials: true,
                    }
                );
                this.stores = response.data;
            } catch (error) {
                console.error("Error fetching stores:", error);
            }
        },

        changeStore() {
            this.fetchReturnHistory();
        },

        // Fetch return history
        async fetchReturnHistory() {
            try {
                const response = await axios.get(
                    `${API_BASE_URL}/api/returns/history`,
                    {
                        params: {
                            store: this.selectedStore,
                        },
                        withCredentials: true,
                    }
                );

                this.returnHistory = response.data || [];
            } catch (error) {
                console.error("Error fetching return history:", error);
                if (SoundService && SoundService.error) {
                    SoundService.error();
                }
            }
        },

        // Check for dual serial based on serial number
        async checkDualSerial() {
            if (!this.serialNumber) return false;

            try {
                // Show loading status
                this.$refs.scanner.startLoading("Checking product...");

                const response = await axios.get(
                    `${API_BASE_URL}/api/returns/check-serial`,
                    {
                        params: { serial: this.serialNumber },
                        withCredentials: true,
                    }
                );

                // Hide loading
                this.$refs.scanner.stopLoading();

                if (response.data.success) {
                    // Check if this is a dual serial product
                    if (response.data.isDualSerial) {
                        this.dualSerialProduct = true;
                        this.secondSerialLabel =
                            response.data.secondSerialLabel || "Second Serial";

                        // If the second serial is already populated from the DB
                        if (response.data.secondSerial) {
                            this.secondSerialNumber =
                                response.data.secondSerial;
                        }

                        // Play notification sound
                        if (SoundService && SoundService.notification) {
                            SoundService.notification();
                        }

                        // Show a message about dual serial
                        this.$refs.scanner.showScanNotice(
                            `Dual serial product detected. Please scan ${this.secondSerialLabel} as well.`
                        );

                        // Focus on second serial field
                        this.$nextTick(() => {
                            if (this.$refs.secondSerialInput) {
                                this.$refs.secondSerialInput.focus();
                            }
                        });

                        return true;
                    } else {
                        this.dualSerialProduct = false;
                        this.secondSerialNumber = "";
                        return false;
                    }
                } else {
                    this.dualSerialProduct = false;
                    this.secondSerialNumber = "";
                    return false;
                }
            } catch (error) {
                console.error("Error checking dual serial:", error);
                this.$refs.scanner.stopLoading();
                return false;
            }
        },

        // Input field handlers with sound
        async handleReturnIdInput() {
            if (!this.showManualInput && this.returnId.trim().length > 5) {
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }

                this.autoVerifyTimeout = setTimeout(() => {
                    // Play success sound
                    if (SoundService && SoundService.success) {
                        SoundService.success();
                    }

                    // Focus on serial number field
                    this.focusNextField("serialNumberInput");
                }, 500);
            }
        },

        async handleSerialInput() {
            // Basic validation for serial
            const isValid = /^[a-zA-Z0-9-]+$/.test(this.serialNumber.trim());

            if (!isValid && this.serialNumber.trim() !== "") {
                // Show error for invalid serial
                this.$refs.scanner.showScanError(
                    "Invalid Serial Number format"
                );
                this.$refs.serialNumberInput.select();
                if (SoundService && SoundService.error) {
                    SoundService.error();
                }
                return;
            }

            // In auto mode with valid input, check for dual serial and proceed
            if (!this.showManualInput && this.serialNumber.trim().length > 5) {
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }

                this.autoVerifyTimeout = setTimeout(async () => {
                    // First check if this is a dual serial product
                    const isDualSerial = await this.checkDualSerial();

                    if (isDualSerial) {
                        // Already handled in checkDualSerial
                        return;
                    }

                    // Play success sound
                    if (SoundService && SoundService.success) {
                        SoundService.success();
                    }

                    // Focus on location field
                    this.focusNextField("locationInput");
                }, 500);
            }
        },

        async handleSecondSerialInput() {
            // Basic validation for second serial
            const isValid = /^[a-zA-Z0-9-]+$/.test(
                this.secondSerialNumber.trim()
            );

            if (!isValid && this.secondSerialNumber.trim() !== "") {
                // Show error for invalid serial
                this.$refs.scanner.showScanError(
                    "Invalid Second Serial Number format"
                );
                this.$refs.secondSerialInput.select();
                if (SoundService && SoundService.error) {
                    SoundService.error();
                }
                return;
            }

            // In auto mode with valid input, proceed to location
            if (
                !this.showManualInput &&
                this.secondSerialNumber.trim().length > 5
            ) {
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }

                this.autoVerifyTimeout = setTimeout(() => {
                    // Play success sound
                    if (SoundService && SoundService.success) {
                        SoundService.success();
                    }

                    // Focus on location field
                    this.focusNextField("locationInput");
                }, 500);
            }
        },

        handleLocationInput() {
            // Validate location format
            const locationRegex = /^L\d{3}[A-G]$/i;
            const isValid =
                locationRegex.test(this.locationInput.trim()) ||
                this.locationInput.trim() === "Floor" ||
                this.locationInput.trim() === "L800G";

            if (!isValid && this.locationInput.trim() !== "") {
                this.$refs.scanner.showScanError(
                    "Invalid Location Format (use L###X, Floor, or L800G)"
                );
                this.$refs.locationInput.select();
                if (SoundService && SoundService.error) {
                    SoundService.error();
                }
                return;
            }

            // Only in auto mode, process scan after valid location input
            if (
                !this.showManualInput &&
                isValid &&
                this.locationInput.trim().length > 0
            ) {
                if (this.autoVerifyTimeout) {
                    clearTimeout(this.autoVerifyTimeout);
                }

                this.autoVerifyTimeout = setTimeout(() => {
                    // Play success sound for valid location
                    if (SoundService && SoundService.success) {
                        SoundService.success();
                    }

                    // Process the scan
                    this.processScan();
                }, 500);
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

        // Process scan with validation
        async processScan(scannedCode = null) {
            try {
                // Use either the scanned code or input fields
                let scanSerial, scanSecondSerial, scanLocation, scanReturnId;

                if (scannedCode) {
                    // External code passed (from hardware scanner)
                    // Determine if it's a return ID, serial, or location based on format
                    const isLocation =
                        /^L\d{3}[A-G]$/i.test(scannedCode) ||
                        scannedCode === "Floor" ||
                        scannedCode === "L800G";

                    const isReturnId = /^R\d{5,}$/i.test(scannedCode);

                    if (isLocation) {
                        scanLocation = scannedCode;
                        scanSerial = this.serialNumber;
                        scanSecondSerial = this.secondSerialNumber;
                        scanReturnId = this.returnId;
                    } else if (isReturnId) {
                        scanReturnId = scannedCode;
                        scanSerial = this.serialNumber;
                        scanSecondSerial = this.secondSerialNumber;
                        scanLocation = this.locationInput;
                    } else {
                        // Assume it's a serial
                        scanSerial = scannedCode;
                        scanSecondSerial = this.secondSerialNumber;
                        scanLocation = this.locationInput;
                        scanReturnId = this.returnId;

                        // Check if this is a dual serial product
                        await this.checkDualSerial();
                    }
                } else {
                    // Use the input fields
                    scanSerial = this.serialNumber;
                    scanSecondSerial = this.secondSerialNumber;
                    scanLocation = this.locationInput;
                    scanReturnId = this.returnId;
                }

                // Basic validation - need at least a serial
                if (!scanSerial) {
                    this.$refs.scanner.showScanError(
                        "Serial Number is required"
                    );
                    if (SoundService && SoundService.error) {
                        SoundService.error();
                    }
                    this.focusNextField("serialNumberInput");
                    return;
                }

                // Validate dual serial if detected
                if (this.dualSerialProduct && !scanSecondSerial) {
                    this.$refs.scanner.showScanError(
                        `${this.secondSerialLabel} is required for this product`
                    );
                    if (SoundService && SoundService.error) {
                        SoundService.error();
                    }
                    this.focusNextField("secondSerialInput");
                    return;
                }

                // Validate location format if provided
                const locationRegex = /^L\d{3}[A-G]$/i;
                const isValidLocation =
                    !scanLocation ||
                    locationRegex.test(scanLocation) ||
                    scanLocation === "Floor" ||
                    scanLocation === "L800G";

                if (!isValidLocation) {
                    this.$refs.scanner.showScanError(
                        "Invalid Location Format (use L###X, Floor, or L800G)"
                    );
                    if (SoundService && SoundService.error) {
                        SoundService.error();
                    }
                    return;
                }

                // Get images from scanner
                const imageData = this.$refs.scanner.capturedImages.map(
                    (img) => img.data
                );

                // Show loading state
                this.$refs.scanner.startLoading("Processing Return Scan");

                // Send data to server
                const scanData = {
                    ReturnId: scanReturnId || null,
                    SerialNumber: scanSerial,
                    SecondSerial: scanSecondSerial || null,
                    Location: scanLocation || "Floor", // Default to Floor if not provided
                    Store: this.selectedStore,
                    Images: imageData,
                };

                console.log("Sending scan data:", scanData);

                // Send to API
                const response = await axios.post(
                    `${API_BASE_URL}/api/returns/process-scan`,
                    scanData,
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

                // Hide loading
                this.$refs.scanner.stopLoading();

                const data = response.data;

                if (data.success) {
                    // Success case
                    this.$refs.scanner.showScanSuccess(
                        data.message || "Return processed successfully"
                    );
                    if (SoundService && SoundService.successScan) {
                        SoundService.successScan(true);
                    }

                    // Add to scan history
                    this.$refs.scanner.addSuccessScan({
                        ReturnId: scanReturnId || "N/A",
                        Serial: scanSerial,
                        SecondSerial: scanSecondSerial || "N/A",
                        Location: scanLocation || "Floor",
                    });

                    // Reset fields and dual serial flag
                    this.dualSerialProduct = false;

                    // Refresh return history
                    this.fetchReturnHistory();
                } else {
                    // Error case
                    this.$refs.scanner.showScanError(
                        data.message || "Error processing return"
                    );
                    if (SoundService && SoundService.scanRejected) {
                        SoundService.scanRejected(true);
                    }

                    // Add to error scan history
                    this.$refs.scanner.addErrorScan(
                        {
                            ReturnId: scanReturnId || "N/A",
                            Serial: scanSerial,
                            SecondSerial: scanSecondSerial || "N/A",
                            Location: scanLocation || "N/A",
                        },
                        data.reason || "error"
                    );
                }

                // Clear input fields and focus on Return ID or Serial
                this.clearScanFields();
                if (this.showReturnIdField) {
                    this.focusNextField("returnIdInput");
                } else {
                    this.focusNextField("serialNumberInput");
                }
            } catch (error) {
                // Hide loading
                this.$refs.scanner.stopLoading();

                console.error("Error processing scan:", error);
                this.$refs.scanner.showScanError("Network or server error");
                if (SoundService && SoundService.scanRejected) {
                    SoundService.scanRejected(true);
                }

                // Add failed scan to history
                this.$refs.scanner.addErrorScan(
                    {
                        ReturnId: this.returnId || "N/A",
                        Serial: this.serialNumber || "",
                        SecondSerial: this.secondSerialNumber || "N/A",
                        Location: this.locationInput || "N/A",
                    },
                    "network_error"
                );
            }
        },

        // Clear scan fields
        clearScanFields() {
            this.returnId = "";
            this.serialNumber = "";
            this.secondSerialNumber = "";
            this.locationInput = "";
            this.dualSerialProduct = false;
        },

        // Scanner event handlers
        handleScanProcess() {
            this.processScan();
        },

        handleHardwareScan(scannedCode) {
            // For hardware scanner input, determine the type of code and process accordingly
            this.processScan(scannedCode);
        },

        handleModeChange(event) {
            this.showManualInput = event.manual;
        },

        handleScannerOpened() {
            // Get current mode from scanner component
            this.showManualInput = this.$refs.scanner.showManualInput;

            // Reset fields
            this.clearScanFields();

            // Focus on appropriate field
            this.$nextTick(() => {
                if (this.showReturnIdField && this.$refs.returnIdInput) {
                    this.$refs.returnIdInput.focus();
                } else if (this.$refs.serialNumberInput) {
                    this.$refs.serialNumberInput.focus();
                }
            });
        },

        handleScannerClosed() {
            // Refresh inventory when scanner is closed
            this.fetchReturnHistory();
        },

        handleScannerReset() {
            // Reset fields when scanner is reset
            this.clearScanFields();
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

        // Fetch stores for dropdown
        this.fetchStores();

        // Fetch initial data
        this.fetchReturnHistory();
    },
    beforeUnmount() {
        // Clean up any timeouts
        if (this.autoVerifyTimeout) {
            clearTimeout(this.autoVerifyTimeout);
        }
    },
};
