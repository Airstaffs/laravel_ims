<template>
    <div class="vue-container">
        <div class="header-actions">
            <h1 class="vue-title">FNSKU LIST</h1>
            <button @click="showInsertFnskuModal" class="insertfnsku-btn">
                <i class="bi bi-plus"></i> ADD FNSKU
            </button>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" @click="toggleAll" v-model="selectAll" />
                            <span class="header-date"></span>
                        </th>
                        <th>Details</th>
                        <th>Order Details</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in inventory" :key="item.FNSKUID">
                        <td>
                            <div class="checkbox-container">
                                <input type="checkbox" v-model="item.checked" />
                            </div>
                            <img :src="item.imageUrl" alt="Product Image" class="product-thumbnail" />
                        </td>
                        <td class="vue-details">
                            <span class="product-name">{{ item.astitle }}</span>
                        </td>
                        <td class="vue-details">
                            <span><strong>ID:</strong> {{ item.FNSKUID }}</span><br />
                            <span><strong>ASIN:</strong> {{ item.ASIN }}</span><br />
                            <span><strong>FNSKU:</strong> {{ item.FNSKU }}</span><br />
                            <span><strong>Condition:</strong> {{ item.grading }}</span><br />
                            <span><strong>Status:</strong> {{ item.fnsku_status }}</span><br />
                            <span><strong>Store:</strong> {{ item.storename }}</span><br />
                            <span><strong>Units:</strong> {{ item.Units }}</span><br />
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
            <!-- Pagination -->
            <div class="pagination">
                <button @click="prevPage" :disabled="currentPage === 1" class="pagination-button">Previous</button>
                <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
                <button @click="nextPage" :disabled="currentPage === totalPages" class="pagination-button">Next</button>
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
</template>

<script>
    import FNSKU from "./fnsku.js";
    export default FNSKU;
</script>
