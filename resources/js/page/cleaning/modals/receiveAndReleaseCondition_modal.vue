<template>
    <Dialog v-model:visible="localVisible" modal :header="dialogTitle" 
        style="width: 70rem; max-height: 90vh;" @hide="handleClose"
        :pt="{ root: { class: 'mobile-fullscreen-dialog' } }">
        
        <div class="dual-condition-view">
            <!-- Loading State -->
            <div v-if="loading" class="text-center py-4">
                <i class="pi pi-spin pi-spinner" style="font-size: 2rem"></i>
                <p class="mt-2">Loading condition data...</p>
            </div>

            <div v-else class="row">
                <!-- LEFT: RECEIVED CONDITION (Read-Only) -->
                <div class="col-md-6">
                    <Card class="h-100">
                        <template #title>
                            <div class="d-flex align-items-center justify-content-between">
                                <h5 class="text-success mb-0">
                                    <i class="pi pi-box me-2"></i>Received Condition
                                </h5>
                                <Tag v-if="receivedCondition" severity="success" value="Completed" />
                                <Tag v-else severity="danger" value="Not Found" />
                            </div>
                        </template>
                        <template #content>
                            <div v-if="receivedCondition">
                                <!-- Inspection Info -->
                                <div class="mb-3 p-2 bg-light rounded">
                                    <small class="text-muted">
                                        <strong>Inspected by:</strong> {{ receivedCondition.inspected_by }}<br>
                                        <strong>Date:</strong> {{ formatDate(receivedCondition.inspected_at) }}
                                    </small>
                                </div>

                                <!-- Physical Condition -->
                                <h6 class="text-primary mt-3 mb-2">Physical Condition</h6>
                                <div class="condition-checks-readonly">
                                    <div class="check-item">
                                        <i :class="receivedCondition.physical_damage ? 'pi pi-times text-danger' : 'pi pi-check text-success'"></i>
                                        <span>Physical Damage: {{ receivedCondition.physical_damage ? 'Yes' : 'No' }}</span>
                                    </div>
                                    <div class="check-item">
                                        <i :class="receivedCondition.scratches ? 'pi pi-times text-danger' : 'pi pi-check text-success'"></i>
                                        <span>Scratches: {{ receivedCondition.scratches ? 'Yes' : 'No' }}</span>
                                    </div>
                                    <div class="check-item">
                                        <i :class="receivedCondition.dents ? 'pi pi-times text-danger' : 'pi pi-check text-success'"></i>
                                        <span>Dents: {{ receivedCondition.dents ? 'Yes' : 'No' }}</span>
                                    </div>
                                    <div class="check-item">
                                        <i :class="receivedCondition.cracks ? 'pi pi-times text-danger' : 'pi pi-check text-success'"></i>
                                        <span>Cracks: {{ receivedCondition.cracks ? 'Yes' : 'No' }}</span>
                                    </div>
                                </div>

                                <!-- Functional Tests -->
                                <h6 class="text-primary mt-3 mb-2">Functional Tests</h6>
                                <div class="condition-checks-readonly">
                                    <div class="check-item">
                                        <i :class="receivedCondition.powers_on ? 'pi pi-check text-success' : 'pi pi-times text-danger'"></i>
                                        <span>Powers On: {{ receivedCondition.powers_on ? 'Yes' : 'No' }}</span>
                                    </div>
                                    <div class="check-item">
                                        <i :class="receivedCondition.all_functions_work ? 'pi pi-check text-success' : 'pi pi-times text-danger'"></i>
                                        <span>All Functions Work: {{ receivedCondition.all_functions_work ? 'Yes' : 'No' }}</span>
                                    </div>
                                    <div class="check-item">
                                        <i :class="receivedCondition.connectivity_tested ? 'pi pi-check text-success' : 'pi pi-times text-danger'"></i>
                                        <span>Connectivity Tested: {{ receivedCondition.connectivity_tested ? 'Yes' : 'No' }}</span>
                                    </div>
                                    <div class="check-item">
                                        <i :class="receivedCondition.display_condition ? 'pi pi-check text-success' : 'pi pi-times text-danger'"></i>
                                        <span>Display Condition: {{ receivedCondition.display_condition ? 'Good' : 'Issues' }}</span>
                                    </div>
                                </div>

                                <!-- Notes -->
                                <div v-if="receivedCondition.notes" class="mt-3">
                                    <h6 class="text-primary mb-2">Inspector Notes</h6>
                                    <div class="p-2 bg-light rounded">
                                        <small>{{ receivedCondition.notes }}</small>
                                    </div>
                                </div>

                                <!-- Condition Score -->
                                <div v-if="receivedCondition.condition_score !== null" class="mt-3 text-center">
                                    <strong>Condition Score: </strong>
                                    <span :class="getScoreClass(receivedCondition.condition_score)" class="fs-5 fw-bold">
                                        {{ receivedCondition.condition_score }}%
                                    </span>
                                </div>
                            </div>
                            <div v-else class="text-center text-muted py-4">
                                <i class="pi pi-exclamation-triangle" style="font-size: 2rem;"></i>
                                <p class="mt-2">No received condition found for this item.</p>
                            </div>
                        </template>
                    </Card>
                </div>

                <!-- RIGHT: RELEASE CONDITION (Editable) -->
                <div class="col-md-6">
                    <Card class="h-100">
                        <template #title>
                            <div class="d-flex align-items-center justify-content-between">
                                <h5 class="text-primary mb-0">
                                    <i class="pi pi-send me-2"></i>Release Condition
                                </h5>
                                <Tag v-if="hasExistingRelease" severity="info" value="Update" />
                                <Tag v-else severity="warning" value="New" />
                            </div>
                        </template>
                        <template #content>
                            <!-- Existing Release Alert -->
                            <Message v-if="hasExistingRelease" severity="info" :closable="false" class="mb-3">
                                Last update: {{ formatDate(releaseData.inspected_at) }} by {{ releaseData.inspected_by }}
                            </Message>

                            <!-- Physical Condition Section -->
                            <fieldset class="condition-section">
                                <legend class="fw-bold">Physical Condition After Cleaning</legend>
                                <div class="condition-checks">
                                    <div class="p-field-checkbox">
                                        <Checkbox v-model="releaseData.physical_damage" :binary="true" inputId="rel_physical_damage" />
                                        <label for="rel_physical_damage">Physical Damage Present</label>
                                    </div>
                                    <div class="p-field-checkbox">
                                        <Checkbox v-model="releaseData.scratches" :binary="true" inputId="rel_scratches" />
                                        <label for="rel_scratches">Scratches</label>
                                    </div>
                                    <div class="p-field-checkbox">
                                        <Checkbox v-model="releaseData.dents" :binary="true" inputId="rel_dents" />
                                        <label for="rel_dents">Dents</label>
                                    </div>
                                    <div class="p-field-checkbox">
                                        <Checkbox v-model="releaseData.cracks" :binary="true" inputId="rel_cracks" />
                                        <label for="rel_cracks">Cracks</label>
                                    </div>
                                </div>
                            </fieldset>

                            <!-- Functional Tests Section -->
                            <fieldset class="condition-section">
                                <legend class="fw-bold">Functional Tests After Cleaning</legend>
                                <div class="condition-checks">
                                    <div class="p-field-checkbox">
                                        <Checkbox v-model="releaseData.powers_on" :binary="true" inputId="rel_powers_on" />
                                        <label for="rel_powers_on">Powers On</label>
                                    </div>
                                    <div class="p-field-checkbox">
                                        <Checkbox v-model="releaseData.all_functions_work" :binary="true" inputId="rel_functions" />
                                        <label for="rel_functions">All Functions Work</label>
                                    </div>
                                    <div class="p-field-checkbox">
                                        <Checkbox v-model="releaseData.connectivity_tested" :binary="true" inputId="rel_connectivity" />
                                        <label for="rel_connectivity">Connectivity Tested</label>
                                    </div>
                                    <div class="p-field-checkbox">
                                        <Checkbox v-model="releaseData.display_condition" :binary="true" inputId="rel_display" />
                                        <label for="rel_display">Display Good Condition</label>
                                    </div>
                                </div>
                            </fieldset>

                            <!-- Inspector Notes -->
                            <div class="mt-3">
                                <label for="rel_notes" class="fw-bold mb-2 d-block">Cleaning Notes</label>
                                <Textarea v-model="releaseData.notes" rows="3" class="w-100" 
                                    placeholder="Document any cleaning performed, repairs made, or remaining issues..." />
                            </div>

                            <!-- Condition Score Display -->
                            <Card v-if="releaseScore !== null" class="mt-3">
                                <template #content>
                                    <div class="text-center">
                                        <span class="fw-bold">Release Score: </span>
                                        <span :class="getScoreClass(releaseScore)" class="fs-5 fw-bold">
                                            {{ releaseScore }}%
                                        </span>
                                    </div>
                                </template>
                            </Card>
                        </template>
                    </Card>
                </div>
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" icon="pi pi-times" @click="handleClose" severity="secondary" />
            <Button label="Save Release Condition" icon="pi pi-check" @click="saveReleaseCondition" 
                :loading="saving" severity="success" :disabled="!receivedCondition" />
        </template>
    </Dialog>
</template>

<script>
import { Dialog, Button, Checkbox, Textarea, Message, Card, Tag } from 'primevue';
import axios from 'axios';

const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: 'ReceiveReleaseConditionModal',
    components: {
        Dialog,
        Button,
        Checkbox,
        Textarea,
        Message,
        Card,
        Tag
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
            receivedCondition: null,
            hasExistingRelease: false,
            releaseScore: null,
            releaseData: {
                item_number: '',
                product_id: '',
                condition_type: 'release',
                physical_damage: false,
                scratches: false,
                dents: false,
                cracks: false,
                powers_on: false,
                all_functions_work: false,
                connectivity_tested: false,
                display_condition: false,
                notes: '',
                inspected_by: '',
                inspected_at: null
            }
        };
    },
    computed: {
        dialogTitle() {
            return `Condition Inspection - ${this.item?.ProductTitle || 'Item'}`;
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
                // Load RECEIVED condition
                const receiveResponse = await axios.get(
                    `${API_BASE_URL}/api/testing/condition/${this.item.itemnumber}`,
                    { params: { type: 'receive' } }
                );

                if (receiveResponse.data.success && receiveResponse.data.condition) {
                    this.receivedCondition = receiveResponse.data.condition;
                }

                // Load RELEASE condition
                const releaseResponse = await axios.get(
                    `${API_BASE_URL}/api/testing/condition/${this.item.itemnumber}`,
                    { params: { type: 'release' } }
                );

                if (releaseResponse.data.success && releaseResponse.data.condition) {
                    this.releaseData = { ...this.releaseData, ...releaseResponse.data.condition };
                    this.hasExistingRelease = true;
                    this.releaseScore = releaseResponse.data.condition.condition_score || null;
                } else {
                    // Initialize with item data for new release condition
                    this.releaseData.item_number = this.item.itemnumber;
                    this.releaseData.product_id = this.item.ProductID;
                    this.releaseData.condition_type = 'release';
                    this.hasExistingRelease = false;
                }
            } catch (error) {
                console.error('Failed to load condition data:', error);
            } finally {
                this.loading = false;
            }
        },

        async saveReleaseCondition() {
            if (!this.receivedCondition) {
                alert('Cannot save release condition without a received condition!');
                return;
            }

            this.saving = true;
            try {
                const dataToSend = {
                    item_number: this.releaseData.item_number,
                    product_id: String(this.releaseData.product_id),
                    condition_type: 'release',
                    physical_damage: this.releaseData.physical_damage,
                    scratches: this.releaseData.scratches,
                    dents: this.releaseData.dents,
                    cracks: this.releaseData.cracks,
                    powers_on: this.releaseData.powers_on,
                    all_functions_work: this.releaseData.all_functions_work,
                    connectivity_tested: this.releaseData.connectivity_tested,
                    display_condition: this.releaseData.display_condition,
                    notes: this.releaseData.notes
                };

                console.log('Sending release condition:', dataToSend);

                const response = await axios.post(
                    `${API_BASE_URL}/api/testing/condition`,
                    dataToSend
                );

                if (response.data.success) {
                    this.releaseScore = response.data.score;
                    this.$emit('saved', response.data.condition);
                    this.handleClose();
                }
            } catch (error) {
                console.error('Failed to save release condition:', error);
                
                const errorMessage = error.response?.data?.errors 
                    ? Object.values(error.response.data.errors).flat().join(', ')
                    : error.response?.data?.message || 'Failed to save release condition';
                
                if (typeof this.$swal !== 'undefined') {
                    this.$swal.fire({
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
            this.receivedCondition = null;
            this.releaseData = {
                item_number: '',
                product_id: '',
                condition_type: 'release',
                physical_damage: false,
                scratches: false,
                dents: false,
                cracks: false,
                powers_on: false,
                all_functions_work: false,
                connectivity_tested: false,
                display_condition: false,
                notes: '',
                inspected_by: '',
                inspected_at: null
            };
            this.hasExistingRelease = false;
            this.releaseScore = null;
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
.dual-condition-view {
    min-height: 400px;
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
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.condition-checks {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.5rem;
}

.p-field-checkbox {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.p-field-checkbox label {
    margin-bottom: 0;
    cursor: pointer;
    font-size: 0.9rem;
}

.condition-checks-readonly {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.check-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.check-item i {
    font-size: 1rem;
}

@media (max-width: 768px) {
    .row > div {
        margin-bottom: 1rem;
    }
}
</style>