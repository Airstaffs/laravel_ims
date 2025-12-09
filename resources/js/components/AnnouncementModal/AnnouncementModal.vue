<template>
    <!-- User Announcement Popup (Auto-show) -->
    <Dialog
        v-model:visible="announcementVisible"
        modal
        :header="currentAnnouncement?.title || 'Announcement'"
        :style="{ width: '35rem' }"
        :breakpoints="{ '960px': '75vw', '641px': '90vw' }"
        :closable="false"
    >
        <div class="announcement-content">
            <p class="mb-3">{{ currentAnnouncement?.message }}</p>
            <small v-if="announcementDuration" class="text-color-secondary">
                Duration: {{ announcementDuration }}
            </small>
            <Divider />
            <div class="readby-section">
                <strong>Read by:</strong>
                <span class="ml-2">{{ readByText }}</span>
            </div>
        </div>

        <template #footer>
            <Button
                label="Acknowledge"
                icon="pi pi-check"
                @click="acknowledgeAnnouncement"
                autofocus
            />
        </template>
    </Dialog>

    <!-- Admin Management Modal -->
    <Dialog
        v-model:visible="manageVisible"
        modal
        header="Announcements"
        :style="{ width: '70rem' }"
        :breakpoints="{ '1400px': '90vw', '768px': '95vw' }"
        class="announcement-manage-modal"
    >
        <!-- Filters and Actions -->
        <div class="filters-container">
            <!-- Left: Filters -->
            <div class="filters-group">
                <div class="filter-item">
                    <label>Status</label>
                    <Select
                        v-model="filterStatus"
                        :options="statusOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Select Status"
                        @change="loadAnnouncements"
                    />
                </div>
                <div class="filter-item">
                    <label>Search (title/message)</label>
                    <InputText
                        v-model="filterQuery"
                        placeholder="Search..."
                        @input="debouncedSearch"
                    />
                </div>
            </div>

            <!-- Right: Buttons -->
            <div class="actions-group">
                <Button
                    label="Refresh"
                    icon="pi pi-refresh"
                    outlined
                    size="small"
                    @click="loadAnnouncements"
                />
                <Button
                    label="New"
                    icon="pi pi-plus"
                    size="small"
                    @click="openComposeModal()"
                />
            </div>
        </div>

        <!-- Announcements Table -->
        <DataTable
            :value="announcements"
            :loading="loading"
            :paginator="true"
            :rows="10"
            class="announcements-table"
            stripedRows
        >
            <Column field="id" header="#" style="min-width: 70px"></Column>
            <Column
                field="title"
                header="Title"
                style="min-width: 200px"
            ></Column>
            <Column field="is_active" header="Status" style="min-width: 100px">
                <template #body="{ data }">
                    <Tag
                        :value="data.is_active ? 'Active' : 'Draft'"
                        :severity="data.is_active ? 'success' : 'secondary'"
                    />
                </template>
            </Column>
            <Column header="Window" style="min-width: 200px">
                <template #body="{ data }">
                    {{ formatWindow(data) }}
                </template>
            </Column>
            <Column header="Recipients" style="min-width: 150px">
                <template #body="{ data }">
                    {{ formatRecipients(data.recipients) }}
                </template>
            </Column>
            <Column header="Actions" style="min-width: 150px">
                <template #body="{ data }">
                    <div class="action-buttons">
                        <Button
                            icon="pi pi-pencil"
                            label="Edit"
                            size="small"
                            text
                            severity="contrast"
                            class="text-primary"
                            @click="openComposeModal(data.id)"
                        />
                        <Button
                            :icon="
                                data.is_active ? 'pi pi-pause' : 'pi pi-play'
                            "
                            :label="data.is_active ? 'Deactivate' : 'Activate'"
                            :severity="data.is_active ? 'warning' : 'success'"
                            size="small"
                            text
                            @click="toggleActive(data.id, !data.is_active)"
                        />
                    </div>
                </template>
            </Column>
        </DataTable>

        <template #footer>
            <Button
                label="Close"
                @click="manageVisible = false"
                severity="secondary"
            />
        </template>
    </Dialog>

    <!-- Compose/Edit Modal -->
    <Dialog
        v-model:visible="composeVisible"
        modal
        :header="composeTitle"
        :style="{ width: '60rem' }"
        :breakpoints="{ '1199px': '90vw', '575px': '95vw' }"
        class="announcement-compose-modal"
    >
        <div class="compose-form" style="max-height: 70vh; overflow-y: auto">
            <div class="mb-3">
                <label class="block mb-2"
                    >Title <span class="text-red-500">*</span></label
                >
                <InputText
                    v-model="form.title"
                    class="w-full"
                    maxlength="255"
                />
            </div>

            <div class="mb-3">
                <label class="block mb-2">Message</label>
                <Textarea v-model="form.message" rows="5" class="w-full" />
            </div>

            <div class="grid mb-3">
                <div class="col-12 md:col-6">
                    <label class="block mb-2">Start Date/Time</label>
                    <DatePicker
                        v-model="form.start_at"
                        show-time
                        hour-format="24"
                        date-format="yy-mm-dd"
                        class="w-full"
                    />
                </div>
                <div class="col-12 md:col-6">
                    <label class="block mb-2">End Date/Time</label>
                    <DatePicker
                        v-model="form.end_at"
                        show-time
                        hour-format="24"
                        date-format="yy-mm-dd"
                        class="w-full"
                    />
                </div>
            </div>

            <div class="mb-3">
                <label class="block mb-2">Status</label>
                <SelectButton
                    v-model="form.status"
                    :options="['draft', 'active']"
                    :allow-empty="false"
                />
            </div>

            <Divider />

            <!-- Recipients Section -->
            <div class="mb-3">
                <label class="block mb-2">Recipients</label>

                <div class="flex flex-wrap gap-3 align-items-center mb-3">
                    <div class="flex align-items-center">
                        <Checkbox
                            v-model="groupPH"
                            input-id="groupPH"
                            binary
                            @change="applyGroupSelection"
                        />
                        <label for="groupPH" class="ml-2">PH group</label>
                    </div>
                    <div class="flex align-items-center">
                        <Checkbox
                            v-model="groupUS"
                            input-id="groupUS"
                            binary
                            @change="applyGroupSelection"
                        />
                        <label for="groupUS" class="ml-2">US group</label>
                    </div>
                    <div class="ml-auto flex gap-2">
                        <Button
                            label="Check all"
                            size="small"
                            outlined
                            @click="checkAll(true)"
                        />
                        <Button
                            label="Uncheck all"
                            size="small"
                            outlined
                            @click="checkAll(false)"
                        />
                    </div>
                </div>

                <InputText
                    v-model="recipientFilter"
                    placeholder="Filter recipients..."
                    class="w-full mb-2"
                />

                <div
                    class="recipients-list"
                    style="max-height: 250px; overflow-y: auto"
                >
                    <div
                        v-for="employee in filteredEmployees"
                        :key="employee.id"
                        class="flex align-items-center gap-2 p-2 border-bottom-1 surface-border"
                    >
                        <Checkbox
                            v-model="form.recipients"
                            :input-id="`emp-${employee.id}`"
                            :value="employee.id"
                        />
                        <label
                            :for="`emp-${employee.id}`"
                            class="cursor-pointer"
                        >
                            {{
                                employee.name ||
                                employee.username ||
                                `#${employee.id}`
                            }}
                            <small
                                v-if="employee.accounttype"
                                class="text-color-secondary"
                            >
                                ({{ employee.accounttype }})
                            </small>
                        </label>
                    </div>
                </div>
                <small class="text-color-secondary"
                    >Leave empty to send to everyone.</small
                >
            </div>
        </div>

        <template #footer>
            <Button
                label="Cancel"
                @click="closeComposeModal"
                severity="secondary"
            />
            <Button
                label="Save"
                icon="pi pi-save"
                @click="submitCompose('draft')"
            />
            <Button
                label="Save & Activate"
                icon="pi pi-check"
                severity="success"
                @click="submitCompose('active')"
            />
        </template>
    </Dialog>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from "vue";
import Dialog from "primevue/dialog";
import Card from "primevue/card";
import Button from "primevue/button";
import Badge from "primevue/badge";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Tag from "primevue/tag";
import InputText from "primevue/inputtext";
import Textarea from "primevue/textarea";
import Select from "primevue/select";
import SelectButton from "primevue/selectbutton";
import Checkbox from "primevue/checkbox";
import DatePicker from "primevue/datepicker";
import Divider from "primevue/divider";
import ProgressSpinner from "primevue/progressspinner";

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    userId: {
        type: [Number, String],
        required: false,
    },
});

const emit = defineEmits(["update:visible"]);

// State
const manageVisible = computed({
    get: () => props.visible,
    set: (value) => emit("update:visible", value),
});

const announcementVisible = ref(false);
const composeVisible = ref(false);
const loading = ref(false);

// Data
const announcements = ref([]);
const employees = ref([]);
const currentAnnouncement = ref(null);
const lastShownId = ref(null);

// Filters
const filterStatus = ref("all");
const filterQuery = ref("");
const recipientFilter = ref("");

const statusOptions = [
    { label: "All", value: "all" },
    { label: "Active", value: "active" },
    { label: "Draft", value: "draft" },
];

// Form
const form = ref({
    id: null,
    title: "",
    message: "",
    start_at: null,
    end_at: null,
    status: "draft",
    recipients: [],
});

const groupPH = ref(false);
const groupUS = ref(false);

// Computed
const composeTitle = computed(() =>
    form.value.id ? `Edit #${form.value.id}` : "New Announcement"
);

const announcementDuration = computed(() => {
    if (!currentAnnouncement.value) return "";
    const start = currentAnnouncement.value.start_at;
    const end = currentAnnouncement.value.end_at;
    if (!start && !end) return "";
    if (start && end) return `${start} — ${end}`;
    if (start) return `from ${start}`;
    return `until ${end}`;
});

const readByText = computed(() => {
    const readby = currentAnnouncement.value?.readby;
    if (Array.isArray(readby) && readby.length) {
        return readby.join(", ");
    }
    return "None";
});

const filteredEmployees = computed(() => {
    if (!recipientFilter.value) return employees.value;

    const term = recipientFilter.value.toLowerCase();
    return employees.value.filter((emp) => {
        const name = (emp.name || emp.username || "").toLowerCase();
        const type = (emp.accounttype || "").toLowerCase();
        return name.includes(term) || type.includes(term);
    });
});

// Methods
const loadAnnouncements = async () => {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (filterStatus.value !== "all")
            params.set("status", filterStatus.value);
        if (filterQuery.value) params.set("q", filterQuery.value);

        const response = await fetch(
            `/hr/announcements/admin?${params.toString()}`,
            {
                credentials: "same-origin",
            }
        );
        const data = await response.json();
        announcements.value = Array.isArray(data) ? data : [];
    } catch (error) {
        console.error("Error loading announcements:", error);
        Swal.fire({
            icon: "error",
            title: "Error",
            text: "Failed to load announcements",
        });
    } finally {
        loading.value = false;
    }
};

const loadEmployees = async () => {
    try {
        const response = await fetch("/hr/employees", {
            credentials: "same-origin",
        });
        const data = await response.json();
        employees.value = Array.isArray(data) ? data : [];
    } catch (error) {
        console.error("Error loading employees:", error);
        employees.value = [];
    }
};

const checkUserAnnouncements = async () => {
    if (announcementVisible.value) return;
    if (document.hidden) return;

    try {
        const response = await fetch("/hr/dash/announcements", {
            credentials: "same-origin",
        });
        const list = await response.json();

        if (Array.isArray(list) && list.length > 0) {
            const ann = list[0];
            if (ann && ann.id !== lastShownId.value) {
                currentAnnouncement.value = ann;
                announcementVisible.value = true;
                lastShownId.value = ann.id;
            }
        }
    } catch (error) {
        console.error("Error checking announcements:", error);
    }
};

const acknowledgeAnnouncement = async () => {
    const annId = currentAnnouncement.value?.id;
    if (!annId) {
        announcementVisible.value = false;
        return;
    }

    try {
        const csrfToken = document.querySelector(
            'meta[name="csrf-token"]'
        )?.content;

        if (!csrfToken) {
            throw new Error("CSRF token not found");
        }

        const xhr = new XMLHttpRequest();
        xhr.open("POST", "/hr/dash/announcements/acknowledge", true);
        xhr.setRequestHeader("Content-Type", "application/json");
        xhr.setRequestHeader("X-CSRF-TOKEN", csrfToken);
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.setRequestHeader("Accept", "application/json");
        xhr.withCredentials = true;

        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const result = JSON.parse(xhr.responseText);
                    if (result && result.success) {
                        announcementVisible.value = false;
                        lastShownId.value = annId;
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: result?.message || "Failed to acknowledge",
                        });
                    }
                } catch (parseError) {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Invalid response from server",
                    });
                }
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Failed to acknowledge",
                });
            }
        };

        xhr.onerror = function () {
            Swal.fire({
                icon: "error",
                title: "Network Error",
                text: "Could not acknowledge announcement",
            });
        };

        xhr.send(
            JSON.stringify({
                announcement_id: annId,
                username:
                    window.userName ||
                    document.querySelector('meta[name="user-name"]')?.content,
            })
        );
    } catch (error) {
        console.error("Error acknowledging announcement:", error);
        Swal.fire({
            icon: "error",
            title: "Network Error",
            text: "Could not acknowledge announcement",
        });
    }
};

const openComposeModal = (id = null) => {
    resetForm();

    if (id) {
        const announcement = announcements.value.find((a) => a.id === id);
        if (announcement) {
            form.value = {
                id: announcement.id,
                title: announcement.title || "",
                message: announcement.message || "",
                start_at: announcement.start_at
                    ? new Date(announcement.start_at)
                    : null,
                end_at: announcement.end_at
                    ? new Date(announcement.end_at)
                    : null,
                status: announcement.is_active ? "active" : "draft",
                recipients: Array.isArray(announcement.recipients)
                    ? announcement.recipients
                    : [],
            };
        }
    }

    composeVisible.value = true;
};

const closeComposeModal = () => {
    composeVisible.value = false;
    resetForm();
};

const resetForm = () => {
    form.value = {
        id: null,
        title: "",
        message: "",
        start_at: null,
        end_at: null,
        status: "draft",
        recipients: [],
    };
    groupPH.value = false;
    groupUS.value = false;
    recipientFilter.value = "";
};

const submitCompose = async (mode) => {
    if (!form.value.title.trim()) {
        Swal.fire({
            icon: "warning",
            title: "Validation Error",
            text: "Title is required",
        });
        return;
    }

    if (
        form.value.start_at &&
        form.value.end_at &&
        form.value.start_at > form.value.end_at
    ) {
        Swal.fire({
            icon: "warning",
            title: "Validation Error",
            text: "Start must be before End",
        });
        return;
    }

    try {
        const csrfToken = document.querySelector(
            'meta[name="csrf-token"]'
        )?.content;

        if (!csrfToken) {
            throw new Error("CSRF token not found");
        }

        const payload = {
            id: form.value.id,
            title: form.value.title,
            message: form.value.message,
            start_at: form.value.start_at
                ? formatDateTime(form.value.start_at)
                : null,
            end_at: form.value.end_at
                ? formatDateTime(form.value.end_at)
                : null,
            save_mode: mode,
            recipients: form.value.recipients,
        };

        const xhr = new XMLHttpRequest();
        xhr.open("POST", "/hr/announcements/save", true);
        xhr.setRequestHeader("Content-Type", "application/json");
        xhr.setRequestHeader("X-CSRF-TOKEN", csrfToken);
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.setRequestHeader("Accept", "application/json");
        xhr.withCredentials = true;

        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const result = JSON.parse(xhr.responseText);
                    if (result && result.success !== false) {
                        Swal.fire({
                            icon: "success",
                            title: "Success",
                            text: "Announcement saved successfully",
                            timer: 2000,
                            showConfirmButton: false,
                        });
                        closeComposeModal();
                        loadAnnouncements();
                    } else {
                        throw new Error(result?.error || "Save failed");
                    }
                } catch (parseError) {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Invalid response from server",
                    });
                }
            } else {
                try {
                    const error = JSON.parse(xhr.responseText);
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: error.message || "Request failed",
                    });
                } catch (parseError) {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Request failed with status " + xhr.status,
                    });
                }
            }
        };

        xhr.onerror = function () {
            Swal.fire({
                icon: "error",
                title: "Network Error",
                text: "Failed to connect to server",
            });
        };

        xhr.send(JSON.stringify(payload));
    } catch (error) {
        console.error("Error saving announcement:", error);
        Swal.fire({
            icon: "error",
            title: "Error",
            text: error.message || "Save failed",
        });
    }
};

const toggleActive = async (id, makeActive) => {
    try {
        const csrfToken = document.querySelector(
            'meta[name="csrf-token"]'
        )?.content;

        if (!csrfToken) {
            throw new Error("CSRF token not found");
        }

        const xhr = new XMLHttpRequest();
        xhr.open("POST", "/hr/announcements/toggle-active", true);
        xhr.setRequestHeader("Content-Type", "application/json");
        xhr.setRequestHeader("X-CSRF-TOKEN", csrfToken);
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.setRequestHeader("Accept", "application/json");
        xhr.withCredentials = true;

        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const result = JSON.parse(xhr.responseText);
                    if (result && result.success !== false) {
                        Swal.fire({
                            icon: "success",
                            title: "Success",
                            text: `Announcement ${
                                makeActive ? "activated" : "deactivated"
                            }`,
                            timer: 2000,
                            showConfirmButton: false,
                        });
                        loadAnnouncements();
                    } else {
                        throw new Error(result?.error || "Toggle failed");
                    }
                } catch (parseError) {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Invalid response from server",
                    });
                }
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Toggle failed",
                });
            }
        };

        xhr.onerror = function () {
            Swal.fire({
                icon: "error",
                title: "Network Error",
                text: "Failed to connect to server",
            });
        };

        xhr.send(JSON.stringify({ id, make_active: makeActive }));
    } catch (error) {
        console.error("Error toggling announcement:", error);
        Swal.fire({
            icon: "error",
            title: "Error",
            text: error.message || "Toggle failed",
        });
    }
};

const applyGroupSelection = () => {
    if (!groupPH.value && !groupUS.value) return;

    const selected = employees.value
        .filter(
            (e) =>
                (groupPH.value && e.accounttype === "PH") ||
                (groupUS.value && e.accounttype === "US")
        )
        .map((e) => e.id);

    form.value.recipients = selected;
};

const checkAll = (flag) => {
    if (flag) {
        form.value.recipients = filteredEmployees.value.map((e) => e.id);
    } else {
        form.value.recipients = [];
    }
};

const formatWindow = (announcement) => {
    const start = announcement.start_at;
    const end = announcement.end_at;
    if (!start && !end) return "—";
    if (start && end) return `${start} — ${end}`;
    if (start) return `from ${start}`;
    return `until ${end}`;
};

const formatRecipients = (recipients) => {
    if (!Array.isArray(recipients) || recipients.length === 0) {
        return "Everyone";
    }

    const names = recipients.slice(0, 3).map((id) => {
        const emp = employees.value.find((e) => e.id === id);
        return emp?.name || `#${id}`;
    });

    return names.join(", ") + (recipients.length > 3 ? "..." : "");
};

const formatDateTime = (date) => {
    if (!date) return null;
    const d = new Date(date);
    return d.toISOString().slice(0, 19).replace("T", " ");
};

// Debounced search
let searchTimeout;
const debouncedSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadAnnouncements();
    }, 300);
};

// Polling
let pollInterval;

watch(manageVisible, (newVal) => {
    if (newVal) {
        loadAnnouncements();
        loadEmployees();
    }
});

onMounted(() => {
    loadEmployees();

    // Initial check
    checkUserAnnouncements();

    // Poll every minute
    pollInterval = setInterval(checkUserAnnouncements, 60000);

    // Check when tab becomes visible
    document.addEventListener("visibilitychange", () => {
        if (!document.hidden) {
            checkUserAnnouncements();
        }
    });
});

onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});
</script>

<style src="./AnnouncementModalGlobal.css"></style>
<style scoped src="./AnnouncementModal.css"></style>
