<template>
    <Dialog
        :visible="visible"
        modal
        header="Address Blacklist"
        :style="{ width: '720px', maxWidth: '95vw' }"
        :pt="{
            root: { class: 'mobile-fullscreen-dialog' }
        }"
        @update:visible="onDialogHide"
    >
        <div class="address-blacklist-wrapper">
            <div class="form-section">
                <div class="form-grid">
                    <fieldset class="form-field">
                        <label>Module</label>
                        <InputText :modelValue="moduleName" disabled />
                    </fieldset>

                    <fieldset class="form-field">
                        <label>Subject</label>
                        <InputText :modelValue="subjectName" disabled />
                    </fieldset>

                    <fieldset class="form-field form-field-full">
                        <label>Detect Word</label>
                        <InputText
                            v-model="form.detect_word"
                            placeholder="Example: MIAMI, OAKLAND, SC, Amazon"
                            @keyup.enter="saveRule"
                        />
                        <small class="helper-text">
                            This will match anywhere inside the customer address.
                        </small>
                    </fieldset>

                    <fieldset class="form-field">
                        <label>Color</label>
                        <div class="color-row">
                            <input
                                v-model="form.color"
                                type="color"
                                class="color-picker"
                            />
                            <InputText
                                v-model="form.color"
                                placeholder="#ff0000"
                            />
                        </div>
                    </fieldset>

                    <fieldset class="form-field actions-field">
                        <label>&nbsp;</label>
                        <div class="action-row">
                            <Button
                                label="Save"
                                icon="pi pi-save"
                                size="small"
                                :loading="saving"
                                @click="saveRule"
                            />
                            <Button
                                label="Clear"
                                icon="pi pi-times"
                                size="small"
                                severity="secondary"
                                outlined
                                @click="resetForm"
                            />
                        </div>
                    </fieldset>
                </div>

                <Message
                    v-if="errorMessage"
                    severity="error"
                    :closable="false"
                    class="mt-3"
                >
                    {{ errorMessage }}
                </Message>

                <Message
                    v-if="successMessage"
                    severity="success"
                    :closable="false"
                    class="mt-3"
                >
                    {{ successMessage }}
                </Message>
            </div>

            <Divider />

            <div class="list-section">
                <div class="list-header">
                    <div>
                        <h4 class="mb-1">{{ moduleName }} - {{ subjectName }}</h4>
                        <p class="list-subtext mb-0">Existing rules</p>
                    </div>

                    <Button
                        icon="pi pi-refresh"
                        size="small"
                        severity="secondary"
                        outlined
                        :loading="loading"
                        @click="fetchRules"
                    />
                </div>

                <div v-if="loading" class="state-box">
                    <i class="pi pi-spin pi-spinner state-icon"></i>
                    <span>Loading rules...</span>
                </div>

                <div v-else-if="rules.length === 0" class="state-box">
                    <i class="pi pi-inbox state-icon muted"></i>
                    <span>No address blacklist rules yet.</span>
                </div>

                <div v-else class="rules-list">
                    <div
                        v-for="rule in rules"
                        :key="rule.id"
                        class="rule-card"
                        :style="{ borderLeftColor: rule.color || '#ff0000' }"
                    >
                        <div class="rule-main">
                            <span
                                class="rule-dot"
                                :style="{ backgroundColor: rule.color || '#ff0000' }"
                            ></span>

                            <div class="rule-text">
                                <div class="rule-title">
                                    {{ rule.module_name || moduleName }} - {{ rule.subject_name || subjectName }}
                                </div>
                                <div class="rule-word">
                                    {{ rule.detect_word }}
                                </div>
                                <div class="rule-meta">
                                    {{ rule.color || '#ff0000' }}
                                </div>
                            </div>
                        </div>

                        <div class="rule-actions">
                            <Button
                                label="Delete"
                                icon="pi pi-trash"
                                size="small"
                                severity="danger"
                                outlined
                                :loading="deletingId === rule.id"
                                @click="deleteRule(rule)"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="footer-row">
                <Button
                    label="Close"
                    icon="pi pi-times"
                    severity="secondary"
                    outlined
                    size="small"
                    @click="$emit('close')"
                />
            </div>
        </template>
    </Dialog>
</template>

<script>
import axios from "axios";
import { Button, Dialog, Divider, InputText, Message } from "primevue";

const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
    name: "AddressBlacklistModal",
    components: {
        Button,
        Dialog,
        Divider,
        InputText,
        Message,
    },
    props: {
        visible: {
            type: Boolean,
            default: false,
        },
        moduleName: {
            type: String,
            default: "Amazon Orders",
        },
        subjectName: {
            type: String,
            default: "Address",
        },
    },
    emits: ["close", "saved"],
    data() {
        return {
            loading: false,
            saving: false,
            deletingId: null,
            rules: [],
            errorMessage: "",
            successMessage: "",
            form: {
                detect_word: "",
                color: "#ff0000",
            },
        };
    },
    watch: {
        visible(newVal) {
            if (newVal) {
                this.clearMessages();
                this.fetchRules();
            }
        },
    },
    methods: {
        onDialogHide(val) {
            if (!val) {
                this.$emit("close");
            }
        },

        clearMessages() {
            this.errorMessage = "";
            this.successMessage = "";
        },

        resetForm() {
            this.form.detect_word = "";
            this.form.color = "#ff0000";
            this.clearMessages();
        },

        async fetchRules() {
            this.loading = true;
            this.clearMessages();

            try {
                const response = await axios.get(
                    `${API_BASE_URL}/api/fbm-orders/address-blacklist/list`,
                    {
                        params: {
                            module_name: this.moduleName,
                            subject_name: this.subjectName,
                        },
                        withCredentials: true,
                        headers: {
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]'
                            )?.content,
                        },
                    }
                );

                if (Array.isArray(response?.data?.data)) {
                    this.rules = response.data.data;
                } else if (Array.isArray(response?.data)) {
                    this.rules = response.data;
                } else {
                    this.rules = [];
                }
            } catch (error) {
                console.error("Failed to fetch address blacklist rules:", error);
                this.rules = [];
                this.errorMessage =
                    error?.response?.data?.message ||
                    "Failed to load address blacklist rules.";
            } finally {
                this.loading = false;
            }
        },

        async saveRule() {
            this.clearMessages();

            const detectWord = String(this.form.detect_word || "").trim();
            const color = String(this.form.color || "").trim();

            if (!detectWord) {
                this.errorMessage = "Detect Word is required.";
                return;
            }

            if (!color) {
                this.errorMessage = "Color is required.";
                return;
            }

            this.saving = true;

            try {
                const payload = {
                    module_name: this.moduleName,
                    subject_name: this.subjectName,
                    detect_word: detectWord,
                    color,
                };

                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/address-blacklist/save`,
                    payload,
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]'
                            )?.content,
                        },
                    }
                );

                this.successMessage =
                    response?.data?.message ||
                    "Address blacklist rule saved successfully.";

                this.form.detect_word = "";
                this.form.color = "#ff0000";

                await this.fetchRules();
                this.$emit("saved");
            } catch (error) {
                console.error("Failed to save address blacklist rule:", error);
                this.errorMessage =
                    error?.response?.data?.message ||
                    "Failed to save address blacklist rule.";
            } finally {
                this.saving = false;
            }
        },

        async deleteRule(rule) {
            if (!rule?.id) return;

            const confirmed = window.confirm(
                `Delete blacklist rule "${rule.detect_word}"?`
            );
            if (!confirmed) return;

            this.clearMessages();
            this.deletingId = rule.id;

            try {
                const response = await axios.post(
                    `${API_BASE_URL}/api/fbm-orders/address-blacklist/delete`,
                    { id: rule.id },
                    {
                        withCredentials: true,
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]'
                            )?.content,
                        },
                    }
                );

                this.successMessage =
                    response?.data?.message ||
                    "Address blacklist rule deleted successfully.";

                await this.fetchRules();
                this.$emit("saved");
            } catch (error) {
                console.error("Failed to delete address blacklist rule:", error);
                this.errorMessage =
                    error?.response?.data?.message ||
                    "Failed to delete address blacklist rule.";
            } finally {
                this.deletingId = null;
            }
        },
    },
};
</script>

<style scoped>
.address-blacklist-wrapper {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.form-field {
    border: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-field-full {
    grid-column: 1 / -1;
}

.actions-field {
    justify-content: flex-end;
}

.action-row {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.color-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.color-picker {
    width: 50px;
    height: 40px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
    cursor: pointer;
    padding: 0.2rem;
}

.helper-text {
    color: #6b7280;
    font-size: 0.8rem;
}

.list-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.list-subtext {
    color: #6b7280;
    font-size: 0.9rem;
}

.state-box {
    min-height: 180px;
    border: 1px dashed #d1d5db;
    border-radius: 10px;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    align-items: center;
    justify-content: center;
    color: #64748b;
}

.state-icon {
    font-size: 2rem;
    color: #6366f1;
}

.state-icon.muted {
    color: #94a3b8;
}

.rules-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    max-height: 360px;
    overflow-y: auto;
    padding-right: 0.2rem;
}

.rule-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 0.9rem 1rem;
    border: 1px solid #e5e7eb;
    border-left: 5px solid #ff0000;
    border-radius: 10px;
    background: #fff;
}

.rule-main {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    min-width: 0;
}

.rule-dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 1px solid #d1d5db;
    margin-top: 0.25rem;
    flex-shrink: 0;
}

.rule-text {
    min-width: 0;
}

.rule-title {
    font-weight: 700;
    color: #111827;
    line-height: 1.2;
}

.rule-word {
    font-size: 1.05rem;
    color: #111827;
    word-break: break-word;
}

.rule-meta {
    font-size: 0.8rem;
    color: #6b7280;
}

.rule-actions {
    flex-shrink: 0;
}

.footer-row {
    display: flex;
    justify-content: flex-end;
    width: 100%;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-field-full {
        grid-column: auto;
    }

    .color-row {
        flex-direction: column;
        align-items: stretch;
    }

    .rule-card {
        flex-direction: column;
        align-items: stretch;
    }

    .rule-actions {
        display: flex;
        justify-content: flex-end;
    }
}
</style>