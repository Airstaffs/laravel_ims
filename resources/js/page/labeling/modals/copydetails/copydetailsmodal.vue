<template>
    <div v-if="showModal" class="modal copy-details-modal">
        <div class="modal-overlay" @click="closeModal"></div>
        
        <div class="modal-content">
            <div class="modal-header">
                <h2>Product Details</h2>
                <button class="btn btn-modal-close" @click="closeModal">
                    &times;
                </button>
            </div>

            <div class="modal-body">
                <div class="copy-details-container">
                    <!-- Product Title and RT Counter Only -->
                    <div class="product-header">
                        <h3 class="product-title">{{ detailsData.itemName }}</h3>
                        <p class="rt-counter">{{ detailsData.rtAr }}</p>
                    </div>

                    <!-- Formatted text preview (copyable content) -->
                    <div class="formatted-text-section">
                        <h4>Product Details:</h4>
                        <div class="formatted-text-container">
                            <pre class="formatted-text">{{ formattedDetailsText }}</pre>
                        </div>
                    </div>

                    <!-- Copy button -->
                    <div class="copy-actions">
                        <button 
                            @click="copyDetailsToClipboard" 
                            class="btn btn-copy-primary"
                            :disabled="isCopying"
                        >
                            <i class="bi bi-clipboard-check" v-if="copySuccess"></i>
                            <i class="bi bi-clipboard" v-else></i>
                            {{ copySuccess ? 'Copied!' : (isCopying ? 'Copying...' : 'Copy Details') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import Swal from "sweetalert2";

export default {
    name: 'CopyDetailsModal',
    props: {
        showModal: {
            type: Boolean,
            default: false
        },
        itemData: {
            type: Object,
            default: () => ({})
        }
    },
    data() {
        return {
            isCopying: false,
            copySuccess: false
        };
    },
    computed: {
        detailsData() {
            if (!this.itemData) {
                return {
                    orderId: 'N/A',
                    trackingNumber: 'N/A',
                    itemName: 'N/A',
                    rtAr: 'N/A',
                    pcn: 'N/A',
                    basketNumber: 'N/A',
                    asin: 'N/A'
                };
            }

            return {
                orderId: this.itemData.rtid || this.itemData.OrderNumber || 'N/A',
                trackingNumber: this.itemData.trackingnumber || this.itemData.TN || 'N/A',
                itemName: this.itemData.ProductTitle || this.itemData.AStitle || 'N/A',
                rtAr: `RT ${this.itemData.rtcounter || 'N/A'}`,
                pcn: this.itemData.PCN || 'N/A',
                basketNumber: this.itemData.basketnumber || this.itemData.BasketNumber || 'N/A',
                asin: this.itemData.ASIN || this.itemData.ASINviewer || 'N/A'
            };
        },
        formattedDetailsText() {
            return `Order ID:    ${this.detailsData.orderId}
TN#:         ${this.detailsData.trackingNumber}
Item Name:   ${this.detailsData.itemName}
RT/AR:       ${this.detailsData.rtAr}
PCN#:        ${this.detailsData.pcn}
BKT#:        ${this.detailsData.basketNumber}
ASIN:        ${this.detailsData.asin}`;
        }
    },
    methods: {
        closeModal() {
            this.copySuccess = false;
            this.isCopying = false;
            this.$emit('close');
        },
        
        async copyDetailsToClipboard() {
            this.isCopying = true;
            
            try {
                // Check if clipboard API is available
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(this.formattedDetailsText);
                } else {
                    // Fallback for older browsers or non-HTTPS
                    this.fallbackCopyToClipboard(this.formattedDetailsText);
                }
                
                this.copySuccess = true;
                console.log('Details copied to clipboard successfully');
                
                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'Copied!',
                    text: 'Product details have been copied to clipboard.',
                    timer: 2000,
                    showConfirmButton: false
                });
                
                // Reset success state after 3 seconds
                setTimeout(() => {
                    this.copySuccess = false;
                }, 3000);
                
            } catch (error) {
                console.error('Failed to copy details to clipboard:', error);
                
                // Show error message
                Swal.fire({
                    icon: 'error',
                    title: 'Copy Failed',
                    text: 'Failed to copy details to clipboard. Please try again.',
                });
            } finally {
                this.isCopying = false;
            }
        },
        
        fallbackCopyToClipboard(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                console.log('Fallback copy successful');
            } catch (error) {
                console.error('Fallback copy failed:', error);
                throw error;
            } finally {
                document.body.removeChild(textArea);
            }
        }
    },
    watch: {
        showModal(newVal) {
            if (newVal) {
                this.copySuccess = false;
                this.isCopying = false;
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = 'auto';
            }
        }
    },
    beforeDestroy() {
        document.body.style.overflow = 'auto';
    }
};
</script>

<style scoped>
/* Copy Details Modal Styling */
.copy-details-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
}

.modal-content {
    position: relative;
    background: white;
    border-radius: 12px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 25px rgba(0, 0, 0, 0.1);
    z-index: 1000;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #e9ecef;
    background: #f8f9fa;
    border-radius: 12px 12px 0 0;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.5rem;
    color: #495057;
    font-weight: 600;
}

.btn-modal-close {
    background: none;
    border: none;
    font-size: 1.8rem;
    color: #6c757d;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s;
}

.btn-modal-close:hover {
    background-color: #e9ecef;
    color: #495057;
}

.modal-body {
    padding: 0;
}

.copy-details-container {
    padding: 24px;
}

.product-header {
    text-align: center;
    padding: 20px;
    margin-bottom: 24px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid #dee2e6;
    border-radius: 8px;
}

.product-header .product-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #212529;
    margin: 0 0 8px 0;
    line-height: 1.3;
}

.product-header .rt-counter {
    font-size: 1rem;
    font-weight: 500;
    color: #6c757d;
    margin: 0;
    font-family: 'Courier New', monospace;
    background: white;
    padding: 4px 12px;
    border-radius: 4px;
    border: 1px solid #ced4da;
    display: inline-block;
}

.details-display {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 24px;
}

.detail-section {
    display: grid;
    gap: 12px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #e9ecef;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 600;
    color: #495057;
    min-width: 100px;
    font-size: 0.95rem;
}

.detail-value {
    color: #212529;
    font-family: 'Courier New', monospace;
    background: white;
    padding: 6px 12px;
    border-radius: 4px;
    border: 1px solid #ced4da;
    flex: 1;
    margin-left: 16px;
    text-align: left;
    font-size: 0.9rem;
    font-weight: 500;
}

.formatted-text-section h4 {
    color: #495057;
    margin-bottom: 12px;
    font-size: 1.1rem;
    font-weight: 600;
}

.formatted-text-container {
    background: #ffffff;
    border: 2px solid #007bff;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 24px;
    position: relative;
}

.formatted-text-container::before {
    content: 'Preview of copied text:';
    position: absolute;
    top: -10px;
    left: 12px;
    background: white;
    padding: 0 8px;
    font-size: 0.8rem;
    color: #007bff;
    font-weight: 600;
}

.formatted-text {
    font-family: 'Courier New', monospace;
    font-size: 14px;
    line-height: 1.5;
    margin: 0;
    color: #212529;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.copy-actions {
    text-align: center;
}

.btn-copy-primary {
    background-color: #28a745;
    color: white;
    border: 2px solid #28a745;
    padding: 14px 28px;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    min-width: 180px;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-copy-primary:hover:not(:disabled) {
    background-color: #218838;
    border-color: #1e7e34;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(40, 167, 69, 0.3);
}

.btn-copy-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.btn-copy-primary i {
    font-size: 1.1rem;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .modal-content {
        width: 95%;
        max-height: 85vh;
        margin: 20px;
    }
    
    .copy-details-container {
        padding: 16px;
    }
    
    .modal-header {
        padding: 16px 20px;
    }
    
    .modal-header h2 {
        font-size: 1.3rem;
    }
    
    .detail-row {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }
    
    .detail-value {
        margin-left: 0;
        margin-top: 0;
    }
    
    .formatted-text {
        font-size: 12px;
        line-height: 1.4;
    }
    
    .btn-copy-primary {
        padding: 12px 20px;
        min-width: 160px;
        font-size: 0.9rem;
    }
}

/* Animation */
.copy-details-modal {
    animation: fadeIn 0.3s ease-out;
}

.modal-content {
    animation: slideIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-50px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
</style>