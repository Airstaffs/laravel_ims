import Swal from "sweetalert2";
import { eventBus } from "../../components/eventbus";
const API_BASE_URL = import.meta.env.VITE_API_URL;


export default {
    name: "SuppliersListModule",
    data() {
        return {
            suppliers: [],
            loading: true,
            isUpdating: false,
            openEditModal: false,

            supplierData: null, // Supplier data to be edited

            //Pagination
            currentPage: 1,
            perPage: 10,
            totalRecords: 0,
            first: 0,
        }
    },
    computed: {
        searchQuery() {
            return eventBus.searchQuery;
        },
    },
    methods: {
        handleUpdateSupplier() {
            console.log(this.supplierData, "supplierData")
            this.updateSupplier()
        },
        async fetchSuppliers() {
            this.loading = true
            try {
                const response = await axios.get(`${API_BASE_URL}/api/suppliers`,
                    {
                        params: {
                            search: this.searchQuery,
                            page: this.currentPage,
                            per_page: this.perPage
                        },
                        withCredentials: true,
                        headers: {
                            "Cache-Control": "no-cache",
                            Pragma: "no-cache",
                            Expires: "0",
                        },
                    }
                )
                const payload = response.data
                this.suppliers = payload.data || []
                this.totalRecords = payload.total
            } catch (error) {
                console.error("Error fetching suppliers:", error)
                this.suppliers = []
            } finally {
                this.loading = false
            }
        },
        async updateSupplier() {
            this.isUpdating = true
            try {
                const { name, contact, address1, address2, email, websiteAddress } = this.supplierData

                const csrfToken = document.querySelector(
                    'meta[name="csrf-token"]'
                ).content;

                const response = await axios.post(`${API_BASE_URL}/api/suppliers/update-supplier`,
                    {
                        supplierName: name,
                        supplierContact: contact || "",
                        supplierAddress1: address1 || "",
                        supplierAddress2: address2 || "",
                        supplierEmail: email || "",
                        supplierWebsiteAddress: websiteAddress || "",
                    },
                    {
                        headers: {
                            "X-CSRF-TOKEN": csrfToken,
                            Accept: "application/json",
                        }
                    }
                )

                if(response.data.success){
                    this.openEditModal = false
                    this.fetchSuppliers()

                    Swal.fire({
                        icon: 'success',
                        title: 'Success...',
                        text: response.data.message,
                    })
                }
            } catch (error) {
                console.error("Error updating supplier:", error)
                Swal.fire({
                    icon: 'error',
                    title: 'Error...',
                    text: error.response.data.message,
                })
            } finally {
                this.isUpdating = false
            }
        },
        onPageChange(event) {
            this.first = event.first;
            this.currentPage = event.page + 1; // convert to 1-based
            this.perPage = event.rows;
            this.fetchSuppliers();
        },
        handleOpenEditModal(data) {
            this.supplierData = data;
            this.openEditModal = true;
        },
    },
    mounted() {
        axios.defaults.baseURL = window.location.origin;
        this.fetchSuppliers()
    },
    watch: {
        searchQuery() {
            this.currentPage = 1;
            this.first = 0
            this.fetchSuppliers();
        },
    }
}