<template>
    <Dialog
        v-model:visible="visible"
        modal
        :header="`Configure Logs for ASIN: ${selectedConfig.ASIN} - ${selectedConfig.AStitle}`"
        :style="{ width: '1260px' }"
    >
        <!-- Log Fields -->
        <div
            class="mb-3 px-2 py-2 rounded d-flex align-items-center justify-content-between"
            style="
                background: #f3effe;
                border-left: 4px solid #6f42c1;
                cursor: pointer;
            "
            @click="logFieldsCollapsed = !logFieldsCollapsed"
        >
            <div class="d-flex align-items-center gap-2">
                <i class="pi pi-tag" style="color: #6f42c1"></i>
                <strong style="color: #6f42c1">Labeling Module</strong>
                <span class="text-muted" style="font-size: 12px">
                    ({{ logFields.length }} field{{
                        logFields.length !== 1 ? "s" : ""
                    }})
                </span>
            </div>
            <i
                :class="
                    logFieldsCollapsed
                        ? 'pi pi-chevron-down'
                        : 'pi pi-chevron-up'
                "
                style="color: #6f42c1"
            />
        </div>

        <div v-if="!logFieldsCollapsed" class="d-flex flex-column gap-3">
            <div
                v-for="(field, index) in logFields"
                :key="index"
                class="p-3 border rounded"
            >
                <!-- Field Header -->
                <div
                    class="d-flex justify-content-between align-items-center mb-3"
                >
                    <div class="d-flex align-items-center gap-2">
                        <i
                            class="pi pi-bars"
                            style="cursor: grab; color: #aaa"
                        />
                        <InputText
                            v-model="field.label"
                            placeholder="New Log Field"
                            style="
                                font-weight: 600;
                                border: none;
                                border-bottom: 1px solid #ccc;
                                background: transparent;
                                padding: 2px 4px;
                            "
                        />
                    </div>
                    <div class="d-flex gap-2">
                        <i
                            class="pi pi-cog"
                            style="cursor: pointer; color: #aaa"
                        />
                        <i
                            class="pi pi-trash"
                            style="cursor: pointer; color: #dc3545"
                            @click="removeLogField(index)"
                        />
                    </div>
                </div>

                <!-- Field Type + Default Value -->
                <div class="row mb-3">
                    <div class="col-6">
                        <label
                            class="form-label text-muted"
                            style="font-size: 12px"
                            >Field Type</label
                        >
                        <Select
                            v-model="field.type"
                            :options="fieldTypeOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select type"
                            class="w-100"
                            @change="onFieldTypeChange(field)"
                        />
                    </div>
                    <div class="col-6">
                        <label
                            class="form-label text-muted"
                            style="font-size: 12px"
                            >Default Value</label
                        >
                        <InputText
                            v-model="field.defaultValue"
                            placeholder="Leave empty if none"
                            class="w-100"
                        />
                    </div>
                </div>

                <!-- Checkboxes -->
                <div class="d-flex gap-4 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <input
                            type="checkbox"
                            v-model="field.required"
                            :id="`required-${index}`"
                        />
                        <label :for="`required-${index}`">Required Field</label>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <input
                            type="checkbox"
                            v-model="field.hasOptions"
                            :id="`hasOptions-${index}`"
                        />
                        <label :for="`hasOptions-${index}`"
                            >Has Options/Selections</label
                        >
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <input
                            type="checkbox"
                            v-model="field.preTypedNotes"
                            :id="`preTypedNotes-${index}`"
                        />
                        <label :for="`preTypedNotes-${index}`"
                            >Pre-typed Notes</label
                        >
                    </div>
                </div>

                <!-- Options/Selections -->
                <div
                    v-if="field.hasOptions"
                    class="p-3 rounded mb-3"
                    style="background: #f8f9fa"
                >
                    <label
                        class="text-muted mb-2 d-block"
                        style="font-size: 12px"
                        >Options/Selections:</label
                    >
                    <div
                        v-for="(option, oIndex) in field.options"
                        :key="oIndex"
                        class="d-flex align-items-center justify-content-between mb-2 px-3 py-2 rounded"
                        style="background: #e9ecef"
                    >
                        <InputText
                            v-model="field.options[oIndex].value"
                            class="border-0 bg-transparent w-100"
                            style="font-family: monospace"
                        />
                        <span
                            v-if="field.preTypedNotes"
                            @click="toggleHasNote(field, oIndex)"
                            class="me-2"
                            style="
                                cursor: pointer;
                                font-size: 12px;
                                white-space: nowrap;
                            "
                            :style="{
                                color: field.options[oIndex].hasNote
                                    ? '#28a745'
                                    : '#aaa',
                            }"
                        >
                            {{
                                field.options[oIndex].hasNote
                                    ? "✓ Has Note"
                                    : "+ Has Note"
                            }}
                        </span>
                        <i
                            class="pi pi-trash"
                            style="cursor: pointer; color: #dc3545"
                            @click="removeOption(field, oIndex)"
                        />
                    </div>
                    <div
                        class="mt-2"
                        style="cursor: pointer; color: #6f42c1"
                        @click="addOption(field)"
                    >
                        + Add Option
                    </div>
                </div>

                <!-- Pre-typed Notes Configuration -->
                <div
                    v-if="
                        field.preTypedNotes &&
                        field.options.some((o) => o.hasNote)
                    "
                    class="p-3 rounded"
                    style="background: #eef4ff; border: 1px solid #cce0ff"
                >
                    <label
                        class="mb-2 d-block"
                        style="
                            font-size: 13px;
                            color: #1a56db;
                            font-weight: 600;
                        "
                    >
                        Pre-typed Notes Configuration:
                    </label>
                    <div
                        v-for="(option, oIndex) in field.options.filter(
                            (o) => o.hasNote,
                        )"
                        :key="oIndex"
                        class="p-3 mb-2 rounded bg-white border"
                    >
                        <div
                            class="d-flex justify-content-between align-items-center mb-1"
                        >
                            <strong
                                style="font-family: monospace; font-size: 13px"
                            >
                                {{ option.value }}
                            </strong>
                        </div>
                        <div
                            v-if="!option.editingNote"
                            class="text-muted"
                            style="font-size: 13px"
                        >
                            {{ option.note || "No note yet." }}
                        </div>
                        <textarea
                            v-if="option.editingNote"
                            v-model="option.note"
                            class="form-control mt-1"
                            rows="2"
                            placeholder="Type a pre-defined note for this option..."
                            style="font-size: 13px"
                        />
                        <span
                            class="mt-1 d-inline-block"
                            style="
                                cursor: pointer;
                                color: #1a56db;
                                font-size: 12px;
                            "
                            @click="option.editingNote = !option.editingNote"
                        >
                            {{ option.editingNote ? "Done" : "Edit Note" }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Add New Log Field + Save -->
            <div class="mt-3 d-flex justify-content-between align-items-center">
                <Button
                    label="Add New Log Field"
                    icon="pi pi-plus"
                    @click="addLogField"
                    style="background: #6f42c1; border-color: #6f42c1"
                />
                <Button
                    v-if="logFields.length > 0"
                    label="Save"
                    icon="pi pi-save"
                    :loading="savingConfig"
                    :disabled="savingConfig"
                    severity="success"
                    @click="saveLogFields"
                />
            </div>
        </div>
    </Dialog>
</template>

<script>
export default {
    name: "ASINLabeling",

    props: {
        modelValue: {
            type: Boolean,
            default: false,
        },
        selectedConfig: {
            type: Object,
            default: () => ({}),
        },
    },

    emits: ["update:modelValue"],

    computed: {
        visible: {
            get() {
                return this.modelValue;
            },
            set(val) {
                this.$emit("update:modelValue", val);
            },
        },
    },

    data() {
        return {
            logFields: [],
            logFieldsCollapsed: false,
            fieldTypeOptions: [
                { label: "Text", value: "Text" },
                { label: "Dropdown/Select", value: "Dropdown/Select" },
                { label: "Number", value: "Number" },
                { label: "Date", value: "Date" },
                { label: "Checkbox", value: "Checkbox" },
            ],
            savingConfig: false,
            configSavedAt: null,
        };
    },

    watch: {
        // Load saved fields whenever selectedConfig changes
        selectedConfig(val) {
            if (val?.ASIN) {
                this.loadLogFields(val.ASIN);
            }
        },
    },

    methods: {
        async loadLogFields(asin) {
            try {
                const saved = localStorage.getItem(`asin_config:${asin}`);
                this.logFields = saved ? JSON.parse(saved) : [];
            } catch {
                this.logFields = [];
            }
        },

        async saveLogFields() {
            this.savingConfig = true;
            try {
                localStorage.setItem(
                    `asin_config:${this.selectedConfig.ASIN}`,
                    JSON.stringify(this.logFields),
                );
                this.configSavedAt = new Date().toLocaleTimeString();
            } catch (e) {
                console.error("Failed to save config:", e);
            } finally {
                this.savingConfig = false;
            }
        },

        addLogField() {
            this.logFields.push({
                label: "",
                type: "Dropdown/Select",
                defaultValue: "",
                required: false,
                hasOptions: false,
                preTypedNotes: false,
                options: [],
            });
        },

        removeLogField(index) {
            this.logFields.splice(index, 1);
        },

        addOption(field) {
            field.options.push({
                value: "",
                hasNote: false,
                note: "",
                editingNote: false,
            });
        },

        removeOption(field, oIndex) {
            field.options.splice(oIndex, 1);
        },

        toggleHasNote(field, oIndex) {
            field.options[oIndex].hasNote = !field.options[oIndex].hasNote;
            if (!field.options[oIndex].hasNote) {
                field.options[oIndex].note = "";
                field.options[oIndex].editingNote = false;
            }
        },

        onFieldTypeChange(field) {
            if (field.type === "Dropdown/Select") {
                field.hasOptions = true;
            }
        },
    },
};
</script>
