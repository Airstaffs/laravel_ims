<template>
    <div class="vue-container fnsku-module">
        <div class="top-header">
            <h1 class="module-title">FNSKU Module</h1>
            <div class="header-buttons">
                <button @click="showInsertFnskuModal" class="btn fnsku-button">
                    <i class="bi bi-plus"></i> ADD FNSKU
                </button>
            </div>
        </div>

        <!-- Pagination with centered layout -->
        <div class="pagination-container">
            <div class="pagination-wrapper">
                <div class="pagination">
                    <button @click="prevPage" :disabled="currentPage === 1" class="pagination-button">
                        <i class="fas fa-chevron-left"></i> Back
                    </button>
                    <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
                    <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button">
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>

                <div class="per-page-selector">
                    <select v-model="perPage" @change="changePerPage" class="per-page-select">
                        <option v-for="option in [10, 15, 20, 50, 100]" :key="option" :value="option">
                            {{ option }} per page
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Desktop Table Container -->
        <div class="table-container desktop-view">
            <table class="table">
                <thead>
                    <tr>
                        <th class="check-column width-2">
                            <input type="checkbox" @click="toggleAll" v-model="selectAll" />
                        </th>
                        <th>Details</th>
                        <th>Order Details</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in inventory" :key="item.FNSKUID">
                        <td>
                            <input type="checkbox" v-model="item.checked" />
                        </td>
                        <td>
                            <div class="product-container">
                                <div class="product-image clickable">
                                    <img :src="item.imageUrl" alt="Product Image" class="product-thumbnail" />
                                </div>
                                <div class="product-info">
                                    <div class="product-name clickable">
                                        {{ item.astitle }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <ul class="list-unstyled product-details">
                                <li>
                                    <p><strong>ID:</strong> {{ item.FNSKUID }}</p>
                                </li>
                                <li>
                                    <p><strong>ASIN:</strong> {{ item.ASIN }}</p>
                                </li>
                                <li>
                                    <p><strong>FNSKU:</strong> {{ item.FNSKU }}</p>
                                </li>
                                <li>
                                    <p><strong>Condition:</strong> {{ item.grading }}</p>
                                </li>
                                <li>
                                    <p><strong>Status:</strong> {{ item.fnsku_status }}</p>
                                </li>
                                <li>
                                    <p><strong>Store:</strong> {{ item.storename }}</p>
                                </li>
                                <li>
                                    <p><strong>Units:</strong> {{ item.Units }}</p>
                                </li>
                            </ul>
                        </td>

                        <td>
                            {{ item.totalquantity }}
                            <button @click="toggleDetails(index)" class="more-details-btn">
                                {{ expandedRows[index] ? 'Less Details' : 'More Details' }}
                            </button>
                        </td>
                    </tr>
                    <tr v-if="expandedRows[index]" class="expanded-row">
                        <td colspan="4">
                            <div class="expanded-content">
                                <strong>Product Name:</strong> {{ item.astitle }}
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Bottom pagination (also centered) -->
            <div class="pagination-container">
                <div class="pagination-wrapper">
                    <div class="pagination">
                        <button @click="prevPage" :disabled="currentPage === 1" class="pagination-button">
                            <i class="fas fa-chevron-left"></i> Back
                        </button>
                        <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
                        <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button">
                            Next <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Insert FNSKU Modal -->
            <div class="fnsku modal" v-if="isInsertFnskuModalVisible">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Add New FNSKU</h2>
                        <span class="close" @click="hideInsertFnskuModal">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="newFnsku">FNSKU:</label>
                            <input type="text" id="newFnsku" v-model="newFnskuData.fnsku" placeholder="Enter FNSKU"
                                class="form-control" ref="newFnskuInput" @input="focusNext('newMskuInput')" />
                        </div>

                        <div class="form-group">
                            <label for="newMsku">MSKU:</label>
                            <input type="text" id="newMsku" v-model="newFnskuData.msku" placeholder="Enter MSKU"
                                class="form-control" ref="newMskuInput" @input="focusNext('newAsinInput')" />
                        </div>

                        <div class="form-group">
                            <label for="newAsin">ASIN:</label>
                            <input type="text" id="newAsin" v-model="newFnskuData.asin" placeholder="Enter ASIN"
                                class="form-control" ref="newAsinInput" @input="focusNext('newTitleInput')" />
                        </div>

                        <div class="form-group">
                            <label for="newTitle">Title:</label>
                            <input type="text" id="newTitle" v-model="newFnskuData.astitle"
                                placeholder="Enter Product Title" class="form-control" ref="newTitleInput"
                                @input="focusNext('newGradingInput')" />
                        </div>

                        <div class="form-group">
                            <label for="newGrading">Grading:</label>
                            <select id="newGrading" v-model="newFnskuData.grading" class="form-control"
                                ref="newGradingInput">
                                <option value="New">New</option>
                                <option value="Like New">Like New</option>
                                <option value="Very Good">Very Good</option>
                                <option value="Good">Good</option>
                                <option value="Acceptable">Acceptable</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="newStoreName">Store Name:</label>
                            <select id="newStoreName" v-model="newFnskuData.storeName" class="form-control"
                                ref="newStoreNameInput">
                                <option value="Allrenewed">Allrenewed</option>
                                <option value="Renovartech">Renovartech</option>
                            </select>
                        </div>

                        <div class="form-actions">
                            <button @click="saveNewFnsku" class="btn-save">Save FNSKU</button>
                            <button @click="hideInsertFnskuModal" class="btn-cancel">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mobile-view">
            <div class="mobile-cards">
                <div class="mobile-card" v-for="(item, index) in inventory" :key="item.FNSKUID">
                    <div class="mobile-card-header">
                        <div class="mobile-checkbox">
                            <input type="checkbox" v-model="item.checked" />
                        </div>
                        <div class="mobile-product-image clickable">
                            <img :src="item.imageUrl" alt="Product Image" class="product-thumbnail" />
                        </div>
                        <div class="mobile-product-info">
                            <h3 class="mobile-product-name clickable">
                                {{ item.astitle }}
                            </h3>
                        </div>
                    </div>

                    <div class="mobile-card-details">
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">ID:</span>
                            <span class="mobile-detail-value">{{ item.FNSKUID }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">ASIN:</span>
                            <span class="mobile-detail-value">{{ item.ASIN }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">FNSKU:</span>
                            <span class="mobile-detail-value">{{ item.FNSKU }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Condition:</span>
                            <span class="mobile-detail-value">{{ item.Grading }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Status:</span>
                            <span class="mobile-detail-value">{{ item.fnsku_status }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Store:</span>
                            <span class="mobile-detail-value">{{ item.storename }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Units:</span>
                            <span class="mobile-detail-value">{{ item.Units }}</span>
                        </div>
                    </div>

                    <div class="mobile-card-actions">
                        <button @click="toggleDetails(index)" class="mobile-btn mobile-btn-details">
                            {{ expandedRows[index] ? 'Less Details' : 'More Details' }}
                        </button>
                    </div>

                    <div v-if="expandedRows[index]" class="mobile-expanded-content">
                        <div class="mobile-section">
                            <strong>Product Name:</strong> {{ item.astitle }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import FNSKU from "./fnsku.js";
    export default FNSKU;
</script>
