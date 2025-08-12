//splitting.js
import Swal from "sweetalert2";
const API_BASE_URL = import.meta.env.VITE_API_URL;

// Export the component object using ES6 export syntax
export default {
    name: "SplittingModal",
    props: {
        showModal: {
            type: Boolean,
            default: false
        },
        splitItem: {
            type: Object,
            default: null
        }
    },
    data() {
        return {
            isSplitting: false,
        };
    },
    methods: {
        /**
         * Get total price from all three price fields combined
         */
        getTotalPrice() {
            if (!this.splitItem) return 0;
            
            console.log('🔍 getTotalPrice called for item:', {
                ProductID: this.splitItem.ProductID,
                price: this.splitItem.price,
                priceshipping: this.splitItem.priceshipping,
                tax: this.splitItem.tax
            });
            
            const price = parseFloat(this.splitItem.price) || 0;
            const priceshipping = parseFloat(this.splitItem.priceshipping) || 0;
            const tax = parseFloat(this.splitItem.tax) || 0;
            const total = price + priceshipping + tax;
            
            console.log('🔍 Combined total price from all fields:', {
                price: price,
                priceshipping: priceshipping,
                tax: tax,
                total: total
            });
            
            return total;
        },

        /**
         * Get unit price for regular price field
         */
        getUnitPrice() {
            if (!this.splitItem) return 0;
            
            const quantity = parseInt(this.splitItem.quantity) || 1;
            const price = parseFloat(this.splitItem.price) || 0;
            
            return quantity > 0 ? price / quantity : 0;
        },

        /**
         * Get unit price for shipping price field
         */
        getUnitPriceShipping() {
            if (!this.splitItem) return 0;
            
            const quantity = parseInt(this.splitItem.quantity) || 1;
            const priceshipping = parseFloat(this.splitItem.priceshipping) || 0;
            
            return quantity > 0 ? priceshipping / quantity : 0;
        },

        /**
         * Get unit price for tax field
         */
        getUnitTax() {
            if (!this.splitItem) return 0;
            
            const quantity = parseInt(this.splitItem.quantity) || 1;
            const tax = parseFloat(this.splitItem.tax) || 0;
            
            return quantity > 0 ? tax / quantity : 0;
        },

        /**
         * Calculate combined unit price from all three fields
         */
        calculateUnitPrice() {
            if (!this.splitItem) return 0;
            
            console.log('🔍 calculateUnitPrice called for item:', {
                ProductID: this.splitItem.ProductID,
                quantity: this.splitItem.quantity,
                price: this.splitItem.price,
                priceshipping: this.splitItem.priceshipping,
                tax: this.splitItem.tax
            });
            
            const quantity = parseInt(this.splitItem.quantity) || 1;
            const totalPrice = this.getTotalPrice();
            
            const result = quantity > 0 ? totalPrice / quantity : 0;
            console.log('🔍 Combined unit price calculated:', result, 'from total:', totalPrice, 'quantity:', quantity);
            
            return result;
        },

        /**
         * Get price breakdown for display - now includes tax
         */
        getPriceBreakdown() {
            if (!this.splitItem) return { hasPrice: false, hasPriceShipping: false, hasTax: false };
            
            const price = parseFloat(this.splitItem.price) || 0;
            const priceshipping = parseFloat(this.splitItem.priceshipping) || 0;
            const tax = parseFloat(this.splitItem.tax) || 0;
            const quantity = parseInt(this.splitItem.quantity) || 1;
            
            return {
                hasPrice: price > 0,
                hasPriceShipping: priceshipping > 0,
                hasTax: tax > 0,
                originalPrice: price,
                originalPriceShipping: priceshipping,
                originalTax: tax,
                unitPrice: quantity > 0 ? price / quantity : 0,
                unitPriceShipping: quantity > 0 ? priceshipping / quantity : 0,
                unitTax: quantity > 0 ? tax / quantity : 0,
                totalOriginal: price + priceshipping + tax,
                totalUnit: quantity > 0 ? (price + priceshipping + tax) / quantity : 0
            };
        },

        /**
         * Close the modal and emit close event
         */
        closeModal(event) {
            console.log('🚀 CLOSE SPLIT MODAL CALLED');
            
            // Prevent any event bubbling
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            if (this.isSplitting) {
                // Don't allow closing while splitting
                console.log('⚠️ Cannot close modal while splitting is in progress');
                return;
            }
            
            // Reset modal state and emit close
            this.resetModalState();
        },

        /**
         * Reset modal state completely
         */
        resetModalState() {
            console.log('🔄 Resetting modal state');
            
            // Clear splitting state
            this.isSplitting = false;
            
            // Emit close event to parent
            this.$emit('close');
            
            // Re-enable body scrolling
            document.body.style.overflow = 'auto';
            console.log('🔍 Body overflow restored to auto');
            console.log('🔍 Modal close event emitted to parent');
        },

        /**
         * Perform the split operation
         */
        async performSplit() {
            console.log('🚀 PERFORM SPLIT CALLED!');
            console.log('🔍 Current split item:', {
                ProductID: this.splitItem?.ProductID,
                rtcounter: this.splitItem?.rtcounter,
                quantity: this.splitItem?.quantity,
                price: this.splitItem?.price,
                priceshipping: this.splitItem?.priceshipping,
                tax: this.splitItem?.tax
            });

            if (!this.splitItem || !this.splitItem.ProductID) {
                console.log('❌ Invalid item for splitting');
                await Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Invalid item selected for splitting.',
                });
                return;
            }

            try {
                this.isSplitting = true;
                console.log('🔍 Starting split API call...');

                // Calculate all three price fields
                const price = parseFloat(this.splitItem.price) || 0;
                const priceshipping = parseFloat(this.splitItem.priceshipping) || 0;
                const tax = parseFloat(this.splitItem.tax) || 0;
                const totalPrice = price + priceshipping + tax;

                const payload = {
                    product_id: this.splitItem.ProductID,
                    rt_counter: this.splitItem.rtcounter,
                    quantity: parseInt(this.splitItem.quantity),
                    // Send all three price fields separately so backend can split them individually
                    price: price,
                    priceshipping: priceshipping,
                    tax: tax,
                    total_price: totalPrice, // Also send combined total for reference
                };
                
                console.log('🔍 Split payload:', payload);

                // Get CSRF token
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                console.log('🔍 CSRF Token found:', !!csrfToken);

                const response = await axios.post(
                    `${API_BASE_URL}/api/labeling/split-item`,
                    payload,
                    {
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken || '',
                            'Accept': 'application/json',
                        },
                        withCredentials: true,
                    }
                );

                console.log('🔍 Split API response status:', response.status);
                console.log('🔍 Response data:', response.data);

                if (response.data && response.data.success) {
                    console.log('✅ Split successful!');
                    const data = response.data.data || {};
                    const breakdown = this.getPriceBreakdown();
                    
                    let successMessage = `
                        <div style="text-align: left;">
                            <p><strong>Item successfully split into individual units!</strong></p>
                            <ul style="margin-top: 10px;">
                                <li><strong>Original RT:</strong> ${this.splitItem.rtcounter} (now quantity = 1)</li>
                                <li><strong>New items created:</strong> ${data.new_items_count || (parseInt(this.splitItem.quantity) - 1)}</li>
                                <li><strong>Total items after split:</strong> ${data.total_items_after_split || this.splitItem.quantity}</li>
                            </ul>
                            <div style="margin-top: 15px;">
                                <strong>Price Breakdown per Unit:</strong>
                                <ul>`;

                    if (breakdown.hasPrice) {
                        successMessage += `<li>Price: ${breakdown.unitPrice.toFixed(2)} (was ${breakdown.originalPrice.toFixed(2)})</li>`;
                    }
                    if (breakdown.hasPriceShipping) {
                        successMessage += `<li>Shipping Price: ${breakdown.unitPriceShipping.toFixed(2)} (was ${breakdown.originalPriceShipping.toFixed(2)})</li>`;
                    }
                    if (breakdown.hasTax) {
                        successMessage += `<li>Tax: ${breakdown.unitTax.toFixed(2)} (was ${breakdown.originalTax.toFixed(2)})</li>`;
                    }
                    if (breakdown.hasPrice || breakdown.hasPriceShipping || breakdown.hasTax) {
                        successMessage += `<li><strong>Combined Unit Price: ${breakdown.totalUnit.toFixed(2)}</strong></li>`;
                    }

                    successMessage += `</ul></div>`;

                    if (data.new_rt_counters && data.new_rt_counters.length > 0) {
                        successMessage += `<p><strong>New RT Counters:</strong> ${data.new_rt_counters.join(', ')}</p>`;
                    }

                    if (totalPrice === 0) {
                        successMessage += `<p class="text-warning"><strong>⚠️ Note:</strong> Items were split with $0.00 price since no original prices were set.</p>`;
                    }

                    successMessage += `</div>`;
                    
                    await Swal.fire({
                        icon: 'success',
                        title: 'Split Successful!',
                        html: successMessage,
                        confirmButtonText: 'OK',
                        width: 700,
                    });

                    // Close modal FIRST and reset all internal state
                    this.resetModalState();
                    
                    // Then emit success event to parent (this will refresh inventory)
                    this.$emit('split-success');
                } else {
                    console.log('❌ Split failed:', response.data);
                    const errorMessage = response.data?.message || 'Split operation failed - no success flag';
                    throw new Error(errorMessage);
                }
            } catch (error) {
                console.error('❌ Split error:', error);
                
                let errorMessage = 'Failed to split item. Please try again.';
                
                if (error.response) {
                    console.log('🔍 Error response status:', error.response.status);
                    console.log('🔍 Error response data:', error.response.data);
                    
                    if (error.response.data && error.response.data.message) {
                        errorMessage = error.response.data.message;
                    } else if (error.response.status === 422 && error.response.data.errors) {
                        // Handle validation errors
                        const errors = Object.values(error.response.data.errors).flat();
                        errorMessage = errors.join('\n');
                    } else {
                        errorMessage = `Server error (${error.response.status}): ${error.response.statusText}`;
                    }
                } else if (error.request) {
                    console.log('🔍 Network error - no response received');
                    errorMessage = 'Network error - could not reach server';
                } else if (error.message) {
                    errorMessage = error.message;
                }

                console.log('🔍 Final error message:', errorMessage);

                await Swal.fire({
                    icon: 'error',
                    title: 'Split Failed',
                    text: errorMessage,
                    confirmButtonText: 'OK'
                });
            } finally {
                this.isSplitting = false;
                console.log('🔍 Split operation finished');
            }
        },
    },
    watch: {
        showModal(newVal, oldVal) {
            console.log('🔍 showModal changed:', oldVal, '->', newVal);
            
            if (newVal) {
                // Disable body scrolling when modal opens
                document.body.style.overflow = 'hidden';
                console.log('🔍 Modal opened - body scrolling disabled');
            } else {
                // Re-enable body scrolling when modal closes
                document.body.style.overflow = 'auto';
                console.log('🔍 Modal closed - body scrolling enabled');
                
                // Ensure splitting state is cleared when modal is closed
                if (this.isSplitting) {
                    this.isSplitting = false;
                    console.log('🔍 Splitting state cleared on modal close');
                }
            }
        }
    },
    beforeDestroy() {
        // Ensure body scrolling is re-enabled when component is destroyed
        document.body.style.overflow = 'auto';
    }
};