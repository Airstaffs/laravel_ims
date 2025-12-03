<template>
    <Dialog
        v-model:visible="isVisible"
        modal
        :closable="true"
        :draggable="false"
        class="notification-modal"
        :style="{
            width: isMobile ? '100vw' : '900px',
            height: isMobile ? '100vh' : 'auto',
        }"
        :contentStyle="{
            padding: 0,
            height: isMobile ? '100vh' : 'auto',
            maxHeight: isMobile ? '100vh' : '80vh',
            borderRadius: isMobile ? '0' : '12px',
            overflow: 'hidden',
        }"
        :position="isMobile ? 'center' : 'center'"
        @show="onModalShow"
        @hide="onModalHide"
    >
        <template #header>
            <div class="notification-header">
                <h3>
                    <i class="pi pi-bell"></i>
                    Notifications
                </h3>
            </div>
        </template>

        <div class="notification-content">
            <!-- Expanded View (List) -->
            <div v-if="!selectedNotification" class="expanded-view">
                <!-- Filter Section -->
                <div class="filter-section">
                    <label for="moduleFilter">Filter by Module:</label>
                    <Select
                        v-model="selectedModule"
                        :options="moduleOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="All"
                        class="module-filter"
                        @change="filterNotifications"
                    />
                </div>

                <!-- Empty State -->
                <div
                    v-if="filteredNotifications.length === 0"
                    class="empty-state"
                >
                    <i class="pi pi-bell-slash"></i>
                    <h4>No notifications</h4>
                    <p v-if="notifications.length === 0">
                        You're all caught up!
                    </p>
                    <p v-else>No notifications match the selected filter.</p>
                    <small
                        v-if="notifications.length > 0"
                        style="color: #6c757d; margin-top: 0.5rem"
                    >
                        Total: {{ notifications.length }} | Showing:
                        {{ filteredNotifications.length }} | Module:
                        {{ selectedModule || "All" }}
                    </small>
                </div>

                <!-- Desktop Table View -->
                <div v-else class="notification-table">
                    <DataTable
                        :value="filteredNotifications"
                        :rowClass="getRowClass"
                        @row-click="onNotificationClick"
                        stripedRows
                        size="small"
                        responsiveLayout="stack"
                        breakpoint="768px"
                    >
                        <Column
                            field="module"
                            header="Module"
                            style="min-width: 120px"
                        ></Column>
                        <Column
                            field="title"
                            header="Title"
                            style="min-width: 200px"
                        >
                            <template #body="{ data }">
                                <div
                                    class="text-truncate"
                                    style="max-width: 260px"
                                >
                                    {{ data.title }}
                                </div>
                            </template>
                        </Column>
                        <Column
                            field="subtitle"
                            header="Subtitle"
                            style="min-width: 180px"
                        >
                            <template #body="{ data }">
                                <div
                                    class="text-truncate"
                                    style="max-width: 220px"
                                >
                                    {{ data.subtitle || "" }}
                                </div>
                            </template>
                        </Column>
                        <Column
                            field="content"
                            header="Content"
                            style="min-width: 280px"
                        >
                            <template #body="{ data }">
                                <div
                                    class="text-truncate"
                                    style="max-width: 360px"
                                >
                                    {{ data.content || "" }}
                                </div>
                            </template>
                        </Column>
                        <Column
                            field="severity"
                            header="Severity"
                            style="min-width: 100px"
                        >
                            <template #body="{ data }">
                                <Tag
                                    :severity="getSeverity(data.severity)"
                                    :value="data.severity || '—'"
                                />
                            </template>
                        </Column>
                        <Column
                            field="notif_created_at"
                            header="Date"
                            style="min-width: 180px"
                        >
                            <template #body="{ data }">
                                {{ formatDate(data.notif_created_at) }}
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <!-- Mobile Card View - REMOVED -->
            </div>

            <!-- Detail View (Single Notification) -->
            <div v-else class="detail-view">
                <div class="detail-table">
                    <table>
                        <tbody>
                            <tr>
                                <th>Module</th>
                                <td>{{ selectedNotification.module }}</td>
                            </tr>
                            <tr>
                                <th>Title</th>
                                <td>{{ selectedNotification.title }}</td>
                            </tr>
                            <tr>
                                <th>Subtitle</th>
                                <td>
                                    {{ selectedNotification.subtitle || "" }}
                                </td>
                            </tr>
                            <tr>
                                <th>Content</th>
                                <td>
                                    {{ selectedNotification.content || "" }}
                                </td>
                            </tr>
                            <tr>
                                <th>Severity</th>
                                <td>
                                    <Tag
                                        :severity="
                                            getSeverity(
                                                selectedNotification.severity
                                            )
                                        "
                                        :value="selectedNotification.severity"
                                    />
                                </td>
                            </tr>
                            <tr>
                                <th>Date</th>
                                <td>
                                    {{
                                        formatDate(
                                            selectedNotification.notif_created_at
                                        )
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="detail-actions">
                    <Button
                        label="Back"
                        @click="backToList"
                        severity="secondary"
                        size="small"
                        icon="pi pi-arrow-left"
                    />

                    <Button
                        v-if="selectedNotification.link_data"
                        :label="getLinkButtonLabel()"
                        @click="handleLinkAction"
                        severity="primary"
                        size="small"
                        icon="pi pi-external-link"
                    />
                </div>
            </div>
        </div>
    </Dialog>
</template>

<script>
import { defineComponent } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Select from "primevue/select";
import Tag from "primevue/tag";

export default defineComponent({
    name: "NotificationModal",
    components: {
        Dialog,
        Button,
        DataTable,
        Column,
        Select,
        Tag,
    },
    props: {
        visible: {
            type: Boolean,
            default: false,
        },
        userId: {
            type: [String, Number],
            default: null,
        },
    },
    emits: ["update:visible", "update-badge", "open-modal", "custom-action"],
    data() {
        return {
            notifications: [],
            filteredNotifications: [],
            selectedNotification: null,
            selectedModule: "",
            moduleOptions: [{ label: "All", value: "" }],
            userId: null,
            csrfToken: null,
            updateInterval: null,
        };
    },
    computed: {
        isVisible: {
            get() {
                return this.visible;
            },
            set(value) {
                this.$emit("update:visible", value);
            },
        },
        isMobile() {
            return window.innerWidth <= 768;
        },
    },
    mounted() {
        // Get userId from props or fallback to meta/window
        if (!this.userId) {
            this.userId =
                window.userId ||
                window.appData?.userId ||
                document.querySelector('meta[name="user-id"]')?.content;
        }

        // Get CSRF token
        this.csrfToken =
            window.appData?.csrfToken ||
            document.querySelector('meta[name="csrf-token"]')?.content;

        // Fetch initial data
        this.updateBadge();

        // Start polling for badge updates
        this.startBadgePolling();
    },
    beforeUnmount() {
        this.stopBadgePolling();
    },
    methods: {
        async fetchNotifications() {
            if (!this.userId) {
                console.error("User ID not found");
                return;
            }

            try {
                const response = await fetch(
                    `/notifications/user/${this.userId}`
                );

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                console.log("Fetched notifications:", data); // Debug log
                console.log("Total notifications:", data.length); // Debug log
                console.log(
                    "Read notifications:",
                    data.filter((n) => n.read_status === "read").length
                ); // Debug log
                console.log(
                    "Unread notifications:",
                    data.filter((n) => n.read_status === "unread").length
                ); // Debug log

                this.notifications = data;
                this.filterNotifications();
                this.extractModules();

                console.log(
                    "Filtered notifications:",
                    this.filteredNotifications
                ); // Debug log
            } catch (error) {
                console.error("Error fetching notifications:", error);
                // Show empty state on error
                this.notifications = [];
                this.filteredNotifications = [];
            }
        },

        async updateBadge() {
            if (!this.userId) {
                console.error("User ID not found");
                return;
            }

            try {
                const response = await fetch(
                    `/notifications/unread-count/${this.userId}`
                );

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                console.log("Badge count:", data.unread_count); // Debug log

                // Emit event to update badge in parent component
                this.$emit("update-badge", data.unread_count);
            } catch (error) {
                console.error("Error updating badge:", error);
            }
        },

        async markAsRead(notifId) {
            try {
                const response = await fetch("/notifications/mark-read", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": this.csrfToken,
                    },
                    body: JSON.stringify({
                        notif_id: notifId,
                        user_id: this.userId,
                    }),
                });

                const data = await response.json();

                if (data.success) {
                    await this.updateBadge();
                }

                return data;
            } catch (error) {
                console.error("Error marking as read:", error);
            }
        },

        extractModules() {
            const modules = [
                ...new Set(this.notifications.map((n) => n.module)),
            ];
            this.moduleOptions = [
                { label: "All", value: "" },
                ...modules.map((m) => ({ label: m, value: m })),
            ];
        },

        filterNotifications() {
            if (!this.selectedModule) {
                this.filteredNotifications = this.notifications;
            } else {
                this.filteredNotifications = this.notifications.filter(
                    (n) => n.module === this.selectedModule
                );
            }
        },

        getSeverity(severity) {
            const s = String(severity || "").toLowerCase();

            if (s === "high" || s === "critical") return "danger";
            if (s === "medium") return "warn";
            if (s === "low") return "success";
            return "secondary";
        },

        getRowClass(data) {
            // Only highlight unread notifications
            return data.read_status === "unread"
                ? "notif-unread-row"
                : "notif-read-row";
        },

        formatDate(dateString) {
            try {
                return new Date(dateString).toLocaleString();
            } catch {
                return dateString || "—";
            }
        },

        async onNotificationClick(event) {
            const notification = event.data;

            // Mark as read
            await this.markAsRead(notification.notif_id);

            // Parse link_data if it exists
            if (notification.link_data) {
                try {
                    notification.parsedLinkData = JSON.parse(
                        notification.link_data
                    );
                } catch (e) {
                    console.error("Error parsing link_data:", e);
                    notification.parsedLinkData = null;
                }
            }

            // Show detail view
            this.selectedNotification = notification;
        },

        getLinkButtonLabel() {
            if (!this.selectedNotification?.parsedLinkData) return "View";

            const type = this.selectedNotification.parsedLinkData.type;

            if (type === "redirect") return "Go to Link";
            if (type === "modal") return "Open Details";
            return "View";
        },

        handleLinkAction() {
            if (!this.selectedNotification?.parsedLinkData) return;

            const linkData = this.selectedNotification.parsedLinkData;

            switch (linkData.type) {
                case "redirect":
                    this.handleRedirect(linkData);
                    break;

                case "modal":
                    this.handleModal(linkData);
                    break;

                case "custom":
                    this.handleCustom(linkData);
                    break;

                default:
                    console.warn("Unknown link type:", linkData.type);
            }
        },

        handleRedirect(linkData) {
            const method = linkData.method || "GET";
            const url = linkData.url;

            if (method === "GET") {
                // Navigate to URL
                window.location.href = url;
            } else if (method === "POST") {
                // Create a form and submit it
                const form = document.createElement("form");
                form.method = "POST";
                form.action = url;

                // Add CSRF token
                const csrfInput = document.createElement("input");
                csrfInput.type = "hidden";
                csrfInput.name = "_token";
                csrfInput.value = this.csrfToken;
                form.appendChild(csrfInput);

                // Add payload fields
                if (linkData.payload) {
                    Object.keys(linkData.payload).forEach((key) => {
                        const input = document.createElement("input");
                        input.type = "hidden";
                        input.name = key;
                        input.value = linkData.payload[key];
                        form.appendChild(input);
                    });
                }

                document.body.appendChild(form);
                form.submit();
            }
        },

        handleModal(linkData) {
            // Emit event to parent component to open specific modal
            this.$emit("open-modal", {
                modalId: linkData.modal_id,
                data: linkData.data,
            });

            // Close notification modal
            this.isVisible = false;
        },

        handleCustom(linkData) {
            // Emit custom event for parent to handle
            this.$emit("custom-action", linkData);
        },

        async backToList() {
            this.selectedNotification = null;

            // Reload notifications to refresh read status
            await this.fetchNotifications();
        },

        async onModalShow() {
            await this.updateBadge();
            await this.fetchNotifications();
        },

        onModalHide() {
            // Reset to expanded view
            this.selectedNotification = null;
        },

        startBadgePolling() {
            // Update badge every 30 seconds
            this.updateInterval = setInterval(() => {
                this.updateBadge();
            }, 30000);
        },

        stopBadgePolling() {
            if (this.updateInterval) {
                clearInterval(this.updateInterval);
            }
        },
    },
});
</script>

<style src="./NotificationModalGlobal.css"></style>
<style scoped src="./NotificationModal.css"></style>
