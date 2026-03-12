<template>
    <div class="vue-container switcheru-module">
        <div class="d-flex align-items-center justify-content-between flex-wrap mb-4">
            <TitlePage title="Switcheru List"
                subtitle="Serial mismatches — compare the serial we sent vs. what was returned." />
        </div>

        <!-- ========== Desktop Table ========== -->
        <AnimateDiv :delay="200" class="px-4">
            <XDataTable :value="switcherus" :loading="loading" :columns="columns" :paginator="false"
                tableClass="desktop-view" dataKey="id">

                <template #date="{ data }">
                    <p>{{ formatDate(data.created_at) }}</p>
                </template>

                <template #rtNumber="{ data }">
                    {{ formatRTNumber(data.rtcounter) }}
                </template>

                <template #returnId="{ data }">
                    <span>{{ data.returnid || "—" }}</span>
                </template>

                <template #buyer="{ data }">
                    <p>{{ data.buyer || "Unknown" }}</p>
                </template>

                <template #sentSerial="{ data }">
                    <div class="serial-cell serial-sent">
                        <div class="serial-thumb-row" @click="openSerialImageModal(data, 'sent')" style="cursor:pointer">
                            <img :src="getSerialImageUrl(data, 'sent')" alt="Sent"
                                class="serial-thumb" @error="handleImageError" />
                            <span v-if="countSerialImages(data.sentImages) > 1" class="thumb-badge">
                                +{{ countSerialImages(data.sentImages) - 1 }}
                            </span>
                        </div>
                        <span class="serial-text" :title="data.sendserial">{{ truncateSerial(data.sendserial) }}</span>
                    </div>
                </template>

                <template #arrow="{ data }">
                    <div class="arrow-cell">
                        <i class="fas fa-exchange-alt switch-arrow"></i>
                    </div>
                </template>

                <template #receivedSerial="{ data }">
                    <div class="serial-cell serial-received">
                        <div class="serial-thumb-row" @click="openSerialImageModal(data, 'received')" style="cursor:pointer">
                            <img :src="getSerialImageUrl(data, 'received')" alt="Received"
                                class="serial-thumb" @error="handleImageError" />
                            <span v-if="countSerialImages(data.receivedImages) > 1" class="thumb-badge">
                                +{{ countSerialImages(data.receivedImages) - 1 }}
                            </span>
                        </div>
                        <span class="serial-text" :title="data.receiveserial">{{ truncateSerial(data.receiveserial) }}</span>
                    </div>
                </template>

                <template #productTitle="{ data }">
                    <p class="product-title-cell">{{ data.product_title || "—" }}</p>
                </template>

                <template #actions="{ data }">
                    <div class="d-flex gap-1">
                        <Button label="Compare" severity="warning" icon="pi pi-arrows-h" variant="text" size="small"
                            @click="openCompareModal(data)" />
                        <Button label="Details" severity="contrast" icon="pi pi-info-circle" variant="text"
                            class="text-primary" size="small" @click="handleShowDetails(data)" />
                    </div>
                </template>
            </XDataTable>
        </AnimateDiv>

        <!-- ========== Mobile Cards ========== -->
        <AnimateDiv :delay="200" class="mobile-view">
            <div class="mobile-cards">
                <div v-if="loading" class="loading-spinner-mobile">
                    <i class="fas fa-spinner fa-spin"></i> Loading...
                </div>
                <div v-else-if="switcherus.length === 0" class="no-data-mobile">No switcheru records found</div>

                <AnimateDiv v-else v-for="(item, index) in switcherus" :key="item.id" class="mobile-card" :delay="index * 100">
                    <div class="mobile-card-header">
                        <div class="mobile-switcheru-badge">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="mobile-return-info">
                            <h5 class="mobile-return-title">{{ formatRTNumber(item.rtcounter) }}</h5>
                            <div class="mobile-return-id" v-if="item.returnid">Return ID: {{ item.returnid }}</div>
                            <div class="mobile-return-date">{{ formatDate(item.created_at) }}</div>
                        </div>
                    </div>

                    <Divider />

                    <!-- Side-by-side serial comparison -->
                    <div class="mobile-serial-compare">
                        <div class="mobile-serial-side" @click="openSerialImageModal(item, 'sent')">
                            <span class="mobile-serial-label sent-label">Sent</span>
                            <img :src="getSerialImageUrl(item, 'sent')" class="mobile-serial-img" @error="handleImageError" />
                            <span class="mobile-serial-value">{{ truncateSerial(item.sendserial, 14) }}</span>
                        </div>
                        <div class="mobile-serial-arrow">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                        <div class="mobile-serial-side" @click="openSerialImageModal(item, 'received')">
                            <span class="mobile-serial-label received-label">Received</span>
                            <img :src="getSerialImageUrl(item, 'received')" class="mobile-serial-img" @error="handleImageError" />
                            <span class="mobile-serial-value">{{ truncateSerial(item.receiveserial, 14) }}</span>
                        </div>
                    </div>

                    <Divider />

                    <div class="mobile-card-details" :style="{ fontSize: '14px' }">
                        <div class="mobile-detail-row">
                            <span class="fw-semibold">Buyer:</span>
                            <span class="mobile-detail-value">{{ item.buyer || "Unknown" }}</span>
                        </div>
                        <div class="mobile-detail-row" v-if="item.product_title">
                            <span class="fw-semibold">Product:</span>
                            <span class="mobile-detail-value">{{ item.product_title }}</span>
                        </div>
                         
                        <div class="mobile-detail-row" v-if="item.returnid">
                            <span class="fw-semibold">Return ID:</span>
                            <span class="mobile-detail-value">{{ item.returnid }}</span>
                        </div>

                    </div>

                    <Divider />

                    <div class="mobile-card-actions">
                        <Button @click="openCompareModal(item)" icon="pi pi-arrows-h" label="Compare" size="small" severity="warning" />
                        <Button @click="handleShowDetails(item)" icon="pi pi-info-circle" label="Details" size="small" />
                    </div>
                </AnimateDiv>
            </div>
        </AnimateDiv>

        <!-- ========== Pagination ========== -->
        <Paginator :first="first" :rows="perPage" :total-records="totalRecords"
            :rows-per-page-options="[10, 20, 50]"
            template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords}" class="small-paginator"
            @page="onPageChange" />

        <!-- ========== Details Modal ========== -->
        <Dialog v-model:visible="viewDetailsModal" modal header="Switcheru Details" class="view-details-dialog"
            :pt="{ root: { class: 'mobile-fullscreen-dialog' } }" style="width: 50%">
            <div class="details-container">
                <div class="item-container"><span>RT#:</span><span>{{ formatRTNumber(selectedItem.rtcounter) }}</span></div>
                <div class="item-container"><span>Buyer:</span><span>{{ selectedItem.buyer || "Unknown" }}</span></div>
                <div class="item-container"><span>Product:</span><span>{{ selectedItem.product_title || "N/A" }}</span></div>
                <div class="item-container"><span>Return ID:</span><span>{{ selectedItem.returnid || "N/A" }}</span></div>
                <div class="item-container"><span>Sent Serial:</span><span class="serial-mono sent-text">{{ selectedItem.sendserial || "N/A" }}</span></div>
                <div class="item-container"><span>Received Serial:</span><span class="serial-mono received-text">{{ selectedItem.receiveserial || "N/A" }}</span></div>
                <div class="item-container"><span>Detected:</span><span>{{ formatDate(selectedItem.created_at) }}</span></div>
            </div>
        </Dialog>

        <!-- ========== Compare Modal (side-by-side images) ========== -->
        <Dialog v-model:visible="showCompareModal" modal header="Serial Comparison" class="compare-dialog"
            :pt="{ root: { class: 'mobile-fullscreen-dialog' } }" style="width: 70%">
            <div class="compare-container" v-if="compareItem">
                <!-- Sent side -->
                <div class="compare-side">
                    <div class="compare-side-header sent-header">
                        <i class="fas fa-arrow-up"></i> Sent Serial
                    </div>
                    <div class="compare-serial-value">{{ compareItem.sendserial || "N/A" }}</div>
                    <div class="compare-images-grid">
                        <img v-for="(img, idx) in getCompareImages(compareItem, 'sent')" :key="'sent-' + idx"
                            :src="img" class="compare-img" @error="handleImageError"
                            @click="openSerialImageModal(compareItem, 'sent')" />
                    </div>
                </div>

                <!-- Arrow -->
                <div class="compare-arrow">
                    <i class="fas fa-exchange-alt"></i>
                </div>

                <!-- Received side -->
                <div class="compare-side">
                    <div class="compare-side-header received-header">
                        <i class="fas fa-arrow-down"></i> Received Serial
                    </div>
                    <div class="compare-serial-value">{{ compareItem.receiveserial || "N/A" }}</div>
                    <div class="compare-images-grid">
                        <img v-for="(img, idx) in getCompareImages(compareItem, 'received')" :key="'recv-' + idx"
                            :src="img" class="compare-img" @error="handleImageError"
                            @click="openSerialImageModal(compareItem, 'received')" />
                    </div>
                </div>
            </div>
            <div class="compare-footer">
                <div class="compare-meta">
                    <span><strong>RT#:</strong> {{ formatRTNumber(compareItem.rtcounter) }}</span>
                    <span><strong>Return ID:</strong> {{ compareItem.returnid || "N/A" }}</span>
                    <span><strong>Buyer:</strong> {{ compareItem.buyer || "Unknown" }}</span>
                    <span><strong>Detected:</strong> {{ formatDate(compareItem.created_at) }}</span>
                </div>
            </div>
        </Dialog>

        <!-- ========== Image Gallery Modal ========== -->
        <ViewImageGalleryModal :showImageModal="showImageModal" :closeImageModal="closeImageModal"
            :ProductTitle="ProductTitle" :regularImages="regularImages" :capturedImages="capturedImages"
            :handleImageError="handleImageError" />
    </div>
</template>

<script>
import switcheruMixin from "./switcheru.js";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import { Button, Dialog, Divider, Paginator } from "primevue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";
import ViewImageGalleryModal from "../../components/ViewImageGalleryModal/ViewImageGalleryModal.vue";

const TABLE_COLUMNS = [
    { header: "RT#", slot: "rtNumber", bodyStyle: "font-size: 14px", sortable: true },
    { header: "Product", slot: "productTitle", bodyStyle: "font-size: 14px", sortable: true },
    { header: "Return ID",       slot: "returnId",        bodyStyle: "font-size: 14px", sortable: true },  // ← NEW
    { header: "Buyer", slot: "buyer", bodyStyle: "font-size: 14px", sortable: true },
    { header: "Date", slot: "date", bodyStyle: "font-size: 14px", sortable: true },
    { header: "Sent Serial", slot: "sentSerial", bodyStyle: "font-size: 14px" },
    { header: "", slot: "arrow", style: { width: "3rem", minWidth: "3rem" } },
    { header: "Received Serial", slot: "receivedSerial", bodyStyle: "font-size: 14px" },
];

export default {
    mixins: [switcheruMixin],
    components: { XDataTable, Button, Dialog, Divider, TitlePage, AnimateDiv, ViewImageGalleryModal, Paginator },
    data() {
        return { columns: TABLE_COLUMNS };
    },
};
</script>

<style scoped>
/* ========== SERIAL CELLS ========== */
.serial-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.serial-thumb-row {
    position: relative;
    flex-shrink: 0;
}

.serial-thumb {
    width: 48px;
    height: 48px;
    object-fit: cover;
    border-radius: 6px;
    border: 2px solid #e0e0e0;
    transition: transform 0.2s, border-color 0.2s;
}

.serial-sent .serial-thumb {
    border-color: #4caf50;
}

.serial-received .serial-thumb {
    border-color: #f44336;
}

.serial-thumb:hover {
    transform: scale(1.1);
}

.thumb-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #2196f3;
    color: white;
    border-radius: 50%;
    min-width: 20px;
    height: 20px;
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    font-weight: 600;
}

.serial-text {
    font-family: monospace;
    font-size: 13px;
    color: #333;
    word-break: break-all;
}

.serial-mono {
    font-family: monospace;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.sent-text {
    color: #2e7d32;
}

.received-text {
    color: #c62828;
}

/* ========== ARROW ========== */
.arrow-cell {
    display: flex;
    align-items: center;
    justify-content: center;
}

.switch-arrow {
    font-size: 18px;
    color: #ff9800;
    animation: pulse-arrow 2s ease-in-out infinite;
}

@keyframes pulse-arrow {
    0%, 100% { opacity: 0.6; transform: scale(1); }
    50% { opacity: 1; transform: scale(1.15); }
}

.product-title-cell {
    font-size: 13px;
    color: #555;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* ========== DETAILS MODAL ========== */
.details-container {
    display: flex;
    flex-direction: column;
    gap: 14px;
    font-size: 14px;
}

.item-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #d5d5d5;
    padding: 6px 0;
}

/* ========== COMPARE MODAL ========== */
.compare-dialog {
    max-width: 950px;
}

.compare-container {
    display: flex;
    gap: 20px;
    align-items: flex-start;
}

.compare-side {
    flex: 1;
    background: #f8f9fa;
    border-radius: 12px;
    padding: 16px;
    text-align: center;
}

.compare-side-header {
    font-weight: 700;
    font-size: 15px;
    padding: 8px 16px;
    border-radius: 8px;
    margin-bottom: 12px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.sent-header {
    background: #e8f5e9;
    color: #2e7d32;
}

.received-header {
    background: #ffebee;
    color: #c62828;
}

.compare-serial-value {
    font-family: monospace;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 14px;
    color: #333;
    word-break: break-all;
}

.compare-images-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
    gap: 8px;
}

.compare-img {
    width: 100%;
    aspect-ratio: 1;
    object-fit: cover;
    border-radius: 6px;
    cursor: pointer;
    transition: transform 0.2s;
    border: 1px solid #ddd;
}

.compare-img:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.compare-arrow {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px 0;
    font-size: 28px;
    color: #ff9800;
    flex-shrink: 0;
}

.compare-footer {
    margin-top: 20px;
    padding-top: 14px;
    border-top: 1px solid #e0e0e0;
}

.compare-meta {
    display: flex;
    gap: 24px;
    font-size: 13px;
    color: #666;
    flex-wrap: wrap;
}

/* ========== MOBILE ========== */
.mobile-view {
    display: none;
}

.mobile-cards {
    padding: 0 15px;
}

.mobile-card {
    background: white;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    animation: fadeIn 0.3s ease;
}

.mobile-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.mobile-card-header {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 10px;
}

.mobile-switcheru-badge {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: linear-gradient(135deg, #ff9800 0%, #f44336 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.mobile-return-info {
    flex: 1;
}

.mobile-return-title {
    margin: 0 0 4px 0;
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.mobile-return-date {
    font-size: 12px;
    color: #666;
}

/* Side-by-side serial comparison on mobile */
.mobile-serial-compare {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 0;
}

.mobile-serial-side {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    padding: 8px;
    border-radius: 8px;
    transition: background 0.2s;
}

.mobile-serial-side:hover {
    background: #f5f5f5;
}

.mobile-serial-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 2px 10px;
    border-radius: 10px;
}

.sent-label {
    background: #e8f5e9;
    color: #2e7d32;
}

.received-label {
    background: #ffebee;
    color: #c62828;
}

.mobile-serial-img {
    width: 64px;
    height: 64px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid #e0e0e0;
}

.mobile-serial-value {
    font-family: monospace;
    font-size: 11px;
    color: #333;
    text-align: center;
    word-break: break-all;
}

.mobile-serial-arrow {
    color: #ff9800;
    font-size: 16px;
    flex-shrink: 0;
}

.mobile-card-details {
    font-size: 14px;
}

.mobile-detail-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    border-bottom: 1px solid #f0f0f0;
}

.mobile-detail-row:last-child {
    border-bottom: none;
}

.mobile-detail-value {
    font-weight: 500;
    text-align: right;
}

.mobile-card-actions {
    margin-top: 10px;
    display: flex;
    gap: 10px;
}

.loading-spinner-mobile {
    text-align: center;
    padding: 40px;
    color: #666;
}

.loading-spinner-mobile i {
    font-size: 32px;
    margin-bottom: 10px;
    display: block;
}

.no-data-mobile {
    text-align: center;
    padding: 40px;
    color: #999;
    font-size: 14px;
}

.fw-semibold {
    font-weight: 600;
}

/* ========== DESKTOP VIEW ========== */
.desktop-view {
    display: block;
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
    .desktop-view {
        display: none;
    }

    .mobile-view {
        display: block;
    }

    .compare-container {
        flex-direction: column;
    }

    .compare-arrow {
        transform: rotate(90deg);
        padding: 10px 0;
    }

    .compare-dialog,
    .view-details-dialog {
        width: 95% !important;
        max-width: 95% !important;
    }
}

@media (min-width: 768px) {
    .view-details-dialog {
        width: 50% !important;
    }
}

/* ========== ANIMATION ========== */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>