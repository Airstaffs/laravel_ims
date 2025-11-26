import "./creation_msku.css";
import Swal from "sweetalert2";

const API_BASE_URL = import.meta.env.VITE_API_URL;
const csrfToken = document
    .querySelector('meta[name="csrf-token"]')
    .getAttribute("content");

export default {
    name: "IMSListing",
    data() {
        return {
            allowedUserIds: ["Jundell", "Admin", "Julius","Glen"],
            selectedStore: "",
            storeOptions: [],
            searchInput: "",
            dropdownOptions: [],
            selectedValue: "",
            showDropdown: false,
            showProductModal: false,
            productSearch: "",
            imageModalVisible: false,
            modalImage: "",
            modalCaption: "",
            showOfferForm: false,
            mskuList: [],
            offer: {
                price: 0,
                currency: "USD",
                fulfillment: "FBA",
                marketplace: "ATVPDKIKX0DER",
            },
            requirements: "LISTING_OFFER_ONLY",
            showRestrictionModal: false,
            restrictionTable: [],
            filteredAsins: [],
            selectedAsin: null,
            selectedCondition: "",
            conditionMap: {
                new_new: "New",
                new_open_box: "New - Open Box",
                new_oem: "New - OEM",
                refurbished_refurbished: "Refurbished",
                used_like_new: "Used - Like New",
                used_very_good: "Used - Very Good",
                used_good: "Used - Good",
                used_acceptable: "Used - Acceptable",
                collectible_like_new: "Collectible - Like New",
                collectible_very_good: "Collectible - Very Good",
                collectible_good: "Collectible - Good",
                collectible_acceptable: "Collectible - Acceptable",
                club_club: "Club",
            },
            generatedMsku: "",
            asinSearch: "", // Ensure this is declared
        };
    },
    computed: {
        isAllowedUser() {
            return this.allowedUserIds.includes(this.$session?.id);
        },
    },
    methods: {
        fetchStores() {
            fetch(`${API_BASE_URL}/api/asinlist/all/stores`)
                .then((res) => res.json())
                .then((data) => {
                    if (Array.isArray(data)) {
                        this.storeOptions = data;
                    } else {
                        console.error("Invalid store response:", data);
                    }
                })
                .catch((err) => {
                    console.error("Failed to load stores:", err);
                });
        },

        fetchAsins() {
            if (!this.asinSearch.trim() || !this.selectedStore) return;

            const params = new URLSearchParams({
                keyword: this.asinSearch,
                storename: this.selectedStore,
            });

            // Show loading alert
            Swal.fire({
                title: "Searching...",
                text: "Please wait while we fetch ASINs.",
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                },
            });

            fetch(
                `${API_BASE_URL}/api/asinlist/asin/search?${params.toString()}`
            )
                .then((res) => res.json())
                .then((data) => {
                    this.filteredAsins = Array.isArray(data) ? data : [];
                    Swal.close();
                })
                .catch((err) => {
                    console.error("ASIN search failed:", err);
                    this.filteredAsins = [];
                    Swal.fire("Error", "ASIN search failed", "error");
                });
        },

        selectAsin(asin) {
            this.selectedAsin = asin;
            this.generatedMsku = "";
            this.asinSearch = "";
            this.filteredAsins = [];
        },

        generateMSKU() {
            if (
                !this.selectedAsin ||
                !this.selectedCondition ||
                !this.selectedStore
            )
                return;

            Swal.fire({
                title: "Generating MSKU...",
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                },
            });

            fetch(`${API_BASE_URL}/api/asinlist/msku/generate`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrfToken,
                },
                body: JSON.stringify({
                    asin: this.selectedAsin.ASIN,
                    condition: this.selectedCondition,
                    storename: this.selectedStore,
                }),
            })
                .then((res) => res.json())
                .then((data) => {
                    Swal.close();
                    if (data.msku) {
                        this.generatedMsku = data.msku;
                        Swal.fire("Success", "MSKU Generated!", "success");
                    } else {
                        Swal.fire(
                            "Error",
                            data.error || "Failed to generate MSKU",
                            "error"
                        );
                    }
                })
                .catch((err) => {
                    Swal.close();
                    console.error("Generate MSKU error:", err);
                    Swal.fire("Error", "Generate MSKU failed", "error");
                });
        },

        removeMsku(index) {
            this.mskuList.splice(index, 1);
        },

        submitAllMskus() {
            if (this.mskuList.length === 0) return;

            Swal.fire({
                title: "Submitting...",
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                },
            });

            fetch(`${API_BASE_URL}/api/asinlist/msku/save`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrfToken,
                },
                body: JSON.stringify({ mskus: this.mskuList }),
            })
                .then((res) => res.json())
                .then((data) => {
                    Swal.close();
                    let message = "";
                    if (data.success?.length) {
                        message += `✅ Success: ${data.success.join(", ")}\n`;
                    }
                    if (data.duplicates?.length) {
                        message += `⚠️ Duplicates: ${data.duplicates.join(
                            ", "
                        )}\n`;
                    }
                    if (data.failed?.length) {
                        message += `❌ Failed: ${data.failed
                            .map((f) => f.msku)
                            .join(", ")}\n`;
                    }
                    Swal.fire(
                        "Submission Result",
                        message || "Submitted!",
                        "info"
                    );
                    this.mskuList = [];
                })
                .catch((err) => {
                    Swal.close();
                    console.error("Submit error:", err);
                    Swal.fire("Error", "Submit failed", "error");
                });
        },

        saveMsku() {
            if (!this.generatedMsku) return;
            this.mskuList.push({
                asin: this.selectedAsin.ASIN,
                msku: this.generatedMsku,
                condition: this.selectedCondition,
                storename: this.selectedStore || "Renovartech",
            });
            this.generatedMsku = "";
        },

        handleStoreChange() {
            this.selectedAsin = null;
            this.asinSearch = "";
            this.filteredAsins = [];
            this.selectedCondition = "";
            this.generatedMsku = "";
            this.mskuList = [];
        },

        clearAsinSelection() {
            this.selectedAsin = null;
            this.selectedCondition = "";
            this.generatedMsku = "";
            this.mskuList = [];

            // Don't clear the search input — just refetch based on what's there
            this.fetchAsins();
        },
    },
    mounted() {
        this.fetchStores();
    },
};
