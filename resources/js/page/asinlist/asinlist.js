import { eventBus } from '../../components/eventBus';
import '../../../css/modules.css';

const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: 'AsinViewerModule',
    data() {
        return {
            asinData: [],
            currentPage: 1,
            totalPages: 1,
            perPage: 15,
            expandedRows: {},
            sortColumn: "",
            sortOrder: "asc",
            
            // Store filter
            stores: [],
            selectedStore: '',
            
            // For ASIN details modal
            showAsinDetailsModal: false,
            selectedAsin: null,
            enlargeImage: false,
            
            // For image handling
            defaultImagePath: '/images/default-product.png',

            // Loading states
            isLoading: false
        };
    },
    computed: {
        searchQuery() {
            return eventBus.searchQuery;
        },
        sortedAsinData() {
            if (!this.sortColumn) return this.asinData;
            return [...this.asinData].sort((a, b) => {
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
        isMobile() {
            return window.innerWidth <= 768;
        }
    },
    methods: {
        // Image handling methods
        getImagePath(asin) {
            return asin ? `/images/asinimg/${asin}_0.png` : this.defaultImagePath;
        },
        
        getInstructionCardPath(asin) {
            return asin ? `/images/instructioncard/${asin}.jpg` : this.defaultImagePath;
        },
        
        handleImageError(event, item) {
            event.target.src = this.defaultImagePath;
            if (item) item.useDefaultImage = true;
        },
        
        handleInstructionCardError(event) {
            event.target.src = this.defaultImagePath;
            event.target.style.opacity = '0.5';
        },
        
        createDefaultImageSVG() {
            return `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100' width='100' height='100'%3E%3Crect width='100' height='100' fill='%23f0f0f0'/%3E%3Cpath d='M35,30L65,30L65,70L35,70Z' fill='%23e0e0e0' stroke='%23bbbbbb' stroke-width='2'/%3E%3Cpath d='M45,40L55,40L55,60L45,60Z' fill='%23d0d0d0' stroke='%23bbbbbb'/%3E%3Cpath d='M35,80L65,80L65,85L35,85Z' fill='%23e0e0e0'/%3E%3C/svg%3E`;
        },

        // Store management
        async fetchStores() {
            try {
                const response = await axios.get(`${API_BASE_URL}/api/asinlist/stores`, {
                    withCredentials: true
                });
                this.stores = response.data;
            } catch (error) {
                console.error("Error fetching stores:", error);
                this.stores = [];
            }
        },
        
        changeStore() {
            this.currentPage = 1;
            this.fetchAsinData();
        },

        // Data fetching
        async fetchAsinData() {
            try {
                console.log('Fetching ASIN data...');
                this.isLoading = true;
                
                const response = await axios.get(`${API_BASE_URL}/api/asinlist/products`, {
                    params: { 
                        search: this.searchQuery, 
                        page: this.currentPage, 
                        per_page: this.perPage,
                        store: this.selectedStore
                    },
                    withCredentials: true
                });

                console.log('ASIN API Response:', response.data);

                // Process items with flags
                const asinItems = (response.data.data || []).map(item => ({
                    ...item,
                    useDefaultImage: false,
                    fnskus: item.fnskus || []
                }));

                this.asinData = asinItems;
                this.totalPages = response.data.last_page || 1;
                
            } catch (error) {
                console.error("Error fetching ASIN data:", error);
                this.asinData = [];
            } finally {
                this.isLoading = false;
            }
        },

        // Pagination
        changePerPage() {
            this.currentPage = 1;
            this.fetchAsinData();
        },
        
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.fetchAsinData();
            }
        },
        
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.fetchAsinData();
            }
        },

        // UI
        toggleDetails(index) {
            const updatedExpandedRows = { ...this.expandedRows };
            updatedExpandedRows[index] = !updatedExpandedRows[index];
            this.expandedRows = updatedExpandedRows;
        },

        // Sorting
        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortOrder = this.sortOrder === "asc" ? "desc" : "asc";
            } else {
                this.sortColumn = column;
                this.sortOrder = "asc";
            }
        },

        // Modal management
        viewAsinDetails(item) {
            this.selectedAsin = item;
            this.showAsinDetailsModal = true;
        },
        
        closeAsinDetailsModal() {
            this.showAsinDetailsModal = false;
            this.selectedAsin = null;
            this.enlargeImage = false;
        },

        // Get unique stores from FNSKUs
        getUniqueStores(fnskus) {
            if (!fnskus || fnskus.length === 0) return [];
            const stores = fnskus.map(fnsku => fnsku.storename).filter(store => store);
            return [...new Set(stores)];
        },

        // Handle window resize for responsiveness
        handleResize() {
            // Update mobile state
            this.$forceUpdate();
        }
    },
    watch: {
        searchQuery() {
            this.currentPage = 1;
            this.fetchAsinData();
        }
    },
    mounted() {
        // Configure axios
        axios.defaults.baseURL = window.location.origin;
        axios.defaults.withCredentials = true;
        
        // Set CSRF token
        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) {
            axios.defaults.headers.common['X-CSRF-TOKEN'] = token.getAttribute('content');
        }
        
        // Add Font Awesome if not already included
        if (!document.querySelector('link[href*="font-awesome"]')) {
            const fontAwesome = document.createElement('link');
            fontAwesome.rel = 'stylesheet';
            fontAwesome.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css';
            document.head.appendChild(fontAwesome);
        }
        
        // Set default image
        this.defaultImagePath = this.createDefaultImageSVG();
        
        // Fetch initial data
        this.fetchStores();
        this.fetchAsinData();

        // Add resize listener
        window.addEventListener('resize', this.handleResize);
    },
    beforeUnmount() {
        // Clean up event listener
        window.removeEventListener('resize', this.handleResize);
    }
}