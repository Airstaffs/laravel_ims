<template>
    <Dialog v-model:visible="localVisible" modal :header="dialogTitle" style="width: 50rem; max-height: 90vh;" @hide="handleClose"
        :pt="{ root: { class: 'mobile-fullscreen-dialog' } }">
        
        <div class="condition-checklist">
            <!-- Loading State -->
            <div v-if="loading" class="text-center py-4">
                <i class="pi pi-spin pi-spinner" style="font-size: 2rem"></i>
                <p class="mt-2">Loading condition data...</p>
            </div>

            <!-- Checklist Form -->
            <div v-else>
                <!-- Existing Condition Alert -->
                <Message v-if="hasExisting" severity="info" :closable="false" class="mb-3">
                    Last inspection: {{ formatDate(conditionData.inspected_at) }} by {{ conditionData.inspected_by }}
                </Message>

                <!-- Physical Condition Section -->
                <fieldset class="condition-section">
                    <legend class="text-primary fw-bold">Physical Condition</legend>
                    <div class="condition-checks">
                        <div class="p-field-checkbox">
                            <Checkbox v-model="conditionData.physical_damage" :binary="true" inputId="physical_damage" />
                            <label for="physical_damage">Physical Damage Present</label>
                        </div>
                        <div class="p-field-checkbox">
                            <Checkbox v-model="conditionData.scratches" :binary="true" inputId="scratches" />
                            <label for="scratches">Scratches</label>
                        </div>
                        <div class="p-field-checkbox">
                            <Checkbox v-model="conditionData.dents" :binary="true" inputId="dents" />
                            <label for="dents">Dents</label>
                        </div>
                        <div class="p-field-checkbox">
                            <Checkbox v-model="conditionData.cracks" :binary="true" inputId="cracks" />
                            <label for="cracks">Cracks</label>
                        </div>
                    </div>
                </fieldset>

                <!-- Functional Tests Section -->
                <fieldset class="condition-section">
                    <legend class="text-primary fw-bold">Functional Tests</legend>
                    <div class="condition-checks">
                        <div class="p-field-checkbox">
                            <Checkbox v-model="conditionData.powers_on" :binary="true" inputId="powers_on" />
                            <label for="powers_on">Powers On</label>
                        </div>
                        <div class="p-field-checkbox">
                            <Checkbox v-model="conditionData.all_functions_work" :binary="true"
                                inputId="all_functions_work" />
                            <label for="all_functions_work">All Functions Work</label>
                        </div>
                        <div class="p-field-checkbox">
                            <Checkbox v-model="conditionData.connectivity_tested" :binary="true"
                                inputId="connectivity_tested" />
                            <label for="connectivity_tested">Connectivity Tested</label>
                        </div>
                        <div class="p-field-checkbox">
                            <Checkbox v-model="conditionData.display_condition" :binary="true"
                                inputId="display_condition" />
                            <label for="display_condition">Display Good Condition</label>
                        </div>
                    </div>
                </fieldset>

                <!-- Inspector Notes -->
                <div class="notes-section mt-4">
                    <label for="notes" class="fw-bold mb-2 d-block">Inspector Notes</label>
                    <Textarea v-model="conditionData.notes" rows="4" class="w-100" 
                        placeholder="Enter any additional observations, issues found, or recommendations..." />
                </div>

                <!-- Condition Score Display -->
                <Card v-if="conditionScore !== null" class="mt-3">
                    <template #content>
                        <div class="text-center">
                            <span class="fw-bold">Condition Score: </span>
                            <span :class="getScoreClass(conditionScore)" class="fs-5 fw-bold">
                                {{ conditionScore }}%
                            </span>
                            <p class="text-muted small mt-2 mb-0">
                                Based on physical condition and functionality tests
                            </p>
                        </div>
                    </template>
                </Card>
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" icon="pi pi-times" @click="handleClose" severity="secondary" />
            <Button label="Save Received Condition" icon="pi pi-check" @click="saveCondition" :loading="saving"
                severity="success" />
        </template>
    </Dialog>
</template>

<script>
import { Dialog, Button, Checkbox, Textarea, Message, Card } from 'primevue';
import axios from 'axios';

const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: 'ReceivedConditionModal',
    components: {
        Dialog,
        Button,
        Checkbox,
        Textarea,
        Message,
        Card
    },
    props: {
        visible: {
            type: Boolean,
            required: true
        },
        item: {
            type: Object,
            required: true
        }
    },
    emits: ['update:visible', 'saved'],
    data() {
        return {
            localVisible: this.visible,
            loading: false,
            saving: false,
            hasExisting: false,
            conditionScore: null,
            conditionData: {
                item_number: '',
                product_id: '',
                condition_type: 'receive',
                physical_damage: false,
                scratches: false,
                dents: false,
                cracks: false,
                original_packaging: false,
                packaging_damaged: false,
                missing_accessories: false,
                powers_on: false,
                all_functions_work: false,
                connectivity_tested: false,
                display_condition: false,
                manual_included: false,
                cables_included: false,
                warranty_card: false,
                notes: '',
                inspected_by: '',
                inspected_at: null
            }
        };
    },
    computed: {
        dialogTitle() {
            return `Received Condition Inspection - ${this.item?.ProductTitle || 'Item'}`;
        }
    },
    watch: {
        visible(newVal) {
            this.localVisible = newVal;
            if (newVal) {
                this.loadConditionData();
            }
        },
        localVisible(newVal) {
            this.$emit('update:visible', newVal);
        }
    },
    methods: {
        async loadConditionData() {
            if (!this.item?.itemnumber) return;

            this.loading = true;
            try {
                const response = await axios.get(
                    `${API_BASE_URL}/api/testing/condition/${this.item.itemnumber}`,
                    {
                        params: { type: 'receive' }
                    }
                );

                if (response.data.success && response.data.condition) {
                    this.conditionData = { ...this.conditionData, ...response.data.condition };
                    this.hasExisting = response.data.has_existing;
                    this.conditionScore = response.data.condition.condition_score || null;
                } else {
                    // Initialize with item data for new condition
                    this.conditionData.item_number = this.item.itemnumber;
                    this.conditionData.product_id = this.item.ProductID;
                    this.conditionData.condition_type = 'receive';
                    this.hasExisting = false;
                }
            } catch (error) {
                console.error('Failed to load condition data:', error);
                if (this.$toast) {
                    this.$toast.add({
                        severity: 'error',
                        summary: 'Error',
                        detail: 'Failed to load condition data',
                        life: 3000
                    });
                }
            } finally {
                this.loading = false;
            }
        },

        async saveCondition() {
            this.saving = true;
            try {
                // FIXED: Removed overall_condition from the data being sent
                const dataToSend = {
                    item_number: this.conditionData.item_number,
                    product_id: String(this.conditionData.product_id),
                    condition_type: this.conditionData.condition_type,
                    physical_damage: this.conditionData.physical_damage,
                    scratches: this.conditionData.scratches,
                    dents: this.conditionData.dents,
                    cracks: this.conditionData.cracks,
                    powers_on: this.conditionData.powers_on,
                    all_functions_work: this.conditionData.all_functions_work,
                    connectivity_tested: this.conditionData.connectivity_tested,
                    display_condition: this.conditionData.display_condition,
                    notes: this.conditionData.notes
                };

                console.log('Sending condition data:', dataToSend);
                
                const response = await axios.post(
                    `${API_BASE_URL}/api/testing/condition`,
                    dataToSend
                );

                if (response.data.success) {
                    this.conditionScore = response.data.score;
                    this.$emit('saved', response.data.condition);
                    this.handleClose();
                }
            } catch (error) {
                console.error('Failed to save condition:', error);
                console.error('Error response:', error.response?.data);
                
                const errorMessage = error.response?.data?.errors 
                    ? Object.values(error.response.data.errors).flat().join(', ')
                    : error.response?.data?.message || 'Failed to save condition';
                
                if (typeof this.$swal !== 'undefined') {
                    this.$swal({
                        icon: 'error',
                        title: 'Error',
                        text: errorMessage,
                        confirmButtonText: 'OK'
                    });
                } else if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: errorMessage,
                        confirmButtonText: 'OK'
                    });
                } else {
                    alert('Error: ' + errorMessage);
                }
            } finally {
                this.saving = false;
            }
        },

        handleClose() {
            this.localVisible = false;
            this.resetForm();
        },

        resetForm() {
            this.conditionData = {
                item_number: '',
                product_id: '',
                condition_type: 'receive',
                physical_damage: false,
                scratches: false,
                dents: false,
                cracks: false,
                original_packaging: false,
                packaging_damaged: false,
                missing_accessories: false,
                powers_on: false,
                all_functions_work: false,
                connectivity_tested: false,
                display_condition: false,
                manual_included: false,
                cables_included: false,
                warranty_card: false,
                notes: '',
                inspected_by: '',
                inspected_at: null
            };
            this.hasExisting = false;
            this.conditionScore = null;
        },

        formatDate(date) {
            if (!date) return 'N/A';
            return new Date(date).toLocaleString();
        },

        getScoreClass(score) {
            if (score >= 90) return 'text-success';
            if (score >= 70) return 'text-info';
            if (score >= 50) return 'text-warning';
            return 'text-danger';
        }
    }
};
</script>

<style scoped>
.condition-checklist {
    /* Let Dialog handle scrolling */
}

.condition-section {
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1rem;
}

.condition-section legend {
    padding: 0 0.5rem;
    width: auto;
    font-size: 1rem;
    margin-bottom: 0.5rem;
}

.condition-checks {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.75rem;
}

.p-field-checkbox {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.p-field-checkbox label {
    margin-bottom: 0;
    cursor: pointer;
}

.notes-section {
    border-top: 1px solid #dee2e6;
    padding-top: 1rem;
}

@media (max-width: 768px) {
    .condition-checks {
        grid-template-columns: 1fr;
    }
}
</style>