<template>
    <div class="vue-container auxiliary-module">
        <TitlePage
            title="Auxiliary Label"
            subtitle="Upload, manage and print auxiliary labels for internal use"
        />

        <!-- Upload Button (Fixed Position) -->
        <button @click="openUploadModal" class="upload-btn">
            <i class="pi pi-plus"></i> ADD
        </button>

        <!-- Loading Spinner -->
        <div v-if="loading" class="loading-container">
            <i class="pi pi-spin pi-spinner" style="font-size: 2rem"></i>
            <p>Loading auxiliaries...</p>
        </div>

        <!-- Error Message -->
        <div v-if="error" class="error-message">
            <i class="pi pi-exclamation-triangle"></i>
            {{ error }}
        </div>

        <!-- Auxiliary Cards Container -->
        <div v-else-if="!loading && auxiliaries.length > 0" class="container">
            <div 
                v-for="aux in auxiliaries" 
                :key="aux.id"
                class="item">
                <div class="image-wrapper">
                    <h2>{{ 'AUX00' + aux.auxcode }}</h2>
                    <img 
                        class="auximages" 
                        :src="getImageUrl(aux.auximgname)" 
                        :alt="aux.auxname"
                        @error="handleImageError"
                    />
                    <h3>{{ aux.auxname }}</h3>
                    
                    <!-- Edit Button -->
                    <button 
                        type="button" 
                        class="edit-btn" 
                        @click="openEditModal(aux)"
                        title="Edit">
                        ✏️
                    </button>
                    
                    <!-- Delete Button -->
                    <button 
                        type="button" 
                        class="delete-btn" 
                        @click="confirmDelete(aux)"
                        title="Delete">
                        🗑
                    </button>
                </div>

                <!-- Quantity Counter -->
                <div class="counter-control">
                    <button type="button" @click="decrementQuantity(aux.id)">-</button>
                    <input 
                        type="number" 
                        :id="'qty' + aux.id"
                        v-model.number="aux.quantity" 
                        min="1" 
                        max="999"
                        @input="validateQuantity(aux)"
                    />
                    <button type="button" @click="incrementQuantity(aux.id)">+</button>
                </div>

                <!-- Printer Selection -->
                <div class="printer-select-inline">
                    <Select
                        v-model="aux.selectedPrinter"
                        :options="printers"
                        optionLabel="printername_short"
                        placeholder="Select printer"
                        class="printer-select-small"
                        @change="saveLastPrinter(aux.selectedPrinter)"
                    />
                </div>

                <!-- Print Button with Loading State -->
                <Button 
                    @click="openPrintModal(aux)"
                    :label="aux.isPrinting ? 'Printing...' : 'Print'"
                    :icon="aux.isPrinting ? 'pi pi-spin pi-spinner' : 'pi pi-print'"
                    :severity="aux.isPrinting ? 'secondary' : 'success'"
                    class="print-btn"
                    :disabled="!aux.selectedPrinter || aux.isPrinting"
                    :loading="aux.isPrinting"
                />
            </div>
        </div>

        <!-- No Data Message -->
        <div v-else-if="!loading && auxiliaries.length === 0" class="no-data">
            <i class="pi pi-info-circle"></i>
            <p>No auxiliary labels found</p>
        </div>

        <!-- Upload Modal -->
        <Dialog 
            v-model:visible="showUploadModal"
            modal
            header="Upload Auxiliary Image"
            :style="{ width: '30rem' }">
            <div class="upload-section">
                <div class="form-group">
                    <label for="auxname">Auxiliary Name *</label>
                    <InputText 
                        id="auxname"
                        v-model.trim="uploadForm.auxname" 
                        placeholder="Enter name"
                        @input="validateAuxName"
                        :class="{ 'p-invalid': uploadWarnings.nameExists }"
                    />
                    <small v-if="uploadForm.auxname && uploadForm.auxname.length < 3" class="text-danger">
                        Name must be at least 3 characters
                    </small>
                    <small v-if="uploadWarnings.nameExists" class="text-danger">
                        ⚠️ This name already exists! Please use a different name.
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="auxcode">Auxiliary Code *</label>
                    <InputText 
                        id="auxcode"
                        v-model.trim="uploadForm.auxcode" 
                        placeholder="Enter code"
                        @input="validateAuxCode"
                        :class="{ 'p-invalid': uploadWarnings.codeExists }"
                    />
                    <small v-if="uploadForm.auxcode && uploadForm.auxcode.length < 2" class="text-danger">
                        Code must be at least 2 characters
                    </small>
                    <small v-if="uploadWarnings.codeExists" class="text-danger">
                        ⚠️ This code already exists! Please use a different code.
                    </small>
                </div>

                <div class="form-group">
                    <label for="imageUpload">Select Image *</label>
                    <input 
                        type="file" 
                        id="imageUpload"
                        ref="fileInput"
                        @change="handleFileSelect"
                        accept="image/*"
                    />
                </div>

                <!-- Image Preview -->
                <img 
                    v-if="imagePreview" 
                    :src="imagePreview" 
                    id="imagePreview"
                    alt="Preview"
                />
            </div>

            <template #footer>
                <Button 
                    label="Cancel" 
                    icon="pi pi-times" 
                    @click="closeUploadModal"
                    severity="secondary"
                />
                <Button 
                    label="Upload" 
                    icon="pi pi-upload" 
                    @click="uploadAuxiliary"
                    :loading="uploading"
                    :disabled="!isUploadFormValid"
                    severity="success"
                />
            </template>
        </Dialog>

        <!-- Edit Modal -->
        <Dialog 
            v-model:visible="showEditModal"
            modal
            header="Edit Auxiliary"
            :style="{ width: '30rem' }">
            <div class="form-group">
                <label for="editAuxname">Auxiliary Name *</label>
                <InputText 
                    id="editAuxname"
                    v-model.trim="editForm.auxname"
                    @input="validateEditName"
                    :class="{ 'p-invalid': editWarnings.nameExists }"
                />
                <small v-if="editWarnings.nameExists" class="text-danger">
                    ⚠️ This name already exists! Please use a different name.
                </small>
            </div>
            
            <div class="form-group">
                <label for="editAuxcode">Auxiliary Code *</label>
                <InputText 
                    id="editAuxcode"
                    v-model.trim="editForm.auxcode"
                    @input="validateEditCode"
                    :class="{ 'p-invalid': editWarnings.codeExists }"
                />
                <small v-if="editWarnings.codeExists" class="text-danger">
                    ⚠️ This code already exists! Please use a different code.
                </small>
            </div>

            <template #footer>
                <Button 
                    label="Cancel" 
                    icon="pi pi-times" 
                    @click="closeEditModal"
                    severity="secondary"
                />
                <Button 
                    label="Save" 
                    icon="pi pi-check" 
                    @click="updateAuxiliary"
                    :loading="updating"
                    :disabled="!editForm.auxname || !editForm.auxcode || editWarnings.nameExists || editWarnings.codeExists"
                    severity="info"
                />
            </template>
        </Dialog>

        <!-- Print Confirmation Modal -->
        <Dialog 
            v-model:visible="showPrintModal"
            modal
            header="Confirm Print"
            :style="{ width: '30rem' }">
            
            <!-- Printing Animation Overlay -->
            <div v-if="printing" class="printing-overlay">
                <div class="printing-content">
                    <i class="pi pi-spin pi-cog" style="font-size: 3rem; color: #28a745;"></i>
                    <p class="printing-text">Printing in progress...</p>
                    <p class="printing-subtext">{{ printForm.quantity }} label(s)</p>
                </div>
            </div>

            <div v-else>
                <p>Print <strong>{{ printForm.quantity }}</strong> label(s) for:</p>
                <p><strong>{{ printForm.auxname }}</strong></p>
                <p>Printer: <strong>{{ printForm.printerName || 'None selected' }}</strong></p>
            </div>

            <template #footer>
                <Button 
                    label="Cancel" 
                    icon="pi pi-times" 
                    @click="closePrintModal"
                    severity="secondary"
                    :disabled="printing"
                />
                <Button 
                    label="Print" 
                    icon="pi pi-print" 
                    @click="printAuxiliary"
                    :loading="printing"
                    :disabled="printing"
                    severity="success"
                />
            </template>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog 
            v-model:visible="showDeleteDialog"
            modal
            header="Confirm Delete"
            :style="{ width: '30rem' }">
            <p>Are you sure you want to delete:</p>
            <p><strong>{{ deleteTarget?.auxname }}</strong>?</p>

            <template #footer>
                <Button 
                    label="Cancel" 
                    icon="pi pi-times" 
                    @click="closeDeleteDialog"
                    severity="secondary"
                />
                <Button 
                    label="Delete" 
                    icon="pi pi-trash" 
                    @click="deleteAuxiliary"
                    :loading="deleting"
                    severity="danger"
                />
            </template>
        </Dialog>

        <ScrollTop />
    </div>
</template>

<script>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import ScrollTop from 'primevue/scrolltop';
import TitlePage from '../../components/TitlePage/TitlePage.vue';
import AuxiliaryMixin from './auxiliary.js';

export default {
    name: 'AuxiliaryModule',
    mixins: [AuxiliaryMixin],
    components: {
        Button,
        Dialog,
        InputText,
        Select,
        ScrollTop,
        TitlePage
    }
};
</script>

<style scoped>
/* Container */
.vue-container {
    padding: 20px;
    min-height: 100vh;
    background-color: #f5f6fa;
}

/* Upload Button */
.upload-btn {
    position: fixed;
    top: 100px;
    right: 50px;
    background-color: #28a745;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 12px 20px;
    font-size: 14px;
    cursor: pointer;
    z-index: 999;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
    transition: background-color 0.3s ease;
}

.upload-btn:hover {
    background-color: #218838;
}

/* Loading & Error States */
.loading-container,
.error-message,
.no-data {
    text-align: center;
    padding: 40px 20px;
    font-size: 16px;
}

.error-message {
    color: #dc3545;
}

.no-data {
    color: #6c757d;
}

/* Auxiliary Cards Container */
.container {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 20px;
    padding: 20px;
}

.item {
    width: 320px;
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    padding: 15px;
    text-align: center;
    transition: transform 0.2s;
}

.item:hover {
    transform: scale(1.02);
}

.image-wrapper {
    position: relative;
    margin-bottom: 15px;
}

.auximages {
    width: 100%;
    height: 150px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #ccc;
}

.edit-btn,
.delete-btn {
    position: absolute;
    top: 10px;
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 50%;
    font-size: 14px;
    width: 28px;
    height: 28px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
}

.edit-btn {
    right: 45px;
}

.edit-btn:hover {
    background-color: #0056b3;
}

.delete-btn {
    right: 10px;
    background-color: crimson;
}

.delete-btn:hover {
    background-color: darkred;
}

/* Counter Controls */
.counter-control {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin: 15px 0;
}

.counter-control button {
    background-color: #003366;
    color: #fff;
    border: none;
    padding: 6px 12px;
    font-size: 16px;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.2s ease-in-out;
}

.counter-control button:hover {
    background-color: #005299;
}

.counter-control input[type="number"] {
    width: 60px;
    padding: 6px;
    text-align: center;
    font-size: 16px;
    border: 1px solid #ccc;
    border-radius: 6px;
    background-color: #f8f8f8;
}

/* Printer Selection Inline */
.printer-select-inline {
    margin: 15px 0;
    width: 100%;
}

.printer-select-small {
    width: 100%;
}

/* Print Button */
.print-btn {
    width: 100%;
    margin-top: 10px;
}

/* Printing Animation Overlay */
.printing-overlay {
    position: relative;
    min-height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    border-radius: 8px;
    padding: 40px;
}

.printing-content {
    text-align: center;
}

.printing-text {
    font-size: 1.5rem;
    font-weight: 600;
    color: #28a745;
    margin-top: 20px;
    margin-bottom: 10px;
}

.printing-subtext {
    font-size: 1rem;
    color: #6c757d;
    margin: 0;
}

/* Modal Styles */
.upload-section {
    padding: 20px 0;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.text-danger {
    color: #dc3545;
    display: block;
    margin-top: 5px;
    font-weight: 600;
}

.p-invalid {
    border-color: #dc3545 !important;
}

#imagePreview {
    display: block;
    width: 100%;
    max-width: 300px;
    max-height: 250px;
    border: 1px solid #ccc;
    border-radius: 8px;
    margin: 15px auto;
    object-fit: cover;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .upload-btn {
        top: 120px;
        right: 20px;
        padding: 8px 14px;
        font-size: 12px;
    }

    .container {
        flex-direction: column;
        align-items: center;
        padding: 10px;
        gap: 15px;
    }

    .item {
        width: 85%;
        padding: 12px;
    }

    .printing-overlay {
        min-height: 150px;
        padding: 30px;
    }

    .printing-text {
        font-size: 1.2rem;
    }
}
</style>