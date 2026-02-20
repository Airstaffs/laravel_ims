<template>
    <div class="product-image-gallery">
        <!-- Label -->
        <label class="gallery-label">{{ label }}</label>
        
        <!-- Main Image Display -->
        <div 
            v-if="localImageList.length" 
            class="gallery-container"
            :key="`gallery-${localRenderKey}`"
            @mouseenter="handleMouseEnter"
            @mouseleave="handleMouseLeave"
        >
            <!-- Main Image -->
            <div class="main-image-display" @click="openZoomModal(localActiveIndex)">
                <img
                    :src="activeImageUrl"
                    :key="`main-${activeImageUrl}-${localRenderKey}`"
                    :alt="`${label}`"
                    @error="onImageError"
                />
                <!-- Image Counter Badge -->
                <div v-if="showCount && localImageList.length > 1" class="image-counter">
                    {{ localActiveIndex + 1 }} / {{ localImageList.length }}
                </div>
                
                <!-- Zoom Indicator -->
                <div class="zoom-indicator">
                    <i class="pi pi-search-plus"></i>
                    <span>Click to zoom</span>
                </div>
            </div>

            <!-- Thumbnail Strip -->
            <transition name="slide-up">
                <div 
                    v-show="showThumbnails && localImageList.length > 1" 
                    class="thumbnail-strip"
                    :class="{ 'mobile-thumbnails': isMobile }"
                >
                    <div
                        v-for="(img, index) in localImageList"
                        :key="`thumb-${index}-${img}-${localRenderKey}`"
                        :class="['thumbnail-item', { selected: index === localActiveIndex }]"
                        @click.stop="updateActiveIndex(index)"
                    >
                        <img
                            :src="getThumbnailUrl(img)"
                            :alt="`Thumbnail ${index + 1}`"
                            @error="onImageError"
                        />
                    </div>
                </div>
            </transition>

            
            <!-- Manage Button -->
            <Button
                label="Manage Images"
                icon="pi pi-cog"
                @click="openDialog"
                class="manage-btn"
                outlined
                size="small"
            />

        </div>
        

        <!-- No Images State -->
        <div v-else class="empty-state" @click="openDialog">
            <i class="pi pi-image"></i>
            <p>No images available</p>
            <p class="empty-hint">Click to add images</p>
        </div>

        <!-- Image Management Dialog -->
        <Dialog
            v-model:visible="showDialog"
            modal
            :key="`dialog-${dialogKey}`"
            :header="`Manage ${label}`"
            :style="{ width: '90vw', maxWidth: '1200px' }"
            :pt="{
                root: { class: 'mobile-fullscreen-dialog' },
            }"
            @show="onDialogShow"
        >
            <!-- Upload Progress Bar -->
            <div v-if="uploadQueue.length > 0" class="upload-progress-container">
                <div class="upload-progress-header">
                    <span>Uploading {{ uploadQueue.filter(u => !u.completed).length }} of {{ uploadQueue.length }} images</span>
                    <span>{{ overallProgress }}%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" :style="{ width: overallProgress + '%' }"></div>
                </div>
                <div class="upload-items">
                    <div 
                        v-for="(upload, idx) in uploadQueue" 
                        :key="idx"
                        class="upload-item"
                        :class="{ 
                            'completed': upload.completed,
                            'error': upload.error 
                        }"
                    >
                        <i :class="upload.completed ? 'pi pi-check-circle' : upload.error ? 'pi pi-times-circle' : 'pi pi-spin pi-spinner'"></i>
                        <span>{{ upload.filename }}</span>
                        <span v-if="upload.error" class="error-text">{{ upload.error }}</span>
                    </div>
                </div>
            </div>

            <div class="dialog-toolbar">
                <button
                    class="toolbar-btn"
                    :class="{ active: isSelectionMode }"
                    @click="toggleSelectionMode"
                >
                    <i class="pi pi-check-square"></i>
                    {{ isSelectionMode ? 'Cancel' : 'Select' }}
                </button>

                <template v-if="isSelectionMode">
                    <button
                        class="toolbar-btn select-all-btn"
                        @click="toggleSelectAll"
                    >
                        <i :class="isAllSelected ? 'pi pi-minus-circle' : 'pi pi-check-circle'"></i>
                        {{ isAllSelected ? 'Deselect All' : 'Select All' }}
                    </button>

                    <button
                        class="toolbar-btn delete-selected-btn"
                        :disabled="selectedIndices.length === 0"
                        @click="confirmDeleteSelected"
                    >
                        <i class="pi pi-trash"></i>
                        Delete ({{ selectedIndices.length }})
                    </button>
                </template>
            </div>

            <!-- Force re-render with v-if -->
            <div v-if="dialogContentKey" :key="dialogContentKey" class="image-grid">
                <!-- Existing Images -->
                <div
                    v-for="(image, index) in displayImageList"
                    :key="`img-card-${index}-${image}-${dialogContentKey}`"
                    class="image-card"
                >
                    <div class="image-card-content"
    :class="{ 'is-selected': isSelectionMode && selectedIndices.includes(index) }"
    @click="isSelectionMode ? toggleImageSelection(index) : null">

     <div v-if="isSelectionMode" class="selection-overlay">
        <div class="selection-checkbox" :class="{ checked: selectedIndices.includes(index) }">
            <i class="pi pi-check"></i>
        </div>
    </div>
                        <!-- Delete Button -->
                          <button
        v-if="!isImageProcessing(index) && !isSelectionMode"
        class="delete-btn"
        @click.stop="confirmDeleteImage(index, image)"
        type="button"
        title="Delete image"
    >
        <i class="pi pi-trash"></i>
    </button>

                        <!-- Zoom Button -->
                       <button
        v-if="!isImageProcessing(index) && !isSelectionMode"
        class="zoom-btn"
        @click.stop="openZoomModalWithImage(image)"
        type="button"
        title="View fullscreen"
    >
        <i class="pi pi-search-plus"></i>
    </button>
                        <!-- Image -->
                        <img
                            :src="getImageUrl(image)"
                            :key="`img-src-${index}-${dialogContentKey}`"
                            :alt="`Image ${index + 1}`"
                            class="card-image"
                            :class="{ processing: isImageProcessing(index) }"
                            @error="onImageError"
                            @load="onImageLoad(index, image)"
                            @click="openZoomModalWithImage(image, index)"
                        />

                        <!-- Processing Overlay -->
                        <div
                            v-if="isImageProcessing(index)"
                            class="processing-overlay"
                        >
                            <div class="spinner"></div>
                            <p>{{ processingStates[index] || 'Processing...' }}</p>
                        </div>
                    </div>

                    <!-- Hidden File Input -->
                    <input
                        type="file"
                        :ref="el => { if (el) fileInputRefs[index] = el }"
                        accept="image/*"
                        style="display: none"
                        @change="handleFileChange($event, index)"
                    />

                    <!-- Update Button -->
                    <Button
                        :label="isImageProcessing(index) ? 'Uploading...' : 'Update'"
                        size="small"
                        icon="pi pi-upload"
                        :loading="isImageProcessing(index)"
                        :disabled="isImageProcessing(index) || isAnyUploading"
                        @click="handleUploadClick(index, image)"
                        class="w-full"
                    />
                </div>

                <!-- Add New Image Card -->
                <div
                    v-if="displayImageList.length < maxImages"
                    class="image-card add-card"
                >
                    <div 
                        class="add-card-content" 
                        :class="{ 'is-adding': isAddingNew }"
                        @click="!isAddingNew && !isAnyUploading && handleAddNewImageClick()"
                    >
                        <template v-if="isAddingNew">
                            <div class="spinner"></div>
                            <p>Uploading...</p>
                        </template>
                        <template v-else>
                            <i class="pi pi-plus"></i>
                            <p>Add Images</p>
                            <span>{{ displayImageList.length }} / {{ maxImages }}</span>
                        </template>
                    </div>

                    <!-- Multiple file input -->
                    <input
                        type="file"
                        ref="addNewImageInput"
                        accept="image/*"
                        multiple
                        style="display: none"
                        @change="handleAddImageChange"
                    />
                </div>
            </div>
        </Dialog>

        <!-- Image Zoom Modal -->
        <ZoomImageModal
            v-model:visible="showZoomModal"
            :images="imagesWithPathList"
            :initialIndex="localActiveIndex"
            :title="label"
        />
    </div>
</template>

<script>
import { Dialog, Button } from "primevue";
import axios from "axios";
import Swal from "sweetalert2";
import { DEFAULT_IMAGE } from "../../constant";
import ZoomImageModal from "../ZoomImageModal/ZoomImageModal.vue";

export default {
    name: "ProductImageGallery",
    components: {
        Dialog,
        Button,
        ZoomImageModal,
    },
    props: {
        label: {
            type: String,
            required: true,
        },
        imageList: {
            type: Array,
            default: () => [],
        },
        activeIndex: {
            type: Number,
            default: 0,
        },
        basePath: {
            type: String,
            default: "/images/product_images/Airstaffs/",
        },
        imageType: {
            type: String,
            required: true,
            validator: (value) =>
                ["captured", "serial", "tracking"].includes(value),
        },
        maxImages: {
            type: Number,
            default: 12,
        },
        showCount: {
            type: Boolean,
            default: false,
        },
        productId: {
            type: [String, Number],
            required: true,
        },
        company: {
            type: String,
            default: "Airstaffs",
        },
    },
    data() {
        return {
            localImageList: [],
            localActiveIndex: 0,
            localRenderKey: 0,
            showDialog: false,
            dialogKey: 0,
            imageGridKey: 0,
            dialogContentKey: 1,
            processingStates: {},
            isAddingNew: false,
            fileInputRefs: {},
            defaultImage: DEFAULT_IMAGE,
            showThumbnails: false,
            isMobile: false,
            cacheBustTimestamp: Date.now(),
            uploadQueue: [],
            showZoomModal: false,
            zoomImagePath: "",
            imagesWithPathList: [],
            isSelectionMode: false,
            selectedIndices: [],
        };
    },
    computed: {
        isAllSelected() {
    return this.displayImageList.length > 0 &&
        this.selectedIndices.length === this.displayImageList.length;
},
        displayImageList() {
            const list = [...this.localImageList];
            console.log('🎨 displayImageList computed:', {
                length: list.length,
                images: list
            });
            return list;
        },
        activeImageUrl() {
            if (!this.localImageList || this.localImageList.length === 0) {
                console.log('⚠️ No images in list, returning default');
                return this.defaultImage;
            }

            const safeIndex = Math.min(
                Math.max(0, this.localActiveIndex), 
                this.localImageList.length - 1
            );

            const currentImage = this.localImageList[safeIndex];
            
            if (!currentImage) {
                console.log('⚠️ No image at index', safeIndex, 'returning default');
                return this.defaultImage;
            }

            let imageUrl;
            if (currentImage.startsWith("/images/")) {
                imageUrl = currentImage;
            } else {
                imageUrl = this.basePath + currentImage;
            }

            console.log('🎯 Active image URL computed:', imageUrl);
            return imageUrl;
        },
        isAnyUploading() {
            return Object.keys(this.processingStates).length > 0 || this.isAddingNew;
        },
        overallProgress() {
            if (this.uploadQueue.length === 0) return 0;
            const completed = this.uploadQueue.filter(u => u.completed || u.error).length;
            return Math.round((completed / this.uploadQueue.length) * 100);
        }
    },
    watch: {
        imageList: {
            immediate: true,
            handler(newVal) {
                console.log(`📥 ${this.label} - imageList prop changed:`, newVal.length, 'images');
                this.localImageList = [...newVal];
                
                if (this.localActiveIndex >= this.localImageList.length) {
                    this.localActiveIndex = Math.max(0, this.localImageList.length - 1);
                }
                
                if (this.showDialog) {
                    this.$nextTick(() => {
                        console.log('🔄 imageList changed while dialog open, refreshing');
                        this.refreshDialogContent();
                    });
                }
            },
            deep: true,
        },
        activeIndex: {
            immediate: true,
            handler(newVal) {
                this.localActiveIndex = newVal;
            },
        },
        localImageList: {
            handler(newVal, oldVal) {
                console.log(`🖼️ ${this.label} - localImageList watcher:`, {
                    newLength: newVal.length,
                    oldLength: oldVal?.length,
                    activeIndex: this.localActiveIndex,
                    dialogOpen: this.showDialog
                });
            },
            deep: true,
        },
    },
    mounted() {
        this.checkMobile();
        window.addEventListener('resize', this.checkMobile);
        
        if (this.isMobile) {
            this.showThumbnails = true;
        }
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.checkMobile);
    },
    methods: {
        toggleSelectionMode() {
    this.isSelectionMode = !this.isSelectionMode;
    this.selectedIndices = [];
},

toggleImageSelection(index) {
    const pos = this.selectedIndices.indexOf(index);
    if (pos === -1) {
        this.selectedIndices.push(index);
    } else {
        this.selectedIndices.splice(pos, 1);
    }
},

toggleSelectAll() {
    if (this.isAllSelected) {
        this.selectedIndices = [];
    } else {
        this.selectedIndices = this.displayImageList.map((_, i) => i);
    }
},
async confirmDeleteSelected() {
    const count = this.selectedIndices.length;
    const result = await Swal.fire({
        title: `Delete ${count} Image${count > 1 ? 's' : ''}?`,
        text: `Are you sure you want to delete ${count} selected image${count > 1 ? 's' : ''}? This cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: `Yes, delete ${count}`,
        cancelButtonText: 'Cancel',
        reverseButtons: true,
    });

    if (result.isConfirmed) {
        await this.deleteSelectedImages();
    }
},
async deleteSelectedImages() {
    // Build list of { index, imgNumber } sorted descending so splice doesn't shift indices
    const targets = this.selectedIndices
        .map(index => {
            const image = this.localImageList[index];
            const urlWithoutQuery = image.split('?')[0];
            const imgNumber = parseInt(urlWithoutQuery.split('_').pop().match(/(\d+)/)?.[1] || (index + 1));
            return { index, imgNumber };
        })
        .sort((a, b) => b.index - a.index); // descending

    const imageNumbers = targets.map(t => t.imgNumber);

    try {
        // Set processing state on all selected
        targets.forEach(({ index }) => this.setImageProcessing(index, 'Deleting...'));

        const response = await axios.post(
            '/api/houseage/delete-images-bulk',
            {
                productId: String(this.productId),
                imageNumbers,
                imageType: this.imageType,
            },
            { withCredentials: true }
        );

        if (response.data.success) {
            // Remove from localImageList descending to preserve indices
            targets.forEach(({ index }) => {
                this.localImageList.splice(index, 1);
            });

            if (this.localActiveIndex >= this.localImageList.length) {
                this.localActiveIndex = Math.max(0, this.localImageList.length - 1);
            }

            this.cacheBustTimestamp = Date.now();
            this.isSelectionMode = false;
            this.selectedIndices = [];
            this.refreshDialogContent();

            const { successCount, failCount } = response.data;
            await Swal.fire({
                title: failCount === 0 ? 'Deleted!' : 'Partial Success',
                text: failCount === 0
                    ? `${successCount} image${successCount > 1 ? 's' : ''} deleted successfully.`
                    : `${successCount} deleted, ${failCount} failed.`,
                icon: failCount === 0 ? 'success' : 'warning',
                timer: 2000,
                showConfirmButton: false,
            });

            this.$emit('request-refresh');
        } else {
            throw new Error(response.data.message || 'Bulk delete failed');
        }
    } catch (error) {
        console.error('❌ Bulk delete error:', error);
        await Swal.fire({
            title: 'Error',
            text: error.response?.data?.message || 'Failed to delete images',
            icon: 'error',
            confirmButtonColor: '#ef4444',
        });
    } finally {
        targets.forEach(({ index }) => this.setImageProcessing(index, null));
    }
},
        openZoomModal(index) {
            this.zoomImagePath = this.activeImageUrl;
            this.showZoomModal = true;
            this.showDialog = false
            this.imagesWithPathList = this.localImageList.map((image) => image.startsWith("/") ? image : this.basePath + image)
             console.log(index, "indexindex", this.imagesWithPathList)
        },

        openZoomModalWithImage(imagePath) {
            let fullPath;
            if (imagePath.startsWith("/images/")) {
                fullPath = imagePath.split('?')[0]; // Remove cache buster for zoom
            } else {
                fullPath = (this.basePath + imagePath).split('?')[0];
            }
            this.zoomImagePath = fullPath;
            this.showZoomModal = true;
            this.showDialog = false
        },

        isImageProcessing(index) {
            return this.processingStates[index] !== undefined;
        },

        setImageProcessing(index, state) {
            if (state) {
                this.processingStates[index] = state;
            } else {
                delete this.processingStates[index];
            }
            this.$forceUpdate();
        },

        getImageUrl(img) {
            if (!img) {
                console.warn('⚠️ getImageUrl: No image provided');
                return this.defaultImage;
            }
            
            let url;
            
            if (img.startsWith("/images/")) {
                url = img;
            } else {
                url = this.basePath + img;
            }
            
            const cleanUrl = url.split('?')[0];
            const finalUrl = `${cleanUrl}?t=${this.cacheBustTimestamp}`;
            
            console.log('🖼️ getImageUrl:', {
                input: img,
                clean: cleanUrl,
                output: finalUrl
            });
            
            return finalUrl;
        },

        checkMobile() {
            const wasMobile = this.isMobile;
            this.isMobile = window.innerWidth <= 768;
            
            if (this.isMobile) {
                this.showThumbnails = true;
            } else if (wasMobile && !this.isMobile) {
                this.showThumbnails = false;
            }
        },

        handleMouseEnter() {
            if (!this.isMobile) {
                this.showThumbnails = true;
            }
        },

        handleMouseLeave() {
            if (!this.isMobile) {
                this.showThumbnails = false;
            }
        },

        getThumbnailUrl(img) {
            if (!img) return this.defaultImage;
            return img.startsWith("/images/") ? img : this.basePath + img;
        },

        updateActiveIndex(index) {
            this.localActiveIndex = index;
            this.$emit("update:activeIndex", index);
        },

        openDialog() {
            console.log(`🚪 Opening ${this.label} dialog`);
            this.showDialog = true;
            this.refreshDialogContent();
        },

        closeDialog() {
            this.showDialog = false;
        },

        onDialogShow() {
            console.log('📖 Dialog shown event triggered');
            this.refreshDialogContent();
        },

        refreshDialogContent() {
            console.log('🔄 Starting dialog content refresh');
            console.log('📊 Current state before refresh:', {
                localImageListLength: this.localImageList.length,
                displayImageListLength: this.displayImageList.length,
                dialogContentKey: this.dialogContentKey,
                images: this.localImageList
            });
            
            this.dialogContentKey = 0;
            
            this.$nextTick(() => {
                this.dialogContentKey = Date.now();
                console.log('✨ Dialog content key updated:', this.dialogContentKey);
                console.log('📊 State after refresh:', {
                    localImageListLength: this.localImageList.length,
                    displayImageListLength: this.displayImageList.length
                });
            });
        },

        onImageError(event) {
            console.warn('⚠️ Image load error:', event.target.src);
            event.target.src = this.defaultImage;
            event.target.onerror = null;
        },

        onImageLoad(index, image) {
            console.log(`✅ Image loaded successfully:`, {
                index,
                src: image,
                actualSrc: event?.target?.src
            });
        },

        handleUploadClick(index, currentImage) {
            const fileInput = this.fileInputRefs[index];
            if (fileInput) {
                fileInput.click();
            }
        },

        handleAddNewImageClick() {
            this.$refs.addNewImageInput.click();
        },

        extractImageNumbers(imageList) {
            if (!imageList || !Array.isArray(imageList)) {
                return [];
            }

            const numbers = [];

            imageList.forEach((imagePath) => {
                if (!imagePath) return;

                const imageNumber = imagePath
                    .split("_")
                    .pop()
                    .match(/(\d+)/)?.[1];

                if (imageNumber) {
                    const num = parseInt(imageNumber, 10);
                    if (
                        num >= 1 &&
                        num <= this.maxImages &&
                        !numbers.includes(num)
                    ) {
                        numbers.push(num);
                    }
                }
            });

            return numbers.sort((a, b) => a - b);
        },

        findNextAvailableImageNumbers(count) {
            const usedNumbers = this.extractImageNumbers(this.localImageList);
            const available = [];

            for (let i = 1; i <= this.maxImages && available.length < count; i++) {
                if (!usedNumbers.includes(i)) {
                    available.push(i);
                }
            }

            return available;
        },

        confirmDeleteImage(index, currentImage) {
            const urlWithoutQuery = currentImage.split('?')[0];
            const imgNumber = urlWithoutQuery.split("_").pop().match(/(\d+)/)?.[1] || (index + 1);

            Swal.fire({
                title: "Delete Image?",
                text: `Are you sure you want to delete image ${imgNumber}? This action cannot be undone.`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#ef4444",
                cancelButtonColor: "#6b7280",
                confirmButtonText: "Yes, delete it!",
                cancelButtonText: "Cancel",
                reverseButtons: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    this.handleDeleteImage(index, imgNumber);
                }
            });
        },

        async handleFileChange(event, index) {
            try {
                const file = event.target.files[0];
                if (!file) return;

                const currentImage = this.localImageList[index];
                const urlWithoutQuery = currentImage.split('?')[0];
                const imgNumber = urlWithoutQuery.split("_").pop().match(/(\d+)/)?.[1] || (index + 1);

                console.log('📤 Updating image:', {
                    index,
                    currentImage,
                    imgNumber,
                    fileName: file.name
                });

                this.setImageProcessing(index, 'Uploading...');

                const formData = new FormData();
                formData.append("images[]", file);
                formData.append("productId", this.productId);
                formData.append("imageNumbers[]", imgNumber);
                formData.append("imageType", this.imageType);

                const response = await axios.post(
                    "/api/houseage/upload-images",
                    formData,
                    {
                        headers: { "Content-Type": "multipart/form-data" },
                        withCredentials: true,
                    }
                );

                console.log('📬 Upload response:', response.data);

                if (response.data.success && response.data.results.length > 0) {
                    const result = response.data.results[0];
                    
                    if (result.success) {
                        const uniqueTimestamp = Date.now();
                        const basePath = `/images/product_images/Airstaffs/`;
                        const newImagePath = basePath + result.filename;
                        const cachedPath = `${newImagePath}?t=${uniqueTimestamp}`;
                        
                        console.log('✅ Update success:', {
                            filename: result.filename,
                            newPath: cachedPath,
                            index
                        });
                        
                        const updatedList = [...this.localImageList];
                        updatedList[index] = cachedPath;
                        this.localImageList = updatedList;
                        
                        console.log('📋 LocalImageList after update:', this.localImageList.length);
                        
                        this.cacheBustTimestamp = uniqueTimestamp;
                        this.localRenderKey++;
                        this.dialogKey++;
                        this.imageGridKey++;

                        await this.$nextTick();
                        console.log('✨ After nextTick');
                        
                        await new Promise(resolve => setTimeout(resolve, 100));
                        console.log('⏱️ After delay');
                        
                        this.refreshDialogContent();
                        console.log('🔄 Dialog refreshed');
                        
                        await Swal.fire({
                            title: "Upload Success",
                            text: result.message || "Image uploaded successfully",
                            icon: "success",
                            timer: 2000,
                            showConfirmButton: false,
                        });

                        this.$emit("request-refresh");
                    } else {
                        throw new Error(result.message || 'Upload failed');
                    }
                } else {
                    throw new Error(response.data.message || 'Upload failed');
                }
            } catch (error) {
                console.error("❌ Upload error:", error);
                await Swal.fire({
                    title: "Error",
                    text: error.response?.data?.message || error.message || "Failed to upload image",
                    icon: "error",
                    confirmButtonColor: "#ef4444",
                });
            } finally {
                event.target.value = "";
                this.setImageProcessing(index, null);
            }
        },

        async handleAddImageChange(event) {
            try {
                const files = Array.from(event.target.files);
                if (files.length === 0) return;

                const availableSlots = this.maxImages - this.localImageList.length;
                const filesToUpload = files.slice(0, availableSlots);

                if (files.length > availableSlots) {
                    await Swal.fire({
                        title: "Limit Exceeded",
                        text: `Can only add ${availableSlots} more image(s). Maximum ${this.maxImages} images allowed.`,
                        icon: "warning",
                        confirmButtonColor: "#f59e0b",
                    });
                }

                const imageNumbers = this.findNextAvailableImageNumbers(filesToUpload.length);

                if (imageNumbers.length < filesToUpload.length) {
                    await Swal.fire({
                        title: "No Space",
                        text: `Not enough available slots`,
                        icon: "error",
                        confirmButtonColor: "#ef4444",
                    });
                    return;
                }

                this.isAddingNew = true;
                
                this.uploadQueue = filesToUpload.map((file, idx) => ({
                    filename: file.name,
                    imageNumber: imageNumbers[idx],
                    completed: false,
                    error: null
                }));

                const formData = new FormData();
                filesToUpload.forEach((file) => {
                    formData.append("images[]", file);
                });
                formData.append("productId", this.productId);
                imageNumbers.forEach(num => {
                    formData.append("imageNumbers[]", num);
                });
                formData.append("imageType", this.imageType);

                const response = await axios.post(
                    "/api/houseage/upload-images",
                    formData,
                    {
                        headers: { "Content-Type": "multipart/form-data" },
                        withCredentials: true,
                    }
                );

                if (response.data.success) {
                    const uniqueTimestamp = Date.now();
                    const basePath = `/images/product_images/Airstaffs/`;
                    const wasEmpty = this.localImageList.length === 0;
                    const newImages = [];
                    
                    console.log('📦 Processing upload results:', response.data.results);
                    console.log('🏢 Using company:', this.company);
                    console.log('📁 Base path:', basePath);
                    
                    response.data.results.forEach((result, idx) => {
                        if (this.uploadQueue[idx]) {
                            this.uploadQueue[idx].completed = result.success;
                            this.uploadQueue[idx].error = result.success ? null : result.message;
                        }

                        if (result.success) {
                            const newImagePath = basePath + result.filename;
                            const cachedPath = `${newImagePath}?t=${uniqueTimestamp + idx}`;
                            newImages.push(cachedPath);
                            console.log(`✅ Image ${idx + 1}:`, {
                                filename: result.filename,
                                fullPath: newImagePath,
                                cached: cachedPath
                            });
                        }
                    });

                    console.log('📊 Total new images to add:', newImages.length);
                    console.log('📋 Current localImageList length:', this.localImageList.length);
                    
                    this.localImageList = [...this.localImageList, ...newImages];
                    
                    console.log('📋 Updated localImageList length:', this.localImageList.length);
                    console.log('🗂️ Full image list:', JSON.stringify(this.localImageList, null, 2));
                    
                    if (wasEmpty && this.localImageList.length > 0) {
                        this.localActiveIndex = 0;
                        this.$emit("update:activeIndex", 0);
                        console.log('🎯 Set active index to 0');
                    }

                    this.cacheBustTimestamp = uniqueTimestamp;
                    this.localRenderKey++;
                    this.dialogKey++;
                    this.imageGridKey++;
                    
                    console.log('🔄 Keys updated:', {
                        cacheBustTimestamp: this.cacheBustTimestamp,
                        localRenderKey: this.localRenderKey,
                        dialogKey: this.dialogKey,
                        imageGridKey: this.imageGridKey
                    });

                    await this.$nextTick();
                    console.log('✨ After nextTick - localImageList:', this.localImageList.length);
                    
                    await new Promise(resolve => setTimeout(resolve, 100));
                    console.log('⏱️ After 100ms delay - localImageList:', this.localImageList.length);
                    
                    this.refreshDialogContent();
                    console.log('🔄 Dialog content refreshed');
                    
                    this.$forceUpdate();
                    console.log('💪 Component force updated');

                    const successCount = response.data.results.filter(r => r.success).length;
                    const failCount = response.data.results.length - successCount;

                    setTimeout(() => {
                        this.uploadQueue = [];
                    }, 3000);

                    if (failCount === 0) {
                        await Swal.fire({
                            title: "Upload Success",
                            text: `Successfully uploaded ${successCount} image(s)`,
                            icon: "success",
                            timer: 2000,
                            showConfirmButton: false,
                        });
                    } else {
                        await Swal.fire({
                            title: "Partial Success",
                            text: `${successCount} succeeded, ${failCount} failed`,
                            icon: "warning",
                            confirmButtonColor: "#f59e0b",
                        });
                    }

                    this.$emit("request-refresh");
                } else {
                    throw new Error(response.data.message || 'Upload failed');
                }
            } catch (error) {
                console.error("❌ Add error:", error);
                this.uploadQueue.forEach(item => {
                    if (!item.completed) {
                        item.error = error.message || "Upload failed";
                    }
                });
                
                await Swal.fire({
                    title: "Error",
                    text: error.response?.data?.message || "Failed to add images",
                    icon: "error",
                    confirmButtonColor: "#ef4444",
                });
            } finally {
                event.target.value = "";
                setTimeout(() => {
                    this.isAddingNew = false;
                    this.uploadQueue = [];
                }, 3000);
            }
        },

        async handleDeleteImage(index, imgNumber) {
            try {
                this.setImageProcessing(index, 'Deleting...');

                const response = await axios.post(
                    "/api/houseage/delete-image",
                    {
                        productId: String(this.productId),
                        capturedImgCount: imgNumber,
                        imageType: this.imageType,
                    },
                    { withCredentials: true }
                );

                if (response.data.success) {
                    this.localImageList.splice(index, 1);

                    if (this.localActiveIndex >= this.localImageList.length) {
                        this.localActiveIndex = Math.max(0, this.localImageList.length - 1);
                    }

                    this.cacheBustTimestamp = Date.now();

                    await Swal.fire({
                        title: "Deleted!",
                        text: `Image ${imgNumber} has been deleted.`,
                        icon: "success",
                        timer: 2000,
                        showConfirmButton: false,
                    });

                    this.$emit("request-refresh");
                } else {
                    throw new Error(response.data.message || 'Delete failed');
                }
            } catch (error) {
                console.error("❌ Delete error:", error);
                await Swal.fire({
                    title: "Error!",
                    text: error.response?.data?.message || "Failed to delete image",
                    icon: "error",
                    confirmButtonColor: "#ef4444",
                });
            } finally {
                this.setImageProcessing(index, null);
            }
        }
    },
};
</script>

<style scoped>
/* Main Container */
.product-image-gallery {
    margin-bottom: .5rem;
}

.gallery-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
}

.gallery-container {
    position: relative;
    border-radius: 8px;
    overflow: visible;
}

.main-image-display {
    position: relative;
    width: 100%;
    height: 200px;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
}

.main-image-display img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    transition: transform 0.3s ease;
}

.main-image-display:hover img {
    transform: scale(1.05);
}

/* Zoom Indicator */
.zoom-indicator {
    position: absolute;
    bottom: 1rem;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.75);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    opacity: 0;
    transition: opacity 0.3s;
    pointer-events: none;
    backdrop-filter: blur(4px);
    z-index: 5;
}

.main-image-display:hover .zoom-indicator {
    opacity: 1;
}

/* Manage Button */
.manage-btn {
    margin-top: 0.5rem;
    width: 100%;
}

.empty-state {
    background: #f9fafb;
    border: 2px dashed #d1d5db;
    border-radius: 8px;
    padding: 3rem 1rem;
    text-align: center;
    color: #9ca3af;
    cursor: pointer;
}

.empty-state:hover {
    border-color: #3b82f6;
    background: #eff6ff;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    display: block;
}

.empty-state p {
    margin: 0;
    font-size: 0.875rem;
}

.empty-hint {
    font-size: 0.75rem !important;
    color: #6b7280 !important;
    margin-top: 0.25rem !important;
}

.image-counter {
    position: absolute;
    top: 12px;
    right: 12px;
    background: rgba(0, 0, 0, 0.75);
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    backdrop-filter: blur(4px);
    z-index: 5;
}

/* Upload Progress Container */
.upload-progress-container {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1.5rem;
}

.upload-progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 1rem;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #3b82f6, #2563eb);
    transition: width 0.3s ease;
}

.upload-items {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-height: 150px;
    overflow-y: auto;
}

.upload-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    background: white;
    border-radius: 4px;
    font-size: 0.875rem;
}

.upload-item i {
    flex-shrink: 0;
}

.upload-item.completed {
    color: #059669;
}

.upload-item.error {
    color: #dc2626;
}

.upload-item span:first-of-type {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.error-text {
    font-size: 0.75rem;
    color: #dc2626;
}

/* Thumbnail Strip */
.thumbnail-strip {
    position: absolute;
    bottom: 42px;
    left: 0;
    right: 0;
    display: flex;
    gap: 0.5rem;
    padding: 1rem;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
    z-index: 10;
    -webkit-overflow-scrolling: touch;
}

.thumbnail-strip.mobile-thumbnails {
    position: relative;
    background: transparent;
    padding: 0.75rem 0;
    margin: 0.5rem auto 0 auto;
    width: 95%;
    bottom: 0;
    left: 0;
    right: 0;
    -webkit-overflow-scrolling: touch;
    scroll-behavior: smooth;
    scrollbar-width: auto;
    scrollbar-color: rgba(0, 0, 0, 0.3) #f3f4f6;
}

.thumbnail-item {
    flex-shrink: 0;
    width: 80px;
    height: 60px;
    border-radius: 6px;
    overflow: hidden;
    cursor: pointer;
    border: 3px solid rgba(255, 255, 255, 0.3);
    transition: all 0.2s ease;
    background: #000;
}

.thumbnail-item:hover {
    border-color: rgba(255, 255, 255, 0.7);
    transform: translateY(1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.thumbnail-item.selected {
    border-color: #f59e0b;
    box-shadow: 0 0 0 1px #f59e0b, 0 4px 12px rgba(245, 158, 11, 0.4);
}

.thumbnail-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.slide-up-enter-active,
.slide-up-leave-active {
    transition: all 0.3s ease;
}

.slide-up-enter-from {
    transform: translateY(100%);
    opacity: 0;
}

.slide-up-leave-to {
    transform: translateY(100%);
    opacity: 0;
}

/* Image Grid */
.image-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.5rem;
    padding: 1rem 0;
    max-height: 70vh;
    overflow-y: auto;
}

.image-card {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.image-card-content {
    position: relative;
    aspect-ratio: 1;
    border-radius: 8px;
    overflow: hidden;
    background: #f3f4f6;
    border: 2px solid #e5e7eb;
    transition: all 0.2s ease;
}

.image-card-content:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
}

.card-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    cursor: pointer;
    transition: opacity 0.2s;
}

.card-image:hover {
    opacity: 0.9;
}

.card-image.processing {
    opacity: 0.5;
}

.delete-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    background: rgba(239, 68, 68, 0.95);
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    z-index: 10;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.delete-btn:hover {
    background: #dc2626;
    transform: scale(1.1);
}

.zoom-btn {
    position: absolute;
    top: 8px;
    left: 8px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    background: rgba(59, 130, 246, 0.95);
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    z-index: 10;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.zoom-btn:hover {
    background: #2563eb;
    transform: scale(1.1);
}

.processing-overlay {
    position: absolute;
    inset: 0;
    background: rgba(255, 255, 255, 0.95);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

.spinner {
    width: 48px;
    height: 48px;
    border: 4px solid #e5e7eb;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.processing-overlay p {
    margin: 0;
    font-size: 0.875rem;
    font-weight: 600;
    color: #6b7280;
}

.add-card {
    display: flex;
    align-items: center;
    justify-content: center;
}

.add-card-content {
    width: 100%;
    aspect-ratio: 1;
    border: 2px dashed #d1d5db;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    cursor: pointer;
    background: #f9fafb;
    transition: all 0.2s ease;
}

.add-card-content:hover:not(.is-adding) {
    border-color: #3b82f6;
    background: #eff6ff;
}

.add-card-content.is-adding {
    cursor: not-allowed;
    background: #ffffff;
    border-color: #e5e7eb;
}

.add-card-content i {
    font-size: 2.5rem;
    color: #9ca3af;
}

.add-card-content:hover:not(.is-adding) i {
    color: #3b82f6;
}

.add-card-content p {
    margin: 0;
    font-weight: 600;
    color: #374151;
    font-size: 0.875rem;
}

.add-card-content span {
    font-size: 0.75rem;
    color: #9ca3af;
}

/* Responsive */
@media (max-width: 768px) {
    .main-image-display {
        height: 250px;
    }

    .thumbnail-item {
        width: 70px;
        height: 52px;
        min-width: 70px;
    }

    .image-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 1rem;
    }

    .slide-up-enter-active,
    .slide-up-leave-active {
        transition: none;
    }

    .zoom-indicator {
        font-size: 0.75rem;
        padding: 0.4rem 0.8rem;
    }
}

@media (max-width: 480px) {
    .main-image-display {
        height: 200px;
    }

    .thumbnail-item {
        width: 60px;
        height: 45px;
        min-width: 60px;
    }
}
/* ── Dialog Toolbar ───────────────────────────────────── */
.dialog-toolbar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.toolbar-btn {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.85rem;
    border-radius: 6px;
    border: 1px solid #d1d5db;
    background: #fff;
    font-size: 0.8rem;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
    transition: all 0.2s;
}

.toolbar-btn:hover:not(:disabled) {
    background: #f3f4f6;
    border-color: #9ca3af;
}

.toolbar-btn.active {
    background: #eff6ff;
    border-color: #3b82f6;
    color: #2563eb;
}

.toolbar-btn.select-all-btn {
    color: #374151;
}

.toolbar-btn.delete-selected-btn {
    background: #fef2f2;
    border-color: #fca5a5;
    color: #dc2626;
    margin-left: auto;
}

.toolbar-btn.delete-selected-btn:hover:not(:disabled) {
    background: #ef4444;
    border-color: #ef4444;
    color: #fff;
}

.toolbar-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

/* ── Selection Overlay on Image Card ─────────────────── */
.image-card-content.is-selected {
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px #3b82f6, 0 4px 12px rgba(59, 130, 246, 0.25);
}

.selection-overlay {
    position: absolute;
    inset: 0;
    background: rgba(59, 130, 246, 0.08);
    z-index: 8;
    cursor: pointer;
    display: flex;
    align-items: flex-start;
    justify-content: flex-start;
    padding: 8px;
}

.selection-checkbox {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 2px solid #fff;
    background: rgba(0, 0, 0, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
    box-shadow: 0 1px 4px rgba(0,0,0,0.3);
}

.selection-checkbox.checked {
    background: #3b82f6;
    border-color: #3b82f6;
}

.selection-checkbox i {
    font-size: 11px;
    color: #fff;
    opacity: 0;
    transition: opacity 0.15s;
}

.selection-checkbox.checked i {
    opacity: 1;
}
</style>