<template>
    <Dialog
        :visible="visible"
        modal
        :header="'Incoming Item Counter'"
        :style="{ width: '95%', maxWidth: '1400px' }"
        :pt="{
            root: { class: 'mobile-fullscreen-dialog' },
        }"
        @update:visible="handleVisibilityChange"
    >
        <div class="incoming-counter-container">
            <!-- Compact Search and Filter Section -->
            <Card class="mb-3">
                <template #content>
                    <div class="filters-row">
                        <!-- Search Bar with Icon Inside -->
                        <div class="search-field">
                            <IconField iconPosition="left">
                                <InputIcon class="pi pi-search" />
                                <InputText
                                    v-model="searchQuery"
                                    placeholder="Search by ASIN, title, or keyword..."
                                    @input="handleSearchInput"
                                    size="small"
                                    style="width: 100%;"
                                />
                            </IconField>
                        </div>

                        <!-- Date From -->
                        <div class="date-field">
                            <input
                                type="date"
                                v-model="dateFrom"
                                @change="handleSearchInput"
                                class="form-control form-control-sm"
                                placeholder="From Date"
                            />
                        </div>

                        <!-- Date To -->
                        <div class="date-field">
                            <input
                                type="date"
                                v-model="dateTo"
                                @change="handleSearchInput"
                                class="form-control form-control-sm"
                                placeholder="To Date"
                            />
                        </div>

                        <!-- Delivery Status Filter -->
                        <div class="status-field">
                            <Select
                                v-model="deliveryStatus"
                                :options="deliveryStatusOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="All Statuses"
                                @change="handleSearchInput"
                                size="small"
                                :showClear="true"
                            />
                        </div>

                        <!-- Clear Button -->
                        <Button
                            icon="pi pi-times"
                            label="Clear"
                            @click="clearSearch"
                            size="small"
                            severity="secondary"
                            outlined
                            v-if="hasActiveFilters"
                        />
                    </div>

                    <!-- Active Filters Display -->
                    <div v-if="hasActiveFilters" class="active-filters-compact mt-2">
                        <Tag
                            v-if="searchQuery"
                            severity="info"
                            class="me-1"
                        >
                            {{ searchQuery }}
                            <i
                                class="pi pi-times ms-1 cursor-pointer"
                                @click="searchQuery = ''; handleSearchInput()"
                            ></i>
                        </Tag>
                        <Tag
                            v-if="dateFrom"
                            severity="success"
                            class="me-1"
                        >
                            From: {{ formatDate(dateFrom) }}
                            <i
                                class="pi pi-times ms-1 cursor-pointer"
                                @click="dateFrom = ''; handleSearchInput()"
                            ></i>
                        </Tag>
                        <Tag
                            v-if="dateTo"
                            severity="success"
                            class="me-1"
                        >
                            To: {{ formatDate(dateTo) }}
                            <i
                                class="pi pi-times ms-1 cursor-pointer"
                                @click="dateTo = ''; handleSearchInput()"
                            ></i>
                        </Tag>
                        <Tag
                            v-if="deliveryStatus"
                            severity="warning"
                            class="me-1"
                        >
                            Status: {{ deliveryStatus }}
                            <i
                                class="pi pi-times ms-1 cursor-pointer"
                                @click="deliveryStatus = ''; handleSearchInput()"
                            ></i>
                        </Tag>
                    </div>
                </template>
            </Card>

            <!-- Results Summary -->
            <div v-if="searchResults.length > 0" class="results-summary mb-3">
                <div class="summary-card">
                    <div class="summary-item">
                        <i class="pi pi-tag summary-icon"></i>
                        <div>
                            <div class="summary-label">Unique ASINs</div>
                            <div class="summary-value">{{ uniqueAsins }}</div>
                        </div>
                    </div>
                    <div class="summary-item">
                        <i class="pi pi-users summary-icon"></i>
                        <div>
                            <div class="summary-label">Seller Count</div>
                            <div class="summary-value">{{ sellerCount }}</div>
                        </div>
                    </div>
                    <div class="summary-item highlight-card">
                        <i class="pi pi-shopping-cart summary-icon-large"></i>
                        <div>
                            <div class="summary-label">Total Quantity</div>
                            <div class="summary-value-large">{{ totalQuantity }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Results Table -->
            <Card>
                <template #content>
                    <div v-if="loading" class="text-center py-5">
                        <i class="pi pi-spin pi-spinner" style="font-size: 2rem"></i>
                        <p class="mt-3">Loading results...</p>
                    </div>

                    <div v-else-if="searchResults.length === 0 && hasSearched" class="text-center py-5">
                        <i class="pi pi-inbox" style="font-size: 3rem; color: #ccc"></i>
                        <p class="mt-3 text-muted">No items found matching your search criteria.</p>
                        <Button
                            label="Clear Filters"
                            @click="clearSearch"
                            size="small"
                            severity="secondary"
                            outlined
                            class="mt-2"
                        />
                    </div>

                    <div v-else-if="!hasSearched" class="text-center py-5">
                        <i class="pi pi-search" style="font-size: 3rem; color: #ccc"></i>
                        <p class="mt-3 text-muted">Start typing to search or select a date range</p>
                        <p class="text-muted small">Results will appear automatically</p>
                    </div>

                    <DataTable
                        v-else
                        :value="searchResults"
                        :paginator="true"
                        :rows="10"
                        :rowsPerPageOptions="[5, 10, 20, 50]"
                        responsiveLayout="scroll"
                        class="results-table"
                        stripedRows
                        showGridlines
                    >
                        <Column field="asin" header="ASIN" sortable style="min-width: 120px">
                            <template #body="{ data }">
                                <div class="asin-cell">
                                    <span v-if="data.asin" class="badge bg-primary">
                                        {{ data.asin }}
                                    </span>
                                    <span v-else class="text-muted small">N/A</span>
                                </div>
                            </template>
                        </Column>

                        <Column field="title" header="Title" sortable style="min-width: 250px">
                            <template #body="{ data }">
                                <div class="title-cell">
                                    <div class="fw-semibold">{{ data.title || 'No Title' }}</div>
                                </div>
                            </template>
                        </Column>

                        <Column field="sellers" header="Seller Name" sortable style="min-width: 180px">
                            <template #body="{ data }">
                                <div class="seller-cell">
                                    <span class="text-muted small">{{ data.sellers || 'N/A' }}</span>
                                </div>
                            </template>
                        </Column>

                        <Column field="delivery_status" header="Delivery Status" sortable style="min-width: 150px">
                            <template #body="{ data }">
                                <div class="status-cell text-center">
                                    <Badge
                                        v-if="data.delivery_status"
                                        :severity="getDeliveryStatusSeverity(data.delivery_status)"
                                        :value="data.delivery_status"
                                        size="small"
                                    />
                                    <span v-else class="text-muted small">N/A</span>
                                </div>
                            </template>
                        </Column>

                        <Column field="total_quantity" header="Total Quantity" sortable style="min-width: 150px">
                            <template #body="{ data }">
                                <div class="quantity-cell text-center">
                                    <Tag :value="data.total_quantity" severity="success" class="quantity-badge" />
                                </div>
                            </template>
                        </Column>

                        <Column field="date_delivered" header="Date Delivered" sortable style="min-width: 150px">
                            <template #body="{ data }">
                                <div class="date-cell text-center">
                                    <span v-if="data.latest_delivery" class="small">
                                        {{ formatDate(data.latest_delivery) }}
                                    </span>
                                    <span v-else class="text-muted small">N/A</span>
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </template>
            </Card>
        </div>

        <template #footer>
            <div class="dialog-footer">
                <Button
                    label="Close"
                    icon="pi pi-times"
                    @click="handleClose"
                    size="small"
                    severity="secondary"
                />
            </div>
        </template>
    </Dialog>

    <!-- Item Details Modal -->
    <Dialog
        :visible="showDetailsModal"
        modal
        :header="`Items for ASIN: ${selectedAsin}`"
        :style="{ width: '90%', maxWidth: '1200px' }"
        @update:visible="showDetailsModal = $event"
    >
        <DataTable
            :value="itemDetails"
            :paginator="true"
            :rows="10"
            responsiveLayout="scroll"
            stripedRows
        >
            <Column field="rtcounter" header="RT#" sortable />
            <Column field="ProductTitle" header="Product Title" sortable style="min-width: 250px" />
            <Column field="quantity" header="Quantity" sortable>
                <template #body="{ data }">
                    <Tag :value="data.quantity" severity="success" />
                </template>
            </Column>
            <Column field="datedelivered" header="Delivery Date" sortable>
                <template #body="{ data }">
                    {{ formatDate(data.datedelivered) }}
                </template>
            </Column>
            <Column field="trackingnumber" header="Tracking #" sortable />
        </DataTable>

        <template #footer>
            <Button
                label="Close"
                icon="pi pi-times"
                @click="showDetailsModal = false"
                size="small"
                severity="secondary"
            />
        </template>
    </Dialog>
</template>

<script>
import { Button, Card, Dialog, InputText, Tag, Badge, DataTable, Column, IconField, InputIcon, Select } from 'primevue';
import Swal from 'sweetalert2';

export default {
    name: 'IncomingCountItem',
    components: {
        Button,
        Card,
        Dialog,
        InputText,
        Tag,
        Badge,
        DataTable,
        Column,
        IconField,
        InputIcon,
        Select
    },
    inheritAttrs: false,
    props: {
        visible: {
            type: Boolean,
            required: true
        }
    },
    emits: ['update:visible', 'close'],
    data() {
        return {
            searchQuery: '',
            dateFrom: '',
            dateTo: '',
            deliveryStatus: '',
            loading: false,
            searchResults: [],
            hasSearched: false,
            showDetailsModal: false,
            selectedAsin: '',
            itemDetails: [],
            searchTimeout: null,
            deliveryStatusOptions: [
                { label: 'Delivered', value: 'Delivered' },
                { label: 'In Transit', value: 'In Transit' },
                { label: 'Awaiting Shipment', value: 'Awaiting Shipment' },
                { label: 'Payment Pending', value: 'Payment Pending' },
                { label: 'Delivery Exception', value: 'Delivery Exception' },
                { label: 'Cancelled', value: 'Cancelled' },
                { label: 'Refunded', value: 'Refunded' },
                { label: 'Not Found', value: 'Not Found' },
                { label: 'Unknown', value: 'Unknown' },
                { label: 'Active', value: 'Active' },
                { label: 'Delivered (Estimated)', value: 'Delivered (Estimated)' }
            ]
        };
    },
    computed: {
        hasActiveFilters() {
            return this.searchQuery || this.dateFrom || this.dateTo || this.deliveryStatus;
        },
        sellerCount() {
            const allSellers = new Set();
            this.searchResults.forEach(item => {
                if (item.sellers && item.sellers !== 'N/A') {
                    item.sellers.split(',').forEach(seller => {
                        const trimmed = seller.trim();
                        if (trimmed) {
                            allSellers.add(trimmed);
                        }
                    });
                }
            });
            return allSellers.size;
        },
        totalQuantity() {
            return this.searchResults.reduce((sum, item) => sum + (item.total_quantity || 0), 0);
        },
        uniqueAsins() {
            return this.searchResults.filter(item => item.asin).length;
        }
    },
    methods: {
        getDeliveryStatusSeverity(status) {
            const statusMap = {
                'Delivered': 'success',
                'In Transit': 'info',
                'Awaiting Shipment': 'warning',
                'Payment Pending': 'secondary',
                'Delivery Exception': 'danger',
                'Cancelled': 'danger',
                'Refunded': 'danger',
                'Not Found': 'secondary',
                'Unknown': 'secondary',
                'Active': 'info',
                'Delivered (Estimated)': 'success'
            };
            
            return statusMap[status] || 'secondary';
        },

        handleSearchInput() {
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout);
            }

            if (!this.searchQuery && !this.dateFrom && !this.dateTo && !this.deliveryStatus) {
                this.searchResults = [];
                this.hasSearched = false;
                return;
            }

            this.searchTimeout = setTimeout(() => {
                this.searchItems();
            }, 500);
        },

        async searchItems() {
            if (!this.searchQuery && !this.dateFrom && !this.dateTo && !this.deliveryStatus) {
                this.searchResults = [];
                this.hasSearched = false;
                return;
            }

            this.loading = true;
            this.hasSearched = true;

            try {
                console.log('🔍 Searching with params:', {
                    search: this.searchQuery,
                    date_from: this.dateFrom,
                    date_to: this.dateTo,
                    delivery_status: this.deliveryStatus
                });

                const response = await axios.get('/api/orders/incoming-count', {
                    params: {
                        search: this.searchQuery,
                        date_from: this.dateFrom,
                        date_to: this.dateTo,
                        delivery_status: this.deliveryStatus
                    }
                });

                console.log('✅ Search response:', response.data);

                this.searchResults = response.data.data || [];

                if (this.searchResults.length === 0) {
                    console.log('ℹ️ No results found');
                }
            } catch (error) {
                console.error('❌ Error searching items:', error);
                console.error('Error details:', error.response?.data);
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.response?.data?.message || 'Failed to search items. Please try again.',
                    confirmButtonText: 'OK'
                });
            } finally {
                this.loading = false;
            }
        },

        async viewItemDetails(data) {
            this.selectedAsin = data.asin || 'N/A';
            this.loading = true;

            try {
                const response = await axios.get('/api/orders/incoming-count-details', {
                    params: {
                        asin: data.asin,
                        search: this.searchQuery,
                        date_from: this.dateFrom,
                        date_to: this.dateTo,
                        delivery_status: this.deliveryStatus
                    }
                });

                this.itemDetails = response.data.data || [];
                this.showDetailsModal = true;
            } catch (error) {
                console.error('Error fetching item details:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load item details.',
                    confirmButtonText: 'OK'
                });
            } finally {
                this.loading = false;
            }
        },

        clearSearch() {
            this.searchQuery = '';
            this.dateFrom = '';
            this.dateTo = '';
            this.deliveryStatus = '';
            this.searchResults = [];
            this.hasSearched = false;
        },

        formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        },

        exportToCSV() {
            if (this.searchResults.length === 0) return;

            const headers = ['ASIN', 'Title', 'Metakeyword', 'Total Quantity', 'Item Count', 'Earliest Delivery', 'Latest Delivery'];
            const rows = this.searchResults.map(item => [
                item.asin || 'N/A',
                item.title || 'N/A',
                item.metakeyword || 'N/A',
                item.total_quantity,
                item.item_count,
                this.formatDate(item.earliest_delivery),
                this.formatDate(item.latest_delivery)
            ]);

            const csvContent = [
                headers.join(','),
                ...rows.map(row => row.map(cell => `"${cell}"`).join(','))
            ].join('\n');

            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `incoming_count_${new Date().toISOString().split('T')[0]}.csv`;
            link.click();
            URL.revokeObjectURL(url);

            Swal.fire({
                icon: 'success',
                title: 'Exported!',
                text: 'CSV file has been downloaded.',
                timer: 2000,
                showConfirmButton: false
            });
        },

        handleClose() {
            this.$emit('update:visible', false);
            this.$emit('close');
        },

        handleVisibilityChange(value) {
            this.$emit('update:visible', value);
            if (!value) {
                this.$emit('close');
                this.resetModal();
            }
        },

        resetModal() {
            this.searchQuery = '';
            this.dateFrom = '';
            this.dateTo = '';
            this.deliveryStatus = '';
            this.searchResults = [];
            this.hasSearched = false;
            this.loading = false;
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout);
            }
        }
    },
    watch: {
        visible(newVal) {
            if (newVal) {
                this.resetModal();
            }
        }
    }
};
</script>

<style scoped>
.incoming-counter-container {
    padding: 1rem 0;
}

.filters-row {
    display: grid;
    grid-template-columns: 1fr auto auto auto auto;
    gap: 0.75rem;
    align-items: center;
}

.search-field {
    flex: 1;
    min-width: 0;
}

.search-field :deep(.p-iconfield) {
    width: 100%;
}

.date-field {
    width: 180px;
}

.date-field input {
    width: 100%;
}

.status-field {
    width: 200px;
}

.active-filters-compact {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.cursor-pointer {
    cursor: pointer;
}

.results-summary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 1.5rem;
    color: white;
}

.summary-card {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: rgba(255, 255, 255, 0.1);
    padding: 1rem;
    border-radius: 8px;
    backdrop-filter: blur(10px);
}

.summary-icon {
    font-size: 2rem;
    opacity: 0.9;
}

.summary-label {
    font-size: 0.875rem;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.summary-value {
    font-size: 1.75rem;
    font-weight: 700;
}

.highlight-card {
    background: rgba(255, 255, 255, 0.2) !important;
    border: 2px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.summary-icon-large {
    font-size: 2.5rem !important;
    opacity: 1 !important;
}

.summary-value-large {
    font-size: 2.5rem !important;
    font-weight: 800 !important;
    color: #fff;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.asin-cell .badge {
    font-size: 0.875rem;
    padding: 0.5rem 0.75rem;
}

.title-cell {
    line-height: 1.5;
}

.quantity-badge {
    font-size: 1rem;
    padding: 0.5rem 1rem;
    font-weight: 600;
}

.date-range-cell {
    font-size: 0.875rem;
    line-height: 1.6;
}

.dialog-footer {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
}

@media (max-width: 1024px) {
    .filters-row {
        grid-template-columns: 1fr;
    }
    
    .date-field,
    .status-field {
        width: 100%;
    }
}

@media (max-width: 768px) {
    .summary-card {
        grid-template-columns: 1fr;
    }

    .dialog-footer {
        flex-direction: column;
    }
    
    .filters-row {
        gap: 0.5rem;
    }
}
</style>