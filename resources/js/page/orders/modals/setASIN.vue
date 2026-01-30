<template>
    <Dialog
        :visible="visible"
        modal
        :style="{ width: '90%', maxWidth: '1200px' }"
        :pt="{
            root: { class: 'set-asin-modal' },
        }"
        @update:visible="$emit('update:visible', $event)"
        @hide="closeModal"
    >
        <template #header>
            <h5>Set ASIN - RT#{{ rtCounter }} - {{ productTitle }}</h5>
        </template>

        <div class="set-asin-container">
            <!-- Search Bar - Better Layout -->
            <div class="search-section mb-3">
                <InputText
                    v-model="searchQuery"
                    placeholder="Search by ASIN/Title/Metakeyword/EAN/UPC"
                    fluid
                    size="large"
                    @input="debouncedSearch"
                    @keyup.enter="fetchAsins"
                    class="search-input"
                >
                    <template #prefix>
                        <i class="pi pi-search"></i>
                    </template>
                </InputText>
            </div>

            <!-- ASIN Table - Simplified Columns -->
            <div class="asin-table-container">
                <DataTable
                    :value="asins"
                    :loading="loading"
                    paginator
                    :rows="10"
                    :rowsPerPageOptions="[10, 20, 50]"
                    scrollable
                    scrollHeight="450px"
                    :rowHover="true"
                >
                    <Column field="asinimg" header="Image" style="width: 80px">
                        <template #body="slotProps">
                            <img
                                :src="getImageUrl(slotProps.data.asinimg)"
                                alt="ASIN"
                                style="width: 60px; height: 60px; object-fit: contain"
                                @error="handleImageError"
                            />
                        </template>
                    </Column>

                    <Column field="ASIN" header="ASIN" sortable style="width: 130px">
                        <template #body="slotProps">
                            <strong class="text-primary">{{ slotProps.data.ASIN }}</strong>
                        </template>
                    </Column>

                    <Column field="display_title" header="Title" sortable style="min-width: 300px">
                        <template #body="slotProps">
                            <div class="title-cell">
                                {{ slotProps.data.display_title || slotProps.data.AStitle }}
                            </div>
                        </template>
                    </Column>

                    <Column field="metakeyword" header="Metakeyword" sortable style="width: 200px">
                        <template #body="slotProps">
                            <div class="meta-cell">
                                {{ slotProps.data.metakeyword || '-' }}
                            </div>
                        </template>
                    </Column>

                    <Column field="EAN" header="EAN" style="width: 130px">
                        <template #body="slotProps">
                            {{ slotProps.data.EAN || '-' }}
                        </template>
                    </Column>

                    <Column field="UPC" header="UPC" style="width: 130px">
                        <template #body="slotProps">
                            {{ slotProps.data.UPC || '-' }}
                        </template>
                    </Column>

                    <Column header="Action" style="width: 120px; text-align: center">
                        <template #body="slotProps">
                            <Button
                                label="Set ASIN"
                                icon="pi pi-check"
                                severity="success"
                                size="small"
                                @click="selectAsin(slotProps.data)"
                            />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <template #footer>
            <Button
                label="Cancel"
                icon="pi pi-times"
                severity="secondary"
                @click="closeModal"
            />
        </template>
    </Dialog>
</template>

<script>
import { Dialog, Button, InputText, DataTable, Column, Tag } from 'primevue';
import { DEFAULT_IMAGE } from '../../../constant';

export default {
    name: 'SetASINModal',
    components: {
        Dialog,
        Button,
        InputText,
        DataTable,
        Column,
        Tag
    },
    props: {
        visible: {
            type: Boolean,
            required: true
        },
        productId: {
            type: [String, Number],
            default: ''
        },
        rtCounter: {
            type: String,
            default: ''
        },
        productTitle: {
            type: String,
            default: ''
        }
    },
    emits: ['update:visible', 'asin-selected'],
    data() {
        return {
            searchQuery: '',
            asins: [],
            loading: false,
            debounceTimeout: null,
            defaultImage: DEFAULT_IMAGE
        };
    },
    watch: {
        visible(newVal) {
            if (newVal) {
                this.fetchAsins();
            }
        }
    },
    methods: {
        debouncedSearch() {
            clearTimeout(this.debounceTimeout);
            this.debounceTimeout = setTimeout(() => {
                this.fetchAsins();
            }, 500);
        },

        async fetchAsins() {
            this.loading = true;
            try {
                const response = await axios.get('/api/orders/asin-list', {
                    params: {
                        search: this.searchQuery,
                        per_page: 100
                    }
                });

                this.asins = response.data.data || [];
            } catch (error) {
                console.error('Error fetching ASINs:', error);
                // Show error using SweetAlert2 instead
                if (window.Swal) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to load ASINs',
                        confirmButtonText: 'OK'
                    });
                }
            } finally {
                this.loading = false;
            }
        },

        getImageUrl(asinimg) {
            if (!asinimg) return this.defaultImage;
            
            // If it's already a full URL, return it
            if (asinimg.startsWith('http')) {
                return asinimg;
            }
            
            // If it's a relative path starting with 'images/', use it as is
            if (asinimg.startsWith('images/')) {
                return `/${asinimg}`;
            }
            
            // Otherwise assume it's just a filename
            return `/images/asinimg/${asinimg}`;
        },

        handleImageError(event) {
            event.target.src = this.defaultImage;
        },

        selectAsin(asin) {
            this.$emit('asin-selected', {
                ASIN: asin.ASIN,
                title: asin.display_title || asin.AStitle,
                metakeyword: asin.metakeyword,
                EAN: asin.EAN,
                UPC: asin.UPC
            });
            this.closeModal();
        },

        closeModal() {
            this.$emit('update:visible', false);
            this.searchQuery = '';
            this.asins = [];
        }
    }
};
</script>

<style scoped>
.set-asin-container {
    padding: 0;
}

/* Better Search Layout */
.search-section {
    margin-bottom: 1.5rem;
}

.search-input {
    font-size: 1rem;
}

.search-input :deep(.p-inputtext) {
    padding: 0.75rem 1rem;
    font-size: 1rem;
}

/* Table Container */
.asin-table-container {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
}

/* Table Styling */
:deep(.p-datatable) {
    font-size: 0.9rem;
}

:deep(.p-datatable-thead > tr > th) {
    background-color: #f8f9fa;
    font-weight: 600;
    padding: 0.875rem;
    white-space: nowrap;
}

:deep(.p-datatable-tbody > tr > td) {
    padding: 0.875rem;
}

:deep(.p-datatable-tbody > tr:hover) {
    background-color: #f8f9fa;
    cursor: pointer;
}

/* Title Cell */
.title-cell {
    word-break: break-word;
    line-height: 1.4;
}

.meta-cell {
    font-size: 0.85rem;
    color: #6c757d;
}

/* Remove horizontal scroll */
:deep(.p-datatable-wrapper) {
    overflow-x: hidden !important;
}

/* Modal responsive */
@media (max-width: 768px) {
    .set-asin-container {
        padding: 0;
    }

    :deep(.p-dialog) {
        width: 100% !important;
        height: 100vh !important;
        max-height: 100vh !important;
        margin: 0 !important;
    }
    
    :deep(.p-datatable-thead > tr > th),
    :deep(.p-datatable-tbody > tr > td) {
        padding: 0.5rem;
        font-size: 0.8rem;
    }
}
</style>