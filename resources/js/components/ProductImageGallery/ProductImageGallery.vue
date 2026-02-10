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
            <div class="main-image-display" @click="openDialog">
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
        </div>

        <!-- No Images State -->
        <div v-else class="empty-state"  @click="openDialog">
            <i class="pi pi-image"></i>
            <p>No images available</p>
        </div>

        <!-- Image Management Dialog -->

        <Dialog
            v-model:visible="showDialog"
            modal
            :key="dialogKey"
            :header="`Manage ${label}`"
            :style="{ width: '90vw', maxWidth: '1200px' }"
            :pt="{
                root: { class: 'mobile-fullscreen-dialog' },
            }"
        >
            <div class="image-grid">
                <!-- Existing Images -->
                <div
                    v-for="(image, index) in localImageList"
                    :key="`dialog-img-${index}-${localRenderKey}`"
                    class="image-card"
                >
                    <div class="image-card-content">
                        <!-- Delete Button -->
                        <button
                            v-if="uploadingIndex !== index && deletingIndex !== index"
                            class="delete-btn"
                            @click.stop="confirmDeleteImage(index, image)"
                            type="button"
                            title="Delete image"
                        >
                            <i class="pi pi-trash"></i>
                        </button>

                        <!-- Image - FIXED: Don't prepend basePath -->
                        <img
                            :src="getImageUrl(image)"
                            :alt="`Image ${index + 1}`"
                            class="card-image"
                            :class="{
                                processing: uploadingIndex === index || deletingIndex === index,
                            }"
                            @error="onImageError"
                        />

                        <!-- Processing Overlay -->
                        <div
                            v-if="uploadingIndex === index || deletingIndex === index"
                            class="processing-overlay"
                        >
                            <div class="spinner"></div>
                            <p>{{ uploadingIndex === index ? 'Uploading...' : 'Deleting...' }}</p>
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
                        :label="uploadingIndex === index ? 'Uploading...' : 'Update'"
                        size="small"
                        icon="pi pi-upload"
                        :loading="uploadingIndex === index"
                        :disabled="uploadingIndex === index || deletingIndex === index"
                        @click="handleUploadClick(index, image)"
                        class="w-full"
                    />
                </div>

                <!-- Add New Image Card -->
                <div
                    v-if="localImageList.length < maxImages"
                    class="image-card add-card"
                >
                    <div class="add-card-content" @click="handleAddNewImageClick">
                        <i class="pi pi-plus"></i>
                        <p>Add Image</p>
                        <span>{{ localImageList.length }} / {{ maxImages }}</span>
                    </div>

                    <input
                        type="file"
                        ref="addNewImageInput"
                        accept="image/*"
                        style="display: none"
                        @change="handleAddImageChange"
                    />
                </div>
            </div>
        </Dialog>
    </div>
</template>

<script>
import { Dialog, Button } from "primevue";
import axios from "axios";
import Swal from "sweetalert2";
import { DEFAULT_IMAGE } from "../../constant";

const API_BASE_URL = import.meta.env.VITE_API_URL || '';

export default {
    name: "ProductImageGallery",
    components: {
        Dialog,
        Button,
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
            dialogKey: 0, // Add this
            uploadingIndex: null,
            deletingIndex: null,
            imgNumber: 0,
            fileInputRefs: {},
            defaultImage: DEFAULT_IMAGE,
            showThumbnails: false,
            isMobile: false,
            cacheBustTimestamp: Date.now(),
        };
    },
    computed: {
        activeImageUrl() {
            const currentImage = this.localImageList[this.localActiveIndex];
            if (!currentImage) return this.defaultImage;

            if (currentImage.startsWith("/images/")) {
                return currentImage;
            }

            return this.basePath + currentImage;
        },
    },
    watch: {
        imageList: {
            immediate: true,
            handler(newVal) {
                console.log(`📥 ${this.label} - imageList changed:`, newVal);
                this.localImageList = [...newVal];
            },
            deep: true,
        },
        activeIndex: {
            immediate: true,
            handler(newVal) {
                this.localActiveIndex = newVal;
            },
        },
    },
    mounted() {
        this.checkMobile();
        window.addEventListener('resize', this.checkMobile);
        
        // Show thumbnails by default on mobile
        if (this.isMobile) {
            this.showThumbnails = true;
        }
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.checkMobile);
    },
    methods: {
    getImageUrl(img) {
    if (!img) return this.defaultImage;
    
    let url;
    
    // If it already has the full path, use it
    if (img.startsWith("/images/")) {
        url = img;
    } else {
        // Otherwise, prepend basePath
        url = this.basePath + img;
    }
    
    // Use stable cache buster that only updates on data refresh
    const cleanUrl = url.split('?')[0];
    return `${cleanUrl}?t=${this.cacheBustTimestamp}`;
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
    },

    closeDialog() {
        this.showDialog = false;
    },

    onImageError(event) {
        console.warn('⚠️ Image load error:', event.target.src);
        event.target.src = this.defaultImage;
        event.target.onerror = null;
    },

    handleUploadClick(index, currentImage) {
        const fileInput = this.fileInputRefs[index];
        if (fileInput) {
            fileInput.click();
        }
        this.imgNumber = currentImage.split("_").pop().match(/(\d+)/)?.[1] || (index + 1);
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

    findNextAvailableImageNumber() {
        const usedNumbers = this.extractImageNumbers(this.localImageList);

        for (let i = 1; i <= this.maxImages; i++) {
            if (!usedNumbers.includes(i)) {
                return i;
            }
        }

        return null;
    },

    confirmDeleteImage(index, currentImage) {
        // Extract image number from the URL
        const urlWithoutQuery = currentImage.split('?')[0];
        this.imgNumber = urlWithoutQuery.split("_").pop().match(/(\d+)/)?.[1] || (index + 1);

        Swal.fire({
            title: "Delete Image?",
            text: `Are you sure you want to delete image ${this.imgNumber}? This action cannot be undone.`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#ef4444",
            cancelButtonColor: "#6b7280",
            confirmButtonText: "Yes, delete it!",
            cancelButtonText: "Cancel",
            reverseButtons: true,
        }).then((result) => {
            if (result.isConfirmed) {
                this.handleDeleteImage(index);
            }
        });
    },

    addCacheBuster(url, timestamp = null) {
        if (!url) return url;

        const bust = timestamp || Date.now();
        const separator = url.includes("?") ? "&" : "?";
        const cleanUrl = url.replace(/[?&](t|v|_)=\d+/g, "");

        return `${cleanUrl}${separator}t=${bust}`;
    },

    isValidImage(path) {
        if (!path) return false;
        if (typeof path !== 'string') return false;
        if (path === 'NULL' || path === 'null') return false;
        if (path.trim() === '') return false;
        return true;
    },

   buildImageListFromProduct(product) {
    const timestamp = Date.now();
    const images = [];
    const basePath = `/images/product_images/${product.company || this.company}/`;

    console.log(`🔨 Building ${this.imageType} images from product:`, product.ProductID);
    console.log(`📁 Base path:`, basePath);
    console.log(`📦 Full product object:`, JSON.stringify(product, null, 2));
    console.log(`🔍 Product.capturedImages:`, product.capturedImages);

    switch (this.imageType) {
        case "captured":
            if (product.capturedImages) {
                console.log(`✅ capturedImages exists, checking slots...`);
                for (let i = 1; i <= this.maxImages; i++) {
                    const imgKey = `capturedimg${i}`;
                    const filename = product.capturedImages[imgKey];
                    
                    console.log(`  Slot ${i} (${imgKey}):`, filename);
                    
                    if (this.isValidImage(filename)) {
                        const path = basePath + filename;
                        const cachedPath = this.addCacheBuster(path, timestamp + i);
                        images.push(cachedPath);
                        console.log(`    ✅ Added:`, cachedPath);
                    } else {
                        console.log(`    ❌ Invalid or empty`);
                    }
                }
            } else {
                console.log(`⚠️ No capturedImages object, trying fallback...`);
                // Fallback to regular images
                for (let i = 1; i <= 15; i++) {
                    const imgKey = `img${i}`;
                    const filename = product[imgKey];
                    console.log(`  Fallback slot ${i} (${imgKey}):`, filename);
                    
                    if (this.isValidImage(filename)) {
                        const path = this.basePath + filename;
                        images.push(this.addCacheBuster(path, timestamp + i));
                        console.log(`    ✅ Added fallback`);
                    }
                }
            }
            break;

        case "serial":
        case "tracking":
            console.log(`🔍 Looking for ${this.imageType} images...`);
            for (let i = 1; i <= this.maxImages; i++) {
                const imgKey = `${this.imageType}img${i}`;
                let imageFilename = null;

                if (product.capturedImages && product.capturedImages[imgKey]) {
                    imageFilename = product.capturedImages[imgKey];
                    console.log(`  Found in capturedImages: ${imgKey}:`, imageFilename);
                } else if (product[imgKey]) {
                    imageFilename = product[imgKey];
                    console.log(`  Found in root: ${imgKey}:`, imageFilename);
                }

                if (this.isValidImage(imageFilename)) {
                    const path = basePath + imageFilename;
                    const cachedPath = this.addCacheBuster(path, timestamp + i);
                    images.push(cachedPath);
                    console.log(`    ✅ Added:`, cachedPath);
                }
            }
            break;
    }

    console.log(`📊 Final result - Built ${images.length} images:`, images);
    return images;
},

async handleFileChange(event, index) {
    try {
        const file = event.target.files[0];
        if (!file) return;

        this.uploadingIndex = index;

        const formData = new FormData();
        formData.append("image", file);
        formData.append("productId", this.productId);
        formData.append("capturedImgCount", this.imgNumber);
        formData.append("imageType", this.imageType);

        console.log('📤 Uploading replacement image:', {
            productId: this.productId,
            imgNumber: this.imgNumber,
            imageType: this.imageType,
            index: index
        });

        const response = await axios.post(
            "/api/houseage/upload-image",
            formData,
            {
                headers: { "Content-Type": "multipart/form-data" },
                withCredentials: true,
            }
        );

        console.log('✅ Full response:', response);
        console.log('✅ Response data:', response.data);

        if (response.data.success) {
            this.cacheBustTimestamp = Date.now();
            
            // Vue 3: Direct assignment works
            this.localImageList[index] = response.data.filename;
            
            console.log('✅ Updated localImageList:', this.localImageList);
            
            await Swal.fire({
                title: "Upload Success",
                text: response.data.message || "Image uploaded successfully",
                icon: "success",
                timer: 2000,
                showConfirmButton: false,
            });

            try {
                this.$emit("request-refresh");
            } catch (refreshError) {
                console.warn('⚠️ Refresh error (non-critical):', refreshError);
            }
        } else {
            throw new Error(response.data.message || 'Upload failed');
        }
    } catch (error) {
        console.error("❌ Upload error:", error);
        console.error("❌ Error response:", error.response?.data);
        await Swal.fire({
            title: "Error",
            text: error.response?.data?.message || error.message || "Failed to upload image",
            icon: "error",
            confirmButtonColor: "#ef4444",
        });
    } finally {
        event.target.value = "";
        this.uploadingIndex = null;
    }
},
async handleAddImageChange(event) {
    try {
        const file = event.target.files[0];
        if (!file) return;

        const nextImageNumber = this.findNextAvailableImageNumber();

        if (nextImageNumber === null) {
            await Swal.fire({
                title: "Limit Reached",
                text: `Maximum ${this.maxImages} images allowed`,
                icon: "warning",
                confirmButtonColor: "#f59e0b",
            });
            return;
        }

        this.uploadingIndex = this.localImageList.length;

        const formData = new FormData();
        formData.append("image", file);
        formData.append("productId", this.productId);
        formData.append("capturedImgCount", nextImageNumber);
        formData.append("imageType", this.imageType);

        const response = await axios.post(
            "/api/houseage/upload-image",
            formData,
            {
                headers: { "Content-Type": "multipart/form-data" },
                withCredentials: true,
            }
        );

        console.log('✅ Add response:', response.data);
        if (response.data.success) {
            this.cacheBustTimestamp = Date.now();
            this.localImageList.push(response.data.filename)
            await Swal.fire({
                title: "Upload Success",
                text: `Image added to slot ${nextImageNumber}`,
                icon: "success",
                timer: 2000,
                showConfirmButton: false,
            });

            try {
                this.$emit("request-refresh");
            } catch (refreshError) {
                console.warn('⚠️ Refresh error (non-critical):', refreshError);
            }
        } else {
            throw new Error(response.data.message || 'Upload failed');
        }
    } catch (error) {
        console.error("❌ Add error:", error);
        await Swal.fire({
            title: "Error",
            text: error.response?.data?.message || "Failed to add image",
            icon: "error",
            confirmButtonColor: "#ef4444",
        });
    } finally {
        event.target.value = "";
        this.uploadingIndex = null;
    }
},
async handleDeleteImage(index) {
    try {
        this.deletingIndex = index;

        const response = await axios.post(
            "/api/houseage/delete-image",
            {
                productId: String(this.productId),
                capturedImgCount: this.imgNumber,
                imageType: this.imageType,
            },
            { withCredentials: true }
        );

        console.log('✅ Delete response:', response.data);

        if (response.data.success) {
            // Remove from localImageList so UI updates immediately
            this.localImageList.splice(index, 1);

            // Optional: reset active index if needed
            if (this.localActiveIndex >= this.localImageList.length) {
                this.localActiveIndex = Math.max(0, this.localImageList.length - 1);
            }

            // Update cache buster
            this.cacheBustTimestamp = Date.now();

            await Swal.fire({
                title: "Deleted!",
                text: `Image ${this.imgNumber} has been deleted.`,
                icon: "success",
                timer: 2000,
                showConfirmButton: false,
            });

            // Notify parent (optional)
            this.$emit("request-refresh", {
                ProductID: this.productId,
                imageList: this.localImageList,
            });

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
        this.deletingIndex = null;
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

/* Label */
.gallery-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
}

/* Gallery Container */
.gallery-container {
    position: relative;
    border-radius: 8px;
    overflow: visible;
}

/* Main Image Display */
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

.empty-state:hover {
    transform: scale(1.05);
      transition: transform 0.3s ease;
      cursor: pointer;
}

/* Image Counter Badge */
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

/* Thumbnail Strip - Desktop: Absolute overlay */
.thumbnail-strip {
    position: absolute;
    bottom: 0;
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
    -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
}

/* Mobile thumbnails - Below image, always visible */
.thumbnail-strip.mobile-thumbnails {
    position: relative;
    background: transparent;
    padding: 0.75rem 0;
    /* margin-top: 0.2rem; */
    overflow-x: auto;
    overflow-y: hidden;
    margin: 0.5rem auto 0 auto;
    width: 95%;
    -webkit-overflow-scrolling: touch;
    scroll-behavior: smooth;
    /* Show scrollbar on mobile */
    scrollbar-width: auto;
    scrollbar-color: rgba(0, 0, 0, 0.3) #f3f4f6;
}

/* Desktop scrollbar */
.thumbnail-strip::-webkit-scrollbar {
    height: 6px;
}

.thumbnail-strip::-webkit-scrollbar-track {
    background: transparent;
}

.thumbnail-strip::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 3px;
}

.thumbnail-strip::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}

/* Mobile: Visible scrollbar for thumbnails */
.thumbnail-strip.mobile-thumbnails::-webkit-scrollbar {
    height: 8px;
}

.thumbnail-strip.mobile-thumbnails::-webkit-scrollbar-track {
    background: #f3f4f6;
    border-radius: 4px;
}

.thumbnail-strip.mobile-thumbnails::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.3);
    border-radius: 4px;
}

.thumbnail-strip.mobile-thumbnails::-webkit-scrollbar-thumb:active {
    background: rgba(0, 0, 0, 0.5);
}

/* Slide Up Animation - Only for desktop */
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

/* Thumbnail Item */
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

/* Mobile thumbnail styling */
.mobile-thumbnails .thumbnail-item {
    border-color: #e5e7eb;
    width: 70px;
    height: 52px;
}

.mobile-thumbnails .thumbnail-item:hover {
    transform: none;
    border-color: rgba(0, 0, 0, 0.3);
}

.mobile-thumbnails .thumbnail-item.selected {
    border-color: #f59e0b;
    box-shadow: 0 0 0 2px #f59e0b;
}

/* Empty State */
.empty-state {
    background: #f9fafb;
    border: 2px dashed #d1d5db;
    border-radius: 8px;
    padding: 3rem 1rem;
    text-align: center;
    color: #9ca3af;
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

/* Image Management Dialog */
.image-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.5rem;
    padding: 1rem 0;
    max-height: 70vh;
    overflow-y: auto;
}

/* Image Card */
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
}

.card-image.processing {
    opacity: 0.5;
}

/* Delete Button */
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

.delete-btn i {
    font-size: 1rem;
}

/* Processing Overlay */
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

/* Add Card */
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

.add-card-content:hover {
    border-color: #3b82f6;
    background: #eff6ff;
}

.add-card-content i {
    font-size: 2.5rem;
    color: #9ca3af;
}

.add-card-content:hover i {
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

/* Responsive - Mobile */
@media (max-width: 768px) {
    .gallery-container {
        overflow: visible;
    }

    .main-image-display {
        height: 250px;
    }

    .thumbnail-strip {
        padding: 0.75rem 0;
    }

    .thumbnail-item {
        width: 70px;
        height: 52px;
        min-width: 70px; /* Prevent shrinking */
    }

    /* Disable slide animation on mobile */
    .slide-up-enter-active,
    .slide-up-leave-active {
        transition: none;
    }

    .slide-up-enter-from,
    .slide-up-leave-to {
        transform: none;
        opacity: 1;
    }

    .image-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 1rem;
    }
}

@media (max-width: 480px) {
    .main-image-display {
        height: 200px;
        width: 100%;
    }

    .thumbnail-item {
        width: 60px;
        height: 45px;
        min-width: 60px; /* Prevent shrinking */
    }
    
    .mobile-thumbnails .thumbnail-item {
        width: 60px;
        height: 45px;
        min-width: 60px;
    }
}
</style>