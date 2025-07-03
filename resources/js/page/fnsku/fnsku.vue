<template>
    <div class="vue-container fnsku-module">
        <div class="top-header">
            <div class="header-buttons">
                <button @click="showInsertFnskuModal" class="btn fnsku-button">
                    <i class="bi bi-plus"></i> ADD FNSKU
                </button>
            </div>
        </div>

        <h2 class="module-title">FNSKU Module</h2>

        <!-- Desktop Table Container -->
        <div class="table-container desktop-view">
            <table>
                <thead>
                    <tr>
                        <th class="sticky-header first-col">
                            <input
                                type="checkbox"
                                @click="toggleAll"
                                v-model="selectAll"
                            />
                        </th>
                        <th class="">ASIN</th>
                        <th class="">FNSKU</th>
                        <th class="">MSKU</th>
                        <th class="">Grading</th>
                        <th class="">Status</th>
                        <th class="">Store Name</th>
                        <th class="">Units</th>
                        <th class="">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in inventory" :key="item.FNSKUID">
                        <td class="sticky-col first-col">
                            <input type="checkbox" v-model="item.checked" />
                        </td>
                        <td>
                            <span>
                                <strong>{{ item.ASIN }}</strong>
                            </span>
                        </td>
                        <td>
                            <span>
                                <strong>{{ item.FNSKU }}</strong>
                            </span>
                        </td>
                        <td>
                            <span>
                                <strong>{{ item.MSKU }}</strong>
                            </span>
                        </td>
                        <td>
                            <span
                                class="badge text-white"
                                :class="{
                                    'bg-primary':
                                        item.grading === 'UsedVeryGood',
                                    'bg-warning': item.grading === 'UsedGood',
                                    'bg-info': item.grading === 'UsedLikeNew',
                                    'bg-secondary': ![
                                        'UsedVeryGood',
                                        'UsedGood',
                                        'UsedLikeNew',
                                    ].includes(item.grading),
                                }"
                            >
                                {{ item.grading }}
                            </span>
                        </td>
                        <td>
                            <span
                                class="badge text-white"
                                :class="
                                    item.fnsku_status === 'available'
                                        ? 'bg-success'
                                        : 'bg-danger'
                                "
                            >
                                {{ item.fnsku_status }}
                            </span>
                        </td>
                        <td>
                            <span>
                                <strong>{{ item.storename }}</strong>
                            </span>
                        </td>
                        <td>
                            <span>
                                <strong>{{ item.Units }}</strong>
                            </span>
                        </td>
                        <td>
                            {{ item.totalquantity }}
                            <button
                                @click="toggleDetails(index)"
                                class="more-details-btn"
                            >
                                {{
                                    expandedRows[index]
                                        ? "Less Details"
                                        : "More Details"
                                }}
                            </button>
                        </td>
                    </tr>
                    <tr v-if="expandedRows[index]" class="expanded-row">
                        <td colspan="4">
                            <div class="expanded-content">
                                <strong>Product Name:</strong>
                                {{ item.astitle }}
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile View -->
        <div class="mobile-view">
            <div class="mobile-cards">
                <div
                    class="mobile-card"
                    v-for="(item, index) in inventory"
                    :key="item.FNSKUID"
                >
                    <div class="mobile-card-details">
                        <div class="mobile-checkbox">
                            <input type="checkbox" v-model="item.checked" />
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">ASIN:</span>
                            <span class="mobile-detail-value">{{
                                item.ASIN
                            }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">FNSKU:</span>
                            <span class="mobile-detail-value">{{
                                item.FNSKU
                            }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">MSKU:</span>
                            <span class="mobile-detail-value">{{
                                item.MSKU
                            }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Grading:</span>
                            <span
                                class="mobile-detail-value badge text-white"
                                :class="{
                                    'bg-primary':
                                        item.grading === 'UsedVeryGood',
                                    'bg-warning': item.grading === 'UsedGood',
                                    'bg-info': item.grading === 'UsedLikeNew',
                                    'bg-secondary': ![
                                        'UsedVeryGood',
                                        'UsedGood',
                                        'UsedLikeNew',
                                    ].includes(item.grading),
                                }"
                            >
                                {{ item.grading }}
                            </span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Status:</span>
                            <span
                                class="mobile-detail-value badge text-white"
                                :class="
                                    item.fnsku_status === 'available'
                                        ? 'bg-success'
                                        : 'bg-danger'
                                "
                            >
                                {{ item.fnsku_status }}
                            </span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Store name:</span>
                            <span class="mobile-detail-value">{{
                                item.storename
                            }}</span>
                        </div>
                        <div class="mobile-detail-row">
                            <span class="mobile-detail-label">Units:</span>
                            <span class="mobile-detail-value">{{
                                item.Units
                            }}</span>
                        </div>
                    </div>

                    <hr />

                    <div class="mobile-card-actions">
                        <button
                            @click="toggleDetails(index)"
                            class="mobile-btn mobile-btn-details"
                        >
                            <i class="fas fa-info-circle"></i>
                            {{
                                expandedRows[index]
                                    ? "Less Details"
                                    : "More Details"
                            }}
                        </button>
                    </div>

                    <hr v-if="expandedRows[index]" />

                    <div
                        v-if="expandedRows[index]"
                        class="mobile-expanded-content"
                    >
                        <div class="mobile-section">
                            <strong>Product Name:</strong> {{ item.astitle }}
                        </div>
                        <!-- Add more fields if needed -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination with centered layout -->
        <div class="pagination-container">
            <div class="pagination-wrapper">
                <div class="per-page-selector">
                    <span>Rows per page</span>
                    <select
                        v-model="perPage"
                        @change="changePerPage"
                        class="per-page-select"
                    >
                        <option
                            v-for="option in [10, 15, 20, 50, 100]"
                            :key="option"
                            :value="option"
                        >
                            {{ option }}
                        </option>
                    </select>
                </div>

                <div class="pagination">
                    <button
                        @click="prevPage"
                        :disabled="currentPage === 1"
                        class="pagination-button"
                    >
                        <i class="fas fa-chevron-left"></i> Back
                    </button>
                    <span class="pagination-info"
                        >Page {{ currentPage }} of {{ totalPages }}</span
                    >
                    <button
                        @click="nextPage"
                        :disabled="currentPage === totalPages"
                        class="pagination-button"
                    >
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Insert FNSKU Modal -->
        <div class="modal fnsku-modal" v-if="isInsertFnskuModalVisible">
            <div class="modal-overlay" @click="hideInsertFnskuModal"></div>

            <div class="modal-content">
                <div class="modal-header">
                    <h2>Add New FNSKU</h2>
                    <span class="close" @click="hideInsertFnskuModal"
                        >&times;</span
                    >
                </div>

                <div class="modal-body">
                    <form class="fnskuForm">
                        <fieldset>
                            <label><span>FNSKU:</span></label>
                            <input
                                type="text"
                                id="newFnsku"
                                v-model="newFnskuData.fnsku"
                                placeholder="Enter FNSKU"
                                class="form-control"
                                ref="newFnskuInput"
                                @input="focusNext('newMskuInput')"
                            />
                        </fieldset>

                        <fieldset>
                            <label><span>MSKU:</span></label>
                            <input
                                type="text"
                                id="newMsku"
                                v-model="newFnskuData.msku"
                                placeholder="Enter MSKU"
                                class="form-control"
                                ref="newMskuInput"
                                @input="focusNext('newAsinInput')"
                            />
                        </fieldset>

                        <fieldset>
                            <label><span>ASIN:</span></label>
                            <input
                                type="text"
                                id="newAsin"
                                v-model="newFnskuData.asin"
                                placeholder="Enter ASIN"
                                class="form-control"
                                ref="newAsinInput"
                                @input="focusNext('newTitleInput')"
                            />
                        </fieldset>

                        <fieldset>
                            <label><span>Title:</span></label>
                            <input
                                type="text"
                                id="newTitle"
                                v-model="newFnskuData.astitle"
                                placeholder="Enter Product Title"
                                class="form-control"
                                ref="newTitleInput"
                                @input="focusNext('newGradingInput')"
                            />
                        </fieldset>

                        <fieldset>
                            <label><span>Grading:</span></label>
                            <select
                                id="newGrading"
                                v-model="newFnskuData.grading"
                                class="form-control"
                                ref="newGradingInput"
                            >
                                <option value="New">New</option>
                                <option value="Like New">Like New</option>
                                <option value="Very Good">Very Good</option>
                                <option value="Good">Good</option>
                                <option value="Acceptable">Acceptable</option>
                            </select>
                        </fieldset>

                        <fieldset>
                            <label><span>Store Name:</span></label>
                            <select
                                id="newStoreName"
                                v-model="newFnskuData.storeName"
                                class="form-control"
                                ref="newStoreNameInput"
                            >
                                <option value="Allrenewed">Allrenewed</option>
                                <option value="Renovartech">Renovartech</option>
                            </select>
                        </fieldset>

                        <button @click="saveNewFnsku" class="btn btn-process">
                            Save FNSKU
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import FNSKU from "./fnsku.js";
export default FNSKU;
</script>
