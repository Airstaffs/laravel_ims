<template>
    <div class="vue-container testing-module">
        <TitlePage
            title="Testing Module"
            subtitle="Manage and log quality assurance and functional testing results for products prior to inventory staging."
        />

        <!-- Desktop Table Container -->
        <AnimateDiv :delay="200" class="px-4">
            <XDataTable
                :value="sortedInventory"
                :loading="loading"
                :columns="visibleColumns"
                :paginator="false"
                tableClass="desktop-view"
                selectionMode="multiple"
                dataKey="ProductID"
            >
                <template #gallery="{ data }">
                    <div
                        class="d-flex justify-content-center align-items-center"
                    >
                        <!-- Use custom image display for captured images -->
                        <div
                            v-if="
                                data.capturedImages &&
                                data.capturedImages.capturedimg1
                            "
                            class="gallery-thumbnail position-relative"
                            @click="openImageModal(data)"
                            style="cursor: pointer"
                        >
                            <img
                                :src="`/images/product_images/${
                                    data.company || 'Airstaffs'
                                }/${data.capturedImages.capturedimg1}`"
                                :alt="getDisplayTitle(data)"
                                style="
                                    width: 50px;
                                    height: 50px;
                                    object-fit: cover;
                                    border-radius: 4px;
                                "
                                @error="handleImageError"
                            />
                            <span
                                v-if="countCapturedImages(data) > 1"
                                class="position-absolute bg-primary text-white rounded-circle"
                                style="
                                    top: -5px;
                                    right: -5px;
                                    min-width: 20px;
                                    height: 20px;
                                    font-size: 0.65rem;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    padding: 0 4px;
                                "
                            >
                                +{{ countCapturedImages(data) - 1 }}
                            </span>
                        </div>

                        <!-- Use regular product images as fallback -->
                        <div
                            v-else-if="data.img1 && data.img1 !== 'NULL'"
                            class="gallery-thumbnail position-relative"
                            @click="openImageModal(data)"
                            style="cursor: pointer"
                        >
                            <img
                                :src="`/images/thumbnails/${data.img1}`"
                                :alt="getDisplayTitle(data)"
                                style="
                                    width: 50px;
                                    height: 50px;
                                    object-fit: cover;
                                    border-radius: 4px;
                                "
                                @error="handleImageError"
                            />
                            <span
                                v-if="countAllImages(data) > 1"
                                class="position-absolute bg-primary text-white rounded-circle"
                                style="
                                    top: -5px;
                                    right: -5px;
                                    min-width: 20px;
                                    height: 20px;
                                    font-size: 0.65rem;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    padding: 0 4px;
                                "
                            >
                                +{{ countAllImages(data) - 1 }}
                            </span>
                        </div>

                        <!-- Fallback icon if no images -->
                        <div
                            v-else
                            class="d-flex justify-content-center align-items-center"
                            style="
                                width: 50px;
                                height: 50px;
                                background-color: #f0f0f0;
                                border-radius: 4px;
                            "
                        >
                            <i
                                class="pi pi-image"
                                style="font-size: 1.5rem; color: #999"
                            ></i>
                        </div>
                    </div>
                </template>

                <template #ProductTitle="{ data }">
                    <div class="d-flex align-items-start gap-4">
                        <div
                            style="
                                word-break: break-word;
                                white-space: normal;
                                overflow-wrap: break-word;
                                flex: 1;
                            "
                        >
                            <p style="font-size: 0.8rem">
                                RT# {{ data.rtcounter }}
                            </p>
                            <p class="fw-semibold">
                                {{ getDisplayTitle(data) }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #datedelivered="{ data }">
                    {{ convertToLocalDate(data.datedelivered) }}
                </template>

                <template #actions="{ data }">
                    <div class="d-flex flex-column align-items-start">
                        <Button
                            size="small"
                            severity="success"
                            variant="text"
                            label="Condition"
                            icon="pi pi-check-square"
                            @click="openConditionModal(data)"
                            class="text-success"
                        />
                        <Button
                            size="small"
                            severity="contrast"
                            variant="text"
                            label="View Details"
                            class="text-primary"
                            icon="pi pi-exclamation-circle"
                            @click="openEditModal(data)"
                        />
                        <Button
                            size="small"
                            severity="contrast"
                            variant="text"
                            label="Testing - Work Log"
                            icon="pi pi-clipboard"
                            class="text-warning"
                            @click="openTestingWorkLog(data)"
                        />
                    </div>
                </template>
            </XDataTable>
        </AnimateDiv>

        <!-- Mobile Cards View -->
        <div class="mobile-view">
            <MobileCard1
                :sortedInventory="sortedInventory"
                :expandedRows="expandedRows"
                :openImageModal="openImageModal"
                :handleImageError="handleImageError"
                :countAdditionalImages="countAdditionalImages"
                :openEditModal="openEditModal"
                :loading="loading"
                :showDetails="showDetails"
                :visibleFields="[
                    'price',
                    'serialnumber',
                    'trackingnumber',
                    'datedelivered',
                ]"
            />
        </div>

        <!-- Pagination -->
        <Paginator
            :first="first"
            :rows="perPage"
            :total-records="totalRecords"
            :rows-per-page-options="[10, 20, 50]"
            template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords}"
            class="small-paginator"
            @page="onPageChange"
        />

        <!-- Image Modal with Tabs -->
        <ViewImageGalleryModal
            :showImageModal="showImageModal"
            :closeImageModal="closeImageModal"
            :ProductTitle="ProductTitle"
            :regularImages="regularImages"
            :capturedImages="capturedImages"
            :handleImageError="handleImageError"
        />

        <!-- Condition Checklist Modal -->
        <ReceivedConditionModal
            v-model:visible="showConditionModal"
            :item="selectedItem"
            @saved="handleConditionSaved"
        />

        <!-- Move to Cleaning Confirmation Dialog -->
        <Dialog
            v-model:visible="showMoveConfirmation"
            modal
            header="Move to Cleaning & Prepping?"
            style="width: 35rem"
            :pt="{ root: { class: 'mobile-fullscreen-dialog' } }"
        >
            <div class="confirmation-content">
                <i
                    class="pi pi-arrow-right-arrow-left"
                    style="
                        font-size: 3rem;
                        color: var(--primary-color);
                        display: block;
                        text-align: center;
                        margin-bottom: 1rem;
                    "
                ></i>
                <p class="text-center mb-3">
                    <strong>{{ moveItemDetails?.ProductTitle }}</strong>
                </p>
                <p class="text-center">
                    Testing complete! Would you like to move this item to
                    <strong>Cleaning & Prepping</strong> module?
                </p>
                <div class="mt-3 p-3 bg-light rounded">
                    <small class="text-muted">
                        <i class="pi pi-info-circle"></i>
                        This will update the item location from
                        <strong>Testing</strong> to <strong>Cleaning</strong>
                    </small>
                </div>
            </div>
            <template #footer>
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    @click="cancelMove"
                    severity="secondary"
                />
                <Button
                    label="Move to Cleaning"
                    icon="pi pi-arrow-right"
                    @click="confirmMoveToCleaning"
                    :loading="movingItem"
                    severity="success"
                />
            </template>
        </Dialog>

        <!-- View Details Modal -->
        <Dialog
            v-model:visible="showEditModal"
            class="view-modal"
            modal
            :header="`RT # ${item.rtcounter} - ${getDisplayTitle(item)}`"
            style="width: 110rem"
            :pt="{
                root: { class: 'mobile-fullscreen-dialog' },
            }"
        >
            <div>
                <div class="view-info-container">
                    <div class="view-grid-wrapper">
                        <!-- LEFT: IMAGE -->
                        <div class="form-col-left">
                            <gallery :item="item" />
                            <Card>
                                <template #title>
                                    <h5 class="text-primary fw-bolder">
                                        Description
                                    </h5>
                                </template>
                                <template #content>
                                    <p
                                        style="
                                            word-break: break-all;
                                            max-height: 450px;
                                            overflow-y: auto;
                                            font-size: 14px;
                                        "
                                    >
                                        {{ item.description }}
                                    </p>
                                </template>
                            </Card>
                        </div>
                        <!-- RIGHT: DETAILS -->
                        <div class="form-col-right">
                            <div class="row">
                                <!-- Left Column -->
                                <div class="col-md-6">
                                    <!-- Warehouse & Tracking -->
                                    <section class="info-section">
                                        <h3 class="text-primary fw-bolder">
                                            Warehouse & Tracking
                                        </h3>
                                        <dl class="info-list">
                                            <div class="info-item">
                                                <dt>Module:</dt>
                                                <dd>
                                                    {{ item.ProductModuleLoc }}
                                                </dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Warehouse Location:</dt>
                                                <dd>
                                                    {{ item.warehouselocation }}
                                                </dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Serial Number:</dt>
                                                <dd>{{ item.serialnumber }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Tracking Number:</dt>
                                                <dd>
                                                    {{ item.trackingnumber }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </section>
                                    <!-- Product Identifiers -->
                                    <section class="info-section">
                                        <h3 class="text-primary fw-bolder">
                                            Product Identifiers
                                        </h3>
                                        <dl class="info-list">
                                            <div class="info-item">
                                                <dt>RT:</dt>
                                                <dd>{{ item.ProductID }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>ASIN:</dt>
                                                <dd>{{ item.ASIN }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>RPN:</dt>
                                                <dd>{{ item.RPN }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>PRD:</dt>
                                                <dd>{{ item.PRD }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>UPC:</dt>
                                                <dd>{{ item.UPC }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>EAN:</dt>
                                                <dd>{{ item.EAN }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>FNSKU:</dt>
                                                <dd>{{ item.FNSKU }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>SKU:</dt>
                                                <dd>{{ item.SKU }}</dd>
                                            </div>
                                        </dl>
                                    </section>

                                    <!-- Order Information -->
                                    <section class="info-section">
                                        <h3 class="text-primary fw-bolder">
                                            Order Information
                                        </h3>
                                        <dl class="info-list">
                                            <div class="info-item">
                                                <dt>Order Number:</dt>
                                                <dd>{{ item.rtid }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Item Number:</dt>
                                                <dd>{{ item.itemnumber }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Basket Number:</dt>
                                                <dd>{{ item.basketnumber }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Order Date:</dt>
                                                <dd>{{ localOrderDate }}</dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Delivered Date:</dt>
                                                <dd>
                                                    {{ localDeliveredDate }}
                                                </dd>
                                            </div>
                                            <div class="info-item">
                                                <dt>Seller:</dt>
                                                <dd>{{ item.seller }}</dd>
                                            </div>
                                        </dl>
                                    </section>

                                    <!-- Additional Info -->
                                    <section
                                        class="info-section"
                                        v-if="item.grading || item.notes"
                                    >
                                        <h3 class="text-primary fw-bolder">
                                            Additional Info
                                        </h3>
                                        <dl class="info-list">
                                            <div
                                                class="info-item"
                                                v-if="item.grading"
                                            >
                                                <dt>Grading:</dt>
                                                <dd>{{ item.grading }}</dd>
                                            </div>
                                            <div
                                                class="info-item"
                                                v-if="item.notes"
                                            >
                                                <dt>Notes:</dt>
                                                <dd>{{ item.notes }}</dd>
                                            </div>
                                        </dl>
                                    </section>
                                </div>

                                <!-- Right Column: Pricing -->
                                <div
                                    class="col-md-6"
                                    v-show="showPricingSection"
                                >
                                    <section class="pricing-section">
                                        <h3 class="text-primary fw-bolder">
                                            Pricing
                                        </h3>
                                        <dl class="pricing-list">
                                            <div class="pricing-item">
                                                <dt>Unit Price:</dt>
                                                <dd>
                                                    {{
                                                        item.formattedUnitprice ||
                                                        "0.00"
                                                    }}
                                                </dd>
                                            </div>
                                            <div class="pricing-item">
                                                <dt>Quantity:</dt>
                                                <dd>
                                                    {{ item.quantity || 0 }}
                                                </dd>
                                            </div>
                                            <div
                                                class="pricing-item subtotal-line"
                                            >
                                                <dt>Subtotal:</dt>
                                                <dd>
                                                    {{ item.price || "0.00" }}
                                                </dd>
                                            </div>
                                            <div
                                                class="pricing-item"
                                                v-if="item.Discount"
                                            >
                                                <dt>Discount:</dt>
                                                <dd class="discount">
                                                    -{{ item.Discount }}
                                                </dd>
                                            </div>
                                            <div class="pricing-item">
                                                <dt>Tax:</dt>
                                                <dd>{{ item.tax }}</dd>
                                            </div>
                                            <div class="pricing-item">
                                                <dt>Shipping:</dt>
                                                <dd>
                                                    {{ item.priceshipping }}
                                                </dd>
                                            </div>
                                            <div
                                                class="pricing-item total-line"
                                            >
                                                <dt>Total Price:</dt>
                                                <dd class="total-amount">
                                                    {{ grandTotal }}
                                                </dd>
                                            </div>
                                            <div
                                                class="pricing-item refund-line"
                                                v-if="item.refund"
                                            >
                                                <dt>Refund:</dt>
                                                <dd class="refund">
                                                    {{ item.refund }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </section>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Dialog>

        <Dialog
            v-model:visible="showTestingWorkLog"
            modal
            :header="`Testing Work Log — ${testingWorkLogItem?.rtcounter || ''}`"
            :style="{ width: '760px', maxWidth: '98vw' }"
        >
            <div v-if="testingWorkLogItem" class="twl-wrapper">
                <!-- QUICK ACTION: Test Result Decision -->
                <div class="twl-quick-action-section">
                    <div class="twl-quick-action-header">
                        <span class="twl-quick-action-badge">QUICK ACTION</span>
                        <span class="twl-quick-action-title"
                            >Test Result Decision</span
                        >
                    </div>
                    <div class="twl-decision-grid">
                        <!-- PASS card -->
                        <div
                            class="twl-decision-card twl-decision-card--pass"
                            :class="{
                                'twl-decision-card--selected':
                                    testResult === 'pass',
                            }"
                            @click="selectTestResult('pass')"
                        >
                            <div
                                class="twl-decision-icon twl-decision-icon--pass"
                            >
                                ✓
                            </div>
                            <div
                                class="twl-decision-label twl-decision-label--pass"
                            >
                                PASS
                            </div>
                            <div class="twl-decision-sub">All tests OK</div>
                            <div class="twl-decision-info">
                                <p class="twl-decision-info-title">
                                    Auto-outputs:
                                </p>
                                <p>✓ All default logs = "OK/Good"</p>
                                <p>✓ Status = "Working"</p>
                                <p>→ Next: <strong>Cleaning Module</strong></p>
                            </div>
                        </div>

                        <!-- FAIL card -->
                        <div
                            class="twl-decision-card twl-decision-card--fail"
                            :class="{
                                'twl-decision-card--selected':
                                    testResult === 'fail',
                            }"
                            @click="selectTestResult('fail')"
                        >
                            <div
                                class="twl-decision-icon twl-decision-icon--fail"
                            >
                                ✕
                            </div>
                            <div
                                class="twl-decision-label twl-decision-label--fail"
                            >
                                FAIL
                            </div>
                            <div class="twl-decision-sub">Issues detected</div>
                            <div
                                class="twl-decision-info twl-decision-info--fail"
                            >
                                <p
                                    class="twl-decision-info-title twl-decision-info-title--fail"
                                >
                                    Required actions:
                                </p>
                                <p>✓ Select failed test items</p>
                                <p>✓ Pre-typed notes auto-fill</p>
                                <p>→ Next: <strong>Repair Module</strong></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AUTO-FETCH: System Pre-filled Fields -->
                <div class="twl-autofetch-section">
                    <div class="twl-autofetch-header">
                        <span class="twl-autofetch-badge">AUTO-FETCH</span>
                        <span class="twl-autofetch-title"
                            >System Pre-filled Fields</span
                        >
                    </div>
                    <div class="twl-autofetch-grid">
                        <div class="twl-autofetch-card">
                            <span class="twl-autofetch-label">Date Tested</span>
                            <span class="twl-autofetch-value">{{
                                currentDateTime
                            }}</span>
                        </div>
                        <div class="twl-autofetch-card">
                            <span class="twl-autofetch-label"
                                >Serial Number</span
                            >
                            <span class="twl-autofetch-value">{{
                                testingWorkLogItem.serialnumber || "—"
                            }}</span>
                        </div>
                        <div class="twl-autofetch-card">
                            <span class="twl-autofetch-label"
                                >Tester / Received By</span
                            >
                            <span class="twl-autofetch-value">{{
                                testingWorkLogItem.received_by ||
                                testingWorkLogItem.Username ||
                                currentUser ||
                                "—"
                            }}</span>
                        </div>
                    </div>
                </div>

                <!-- No fields configured -->
                <div v-if="!testingWorkLogFields.length" class="twl-empty">
                    <i class="pi pi-info-circle"></i>
                    <span
                        >No testing fields configured for this ASIN. Set them up
                        in <strong>ASIN Configuration</strong>.</span
                    >
                </div>

                <!-- Field groups -->
                <div v-else class="twl-fields">
                    <div
                        v-for="(field, i) in testingWorkLogFields"
                        :key="'twl-' + i"
                        class="twl-field-card"
                    >
                        <!-- Field label + badges -->
                        <div class="twl-field-label">
                            <span>{{ field.label }}</span>
                            <span
                                v-if="field.required"
                                class="twl-badge twl-badge--required"
                                >Required</span
                            >
                            <span
                                v-if="field._fromGlobal"
                                class="twl-badge twl-badge--global"
                                >Global</span
                            >
                        </div>

                        <!-- Dropdown/Select -->
                        <select
                            v-if="
                                field.type === 'Dropdown/Select' &&
                                field.hasOptions &&
                                field.options?.length
                            "
                            v-model="testingWorkLogValues[field.label]"
                            class="twl-select"
                        >
                            <option value="" disabled>
                                Select {{ field.label }}
                            </option>
                            <option
                                v-for="opt in field.options"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.value }}
                            </option>
                        </select>

                        <!-- Checkbox -->
                        <div
                            v-else-if="field.type === 'Checkbox'"
                            class="twl-checkbox-wrap"
                        >
                            <input
                                type="checkbox"
                                :id="`twl-cb-${i}`"
                                v-model="testingWorkLogValues[field.label]"
                                class="twl-checkbox"
                            />
                            <label :for="`twl-cb-${i}`">{{
                                field.label
                            }}</label>
                        </div>

                        <!-- Textarea -->
                        <textarea
                            v-else-if="field.type === 'Textarea'"
                            v-model="testingWorkLogValues[field.label]"
                            :placeholder="
                                field.defaultValue || `Enter ${field.label}`
                            "
                            rows="3"
                            class="twl-textarea"
                        />

                        <!-- Number -->
                        <input
                            v-else-if="field.type === 'Number'"
                            type="number"
                            v-model="testingWorkLogValues[field.label]"
                            :placeholder="field.defaultValue || '0'"
                            class="twl-input"
                        />

                        <!-- Date -->
                        <input
                            v-else-if="field.type === 'Date'"
                            type="date"
                            v-model="testingWorkLogValues[field.label]"
                            class="twl-input"
                        />

                        <!-- Text / fallback -->
                        <input
                            v-else
                            type="text"
                            v-model="testingWorkLogValues[field.label]"
                            :placeholder="
                                field.defaultValue || `Enter ${field.label}`
                            "
                            class="twl-input"
                        />

                        <!-- Pre-typed note hint -->
                        <div
                            v-if="
                                field.preTypedNotes &&
                                getPreTypedNote(
                                    field,
                                    testingWorkLogValues[field.label],
                                )
                            "
                            class="twl-note-hint"
                        >
                            <i
                                class="pi pi-comment"
                                style="font-size: 11px"
                            ></i>
                            {{
                                getPreTypedNote(
                                    field,
                                    testingWorkLogValues[field.label],
                                )
                            }}
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button
                    label="Cancel"
                    severity="secondary"
                    text
                    @click="showTestingWorkLog = false"
                />
                <Button
                    label="Save Work Log"
                    icon="pi pi-save"
                    :disabled="!testingWorkLogFields.length"
                    @click="saveTestingWorkLog"
                />
            </template>
        </Dialog>

        <ScrollTop />
    </div>
</template>

<script>
import { Button, Dialog, Card, ScrollTop, Select, Paginator } from "primevue";
import Testing from "./testing.js";
import gallery from "../../components/Gallery/gallery.vue";
import TableGallery from "../../components/Gallery/tableGallery.vue";
import XDataTable from "../../components/DataTable/XDataTable.vue";
import MobileCard1 from "../../components/MobileCard1/MobileCard1.vue";
import TitlePage from "../../components/TitlePage/TitlePage.vue";
import ViewImageGalleryModal from "../../components/ViewImageGalleryModal/ViewImageGalleryModal.vue";
import AnimateDiv from "../../components/AnimationDiv/AnimateDiv.vue";
import ReceivedConditionModal from "./modals/receivedCondtion_modal.vue";
import { ROWS_PER_PAGE } from "../../constant.js";
import axios from "axios";
import { showPricingForPH } from "../../utils/helpers.js";

const API_BASE_URL = import.meta.env.VITE_API_URL;

const TABLE_COLUMNS = [
    {
        field: "gallery",
        header: "Gallery",
        slot: "gallery",
        style: { width: "4rem", minWidth: "4rem" },
    },
    {
        field: "AStitle",
        header: "Product Name",
        sortable: true,
        headerStyle: "font-size: 16px;",
        slot: "ProductTitle",
        style: { maxWidth: "20rem" },
    },
    {
        field: "price",
        header: "Price",
        sortable: true,
        bodyStyle: "font-size: 14px;",
    },
    {
        field: "serialNumber",
        header: "Serial Number",
        sortable: true,
        bodyStyle: "font-size: 14px;",
    },
    {
        field: "trackingnumber",
        header: "Tracking Number",
        sortable: true,
        bodyStyle: "font-size: 14px;",
    },
    {
        field: "datedelivered",
        header: "Date Delivered",
        sortable: true,
        bodyStyle: "font-size: 14px;",
    },
];

export default {
    mixins: [Testing],
    components: {
        Button,
        Dialog,
        Card,
        gallery,
        TableGallery,
        XDataTable,
        MobileCard1,
        ScrollTop,
        TitlePage,
        ViewImageGalleryModal,
        AnimateDiv,
        Select,
        ReceivedConditionModal,
        Paginator,
    },
    data() {
        return {
            columns: TABLE_COLUMNS,
            rowsPerPage: ROWS_PER_PAGE,
            showConditionModal: false,
            selectedItem: null,
            showMoveConfirmation: false,
            moveItemDetails: null,
            movingItem: false,
            currentTimezone: "UTC",
            timezoneLabel: "Loading...",
            showPricingSection: showPricingForPH(),

            showTestingWorkLog: false,
            testingWorkLogItem: null,
            testingWorkLogFields: [],
            testingWorkLogValues: {},
            currentUser: "",
            testResult: null,
        };
    },
    async mounted() {
        await this.loadUserTimezone();
        window.addEventListener("resize", this.updatePricingView);

        try {
            const res = await axios.get("/api/auth/user");
            this.currentUser = res.data?.name || res.data?.email || "";
        } catch {
            this.currentUser = "";
        }
    },
    computed: {
        visibleColumns() {
            if (!this.columns) return [];

            const detailFields = [
                "FBMAvailable",
                "FbaAvailable",
                "Outbound",
                "Inbound",
                "Reserved",
                "Unfulfillable",
            ];
            const mandatoryFields = ["gallery", "ProductTitle"];

            return this.columns.filter((col) => {
                if (mandatoryFields.includes(col.field)) {
                    return true;
                }

                if (!this.showDetails && detailFields.includes(col.field)) {
                    return false;
                }

                return true;
            });
        },

        // ✅ ADD THESE COMPUTED PROPERTIES FOR DATE CONVERSION
        localOrderDate() {
            return this.convertToLocalDate(this.item.orderdate);
        },
        localDeliveredDate: {
            get() {
                return this.convertToLocalDate(this.item.datedelivered);
            },
            set(value) {
                this.item.datedelivered = this.convertFromLocalDate(value);
            },
        },

        currentDateTime() {
            if (!this.testingWorkLogOpenedAt) return "—";
            return this.testingWorkLogOpenedAt.toLocaleString("en-US", {
                year: "numeric",
                month: "2-digit",
                day: "2-digit",
                hour: "2-digit",
                minute: "2-digit",
                hour12: true,
            });
        },
    },
    methods: {
        openConditionModal(item) {
            this.selectedItem = item;
            this.showConditionModal = true;
        },

        async handleConditionSaved(conditionData) {
            console.log("Condition saved:", conditionData);

            // Show success notification
            if (typeof this.$swal !== "undefined") {
                await this.$swal.fire({
                    icon: "success",
                    title: "Success!",
                    text: "Received condition saved successfully",
                    timer: 2000,
                    showConfirmButton: false,
                });
            } else if (typeof Swal !== "undefined") {
                await Swal.fire({
                    icon: "success",
                    title: "Success!",
                    text: "Received condition saved successfully",
                    timer: 2000,
                    showConfirmButton: false,
                });
            }

            // Store item details for move confirmation
            this.moveItemDetails = this.selectedItem;

            // Show move to cleaning confirmation
            this.showMoveConfirmation = true;
        },

        async confirmMoveToCleaning() {
            if (!this.moveItemDetails) return;

            this.movingItem = true;
            try {
                const dataToSend = {
                    item_number: this.moveItemDetails.itemnumber,
                    product_id: String(this.moveItemDetails.ProductID),
                };

                console.log("Moving to cleaning:", dataToSend);

                const response = await axios.post(
                    `${API_BASE_URL}/api/testing/move-to-cleaning`,
                    dataToSend,
                );

                if (response.data.success) {
                    // Success notification
                    if (typeof this.$swal !== "undefined") {
                        this.$swal.fire({
                            icon: "success",
                            title: "Moved!",
                            text: "Item moved to Cleaning & Prepping module successfully",
                            timer: 2000,
                            showConfirmButton: false,
                        });
                    } else if (typeof Swal !== "undefined") {
                        Swal.fire({
                            icon: "success",
                            title: "Moved!",
                            text: "Item moved to Cleaning & Prepping module successfully",
                            timer: 2000,
                            showConfirmButton: false,
                        });
                    } else {
                        alert(
                            "Success! Item moved to Cleaning & Prepping module",
                        );
                    }

                    // Close modal and refresh
                    this.showMoveConfirmation = false;
                    this.moveItemDetails = null;

                    // Refresh inventory to remove the moved item
                    await this.fetchInventory();
                }
            } catch (error) {
                console.error("Failed to move item:", error);
                console.error("Error response data:", error.response?.data);
                console.error(
                    "Validation errors:",
                    error.response?.data?.errors,
                );

                let errorMessage = "Failed to move item to Cleaning module";

                // Handle validation errors
                if (error.response?.data?.errors) {
                    const errors = error.response.data.errors;
                    errorMessage = Object.values(errors).flat().join("\n");
                } else if (error.response?.data?.message) {
                    errorMessage = error.response.data.message;
                }

                if (typeof this.$swal !== "undefined") {
                    this.$swal.fire({
                        icon: "error",
                        title: "Error Moving Item",
                        text: errorMessage,
                        confirmButtonText: "OK",
                    });
                } else if (typeof Swal !== "undefined") {
                    Swal.fire({
                        icon: "error",
                        title: "Error Moving Item",
                        text: errorMessage,
                        confirmButtonText: "OK",
                    });
                } else {
                    alert("Error: " + errorMessage);
                }
            } finally {
                this.movingItem = false;
            }
        },

        cancelMove() {
            this.showMoveConfirmation = false;
            this.moveItemDetails = null;

            // Still refresh inventory to show updated condition
            this.fetchInventory();
        },

        transformDataForGallery(data) {
            if (!data) {
                return {};
            }

            // If captured images exist, use them with full path
            if (data.capturedImages && data.capturedImages.capturedimg1) {
                const transformedData = { ...data };

                // Map capturedimg1-12 to img1-12 with full path
                for (let i = 1; i <= 12; i++) {
                    const capturedImg = data.capturedImages[`capturedimg${i}`];
                    if (capturedImg) {
                        // Add full path: /images/product_images/Airstaffs/
                        transformedData[`img${i}`] =
                            `/images/product_images/Airstaffs/${capturedImg}`;
                    } else {
                        transformedData[`img${i}`] = null;
                    }
                }

                // Clear img13-15 since captured images only go up to 12
                for (let i = 13; i <= 15; i++) {
                    transformedData[`img${i}`] = null;
                }

                return transformedData;
            }

            // Return original data if no captured images exist (fallback to product images)
            return data;
        },

        countAllImages(data) {
            // Safety check
            if (!data) {
                return 0;
            }

            // If captured images exist, count them
            if (data.capturedImages) {
                let count = 0;
                for (let i = 1; i <= 12; i++) {
                    if (data.capturedImages[`capturedimg${i}`]) {
                        count++;
                    }
                }
                // Return count if captured images exist
                if (count > 0) {
                    return count;
                }
            }

            // Otherwise count product images (fallback)
            let count = 0;
            for (let i = 1; i <= 15; i++) {
                if (data[`img${i}`]) {
                    count++;
                }
            }
            return count;
        },

        convertToLocalDate(dateString) {
            if (!dateString) return "";

            try {
                const userTimezone = this.currentTimezone;
                const isLATimezone =
                    userTimezone === "America/Los_Angeles" ||
                    userTimezone === "America/Pacific" ||
                    !userTimezone;

                // DB stores time in LA timezone — if user is already in LA, just extract date directly
                if (isLATimezone) {
                    return dateString.split(" ")[0].split("T")[0];
                }

                // User is in a different timezone — convert LA time to user's local timezone
                const isRawFormat =
                    !dateString.includes("T") &&
                    !dateString.includes("Z") &&
                    !dateString.includes("+");

                let date;
                if (isRawFormat) {
                    const isoLike = dateString.replace(" ", "T");
                    const tempDate = new Date(isoLike);
                    const laWallClock = new Date(
                        new Date(isoLike).toLocaleString("en-US", {
                            timeZone: "America/Los_Angeles",
                        }),
                    );
                    const diff = tempDate - laWallClock;
                    date = new Date(tempDate.getTime() + diff);
                } else {
                    date = new Date(dateString);
                }

                const formatter = new Intl.DateTimeFormat("en-CA", {
                    timeZone: userTimezone,
                    year: "numeric",
                    month: "2-digit",
                    day: "2-digit",
                });

                return formatter.format(date);
            } catch (error) {
                return dateString;
            }
        },

        convertFromLocalDate(localDateString) {
            if (!localDateString) return null;

            try {
                // The input gives us YYYY-MM-DD in user's timezone
                // We need to convert it to a proper datetime for storage

                // Create a date object at noon in the user's timezone to avoid day boundary issues
                const [year, month, day] = localDateString.split("-");
                const dateInUserTz = new Date(
                    `${year}-${month}-${day}T12:00:00`,
                );

                // Format for database storage (ISO format)
                return dateInUserTz.toISOString().split("T")[0]; // Returns YYYY-MM-DD
            } catch (error) {
                console.error("Error converting from local date:", error);
                return localDateString;
            }
        },

        async loadUserTimezone() {
            try {
                const response = await axios.get("/api/timezone/current");

                if (response.data.success && response.data.usertimezone) {
                    this.currentTimezone = response.data.usertimezone;

                    // Format timezone for display
                    const timezoneParts = this.currentTimezone.split("/");
                    const location = timezoneParts[
                        timezoneParts.length - 1
                    ].replace("_", " ");

                    // ✅ FIXED: Calculate GMT offset for the SELECTED timezone, not browser's
                    const date = new Date();

                    // Get the date in UTC
                    const utcDate = new Date(
                        date.toLocaleString("en-US", { timeZone: "UTC" }),
                    );

                    // Get the date in user's selected timezone
                    const userTzDate = new Date(
                        date.toLocaleString("en-US", {
                            timeZone: this.currentTimezone,
                        }),
                    );

                    // Calculate offset in hours
                    const offsetMs = userTzDate - utcDate;
                    const offsetHours = Math.round(offsetMs / (1000 * 60 * 60));
                    const offsetSign = offsetHours >= 0 ? "+" : "-";
                    const gmtOffset = `GMT${offsetSign}${Math.abs(
                        offsetHours,
                    )}`;

                    this.timezoneLabel = `(${gmtOffset})`;
                } else {
                    // Fallback to browser timezone
                    const browserTz =
                        Intl.DateTimeFormat().resolvedOptions().timeZone;
                    this.currentTimezone = browserTz;
                    const location = browserTz
                        .split("/")
                        .pop()
                        .replace("_", " ");
                    this.timezoneLabel = location;
                }

                console.log("📍 Timezone loaded:", this.timezoneLabel);
            } catch (error) {
                console.error("Error loading timezone:", error);
                this.currentTimezone = "UTC";
                this.timezoneLabel = "UTC";
            }
        },

        updatePricingView() {
            this.showPricingSection = showPricingForPH();
        },

        // Select PASS or FAIL — auto-fills field values on PASS
        selectTestResult(result) {
            this.testResult = result;

            if (result === "pass") {
                // Auto-fill all fields with their defaultValue or first option
                this.testingWorkLogFields.forEach((f) => {
                    if (
                        f.type === "Dropdown/Select" &&
                        f.hasOptions &&
                        f.options?.length
                    ) {
                        // Use first option as the "OK/Good" default
                        this.testingWorkLogValues[f.label] =
                            f.options[0]?.value ?? f.defaultValue ?? "";
                    } else if (f.type === "Checkbox") {
                        this.testingWorkLogValues[f.label] = true;
                    } else {
                        this.testingWorkLogValues[f.label] =
                            f.defaultValue || "OK";
                    }
                });
            } else {
                // FAIL — clear all values so worker selects manually
                this.testingWorkLogFields.forEach((f) => {
                    this.testingWorkLogValues[f.label] = "";
                });
            }
        },

        // Open the Testing Work Log dialog for a given item
        openTestingWorkLog(item) {
            this.testingWorkLogItem = item;
            this.testingWorkLogFields = this.loadTestingFields(
                item.ASINviewer || item.ASIN || item.asin,
            );

            // Snapshot current datetime when dialog opens
            this.testingWorkLogOpenedAt = new Date();

            // Pre-fill: defaults first, then any previously saved values
            const saved = this.loadSavedTestingValues(item.rtcounter);
            const prefilled = {};
            this.testingWorkLogFields.forEach((f) => {
                prefilled[f.label] = saved[f.label] ?? f.defaultValue ?? "";
            });
            this.testingWorkLogValues = prefilled;

            this.testResult = null; // reset decision
            this.showTestingWorkLog = true;
        },

        // Load merged testing fields from localStorage (global + ASIN-specific)
        loadTestingFields(asin) {
            if (!asin) return [];

            const parse = (key) => {
                try {
                    const r = localStorage.getItem(key);
                    return r ? JSON.parse(r) : [];
                } catch {
                    return [];
                }
            };

            const globalFields = parse("asin_global_config_testing");
            const asinFields = parse(`asin_config_testing:${asin}`);

            // Mark globals, merge — ASIN label overrides global of same name
            const markedGlobals = globalFields.map((f) => ({
                ...f,
                _fromGlobal: true,
            }));
            const asinLabels = new Set(asinFields.map((f) => f.label));

            return [
                ...markedGlobals.filter((f) => !asinLabels.has(f.label)),
                ...asinFields,
            ];
        },

        // Load previously saved values for this rtcounter from localStorage
        loadSavedTestingValues(rtcounter) {
            if (!rtcounter) return {};
            try {
                const raw = localStorage.getItem(
                    `testing_worklog:${rtcounter}`,
                );
                return raw ? JSON.parse(raw) : {};
            } catch {
                return {};
            }
        },

        // Save current form values to localStorage
        saveTestingWorkLog() {
            if (!this.testingWorkLogItem?.rtcounter) return;

            // Validate required fields
            const missing = this.testingWorkLogFields.filter(
                (f) => f.required && !this.testingWorkLogValues[f.label],
            );
            if (missing.length) {
                alert(
                    `Please fill in required fields: ${missing.map((f) => f.label).join(", ")}`,
                );
                return;
            }

            try {
                localStorage.setItem(
                    `testing_worklog:${this.testingWorkLogItem.rtcounter}`,
                    JSON.stringify(this.testingWorkLogValues),
                );

                // Close dialog
                this.showTestingWorkLog = false;
                this.testingWorkLogItem = null;
                this.testingWorkLogFields = [];
                this.testingWorkLogValues = {};

                // Brief success feedback (uses Swal if available, else native)
                if (typeof Swal !== "undefined") {
                    Swal.fire({
                        icon: "success",
                        title: "Saved!",
                        text: "Testing work log saved successfully.",
                        confirmButtonText: "OK",
                    });
                } else {
                    alert("Testing work log saved.");
                }
            } catch (e) {
                console.error("Failed to save testing work log:", e);
                alert("Failed to save. Please try again.");
            }
        },

        // Get the pre-typed note for the currently selected option value
        getPreTypedNote(field, selectedValue) {
            if (
                !field.preTypedNotes ||
                !field.options?.length ||
                !selectedValue
            )
                return null;
            const opt = field.options.find((o) => o.value === selectedValue);
            return opt?.hasNote ? opt.note : null;
        },
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.updatePricingView);
    },
};
</script>

<style scoped>
.confirmation-content {
    padding: 1rem 0;
}

.confirmation-content p {
    font-size: 1rem;
    line-height: 1.6;
}

.confirmation-content .bg-light {
    background-color: #f8f9fa !important;
    border-left: 3px solid var(--primary-color);
}

/* ── QUICK ACTION section ─────────────────────────────────── */
.twl-quick-action-section {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
}

.twl-quick-action-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.twl-quick-action-badge {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.5px;
    background: #fef9c3;
    color: #854d0e;
    padding: 2px 8px;
    border-radius: 4px;
}

.twl-quick-action-title {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
}

.twl-decision-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    padding: 14px;
    background: #fff;
}

/* ── Decision cards ───────────────────────────────────────── */
.twl-decision-card {
    border: 2px solid transparent;
    border-radius: 10px;
    padding: 20px 16px 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    transition:
        transform 0.1s,
        box-shadow 0.1s;
    user-select: none;
}
.twl-decision-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.twl-decision-card--pass {
    background: #f0fdf4;
    border-color: #22c55e;
}
.twl-decision-card--fail {
    background: #fff5f5;
    border-color: #ef4444;
}

/* Selected state — thicker border + subtle glow */
.twl-decision-card--pass.twl-decision-card--selected {
    border-width: 3px;
    box-shadow: 0 0 0 3px #bbf7d0;
}
.twl-decision-card--fail.twl-decision-card--selected {
    border-width: 3px;
    box-shadow: 0 0 0 3px #fecaca;
}

.twl-decision-icon {
    font-size: 2.8rem;
    font-weight: 900;
    line-height: 1;
}
.twl-decision-icon--pass {
    color: #16a34a;
}
.twl-decision-icon--fail {
    color: #dc2626;
}

.twl-decision-label {
    font-size: 1.4rem;
    font-weight: 900;
    letter-spacing: 1px;
}
.twl-decision-label--pass {
    color: #16a34a;
}
.twl-decision-label--fail {
    color: #dc2626;
}

.twl-decision-sub {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 8px;
}

/* Info box inside card */
.twl-decision-info {
    width: 100%;
    background: #f8fafccc;
    border-radius: 6px;
    padding: 10px 12px;
    font-size: 12px;
    color: #374151;
    line-height: 1.7;
}
.twl-decision-info--fail {
    background: #fff1f1cc;
    color: #7f1d1d;
}

.twl-decision-info-title {
    font-weight: 700;
    margin-bottom: 2px;
}
.twl-decision-info-title--fail {
    color: #dc2626;
}

/* AUTO-FETCH section */
.twl-autofetch-section {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
}

.twl-autofetch-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.twl-autofetch-badge {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.5px;
    background: #e2e8f0;
    color: #475569;
    padding: 2px 8px;
    border-radius: 4px;
}

.twl-autofetch-title {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
}

.twl-autofetch-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    padding: 14px;
    background: #fff;
}

.twl-autofetch-card {
    display: flex;
    flex-direction: column;
    gap: 6px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 10px 12px;
}

.twl-autofetch-label {
    font-size: 11px;
    color: #64748b;
    font-weight: 500;
}

.twl-autofetch-value {
    font-size: 14px;
    color: #1e293b;
    font-weight: 500;
}

/* Info strip — remove old, keeping for reference */
.twl-wrapper {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* Empty state */
.twl-empty {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 16px;
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 6px;
    font-size: 13px;
    color: #0369a1;
}

/* Field cards */
.twl-fields {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.twl-field-card {
    background: #fafafa;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.twl-field-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
    font-size: 14px;
    color: #1e293b;
}

/* Shared input styles */
.twl-input,
.twl-select,
.twl-textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    background: #fff;
    color: #111;
    outline: none;
    transition: border-color 0.15s;
}
.twl-input:focus,
.twl-select:focus,
.twl-textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 2px #e0e7ff;
}
.twl-textarea {
    resize: vertical;
    min-height: 72px;
}

/* Checkbox */
.twl-checkbox-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    cursor: pointer;
}
.twl-checkbox {
    width: 16px;
    height: 16px;
    cursor: pointer;
}

/* Pre-typed note hint */
.twl-note-hint {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: #7c3aed;
    background: #f5f3ff;
    border-left: 3px solid #7c3aed;
    padding: 4px 8px;
    border-radius: 3px;
}

/* Badges */
.twl-badge {
    font-size: 10px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 999px;
}
.twl-badge--required {
    background: #fee2e2;
    color: #b91c1c;
}
.twl-badge--global {
    background: #ede9fe;
    color: #6d28d9;
}
</style>
