<template>
    <nav class="top-navbar">
        <div class="navbar-container">
            <!-- Mobile: First Row -->
            <div class="navbar-row-mobile">
                <!-- Burger + Logo -->
                <div class="navbar-left">
                    <Button
                        icon="pi pi-bars"
                        class="border-0"
                        size="small"
                        @click="visible = true"
                        style="background-color: #007bff"
                    />

                    <div class="navbar-brand">
                        <img
                            v-if="logo"
                            :src="logo"
                            alt="Logo"
                            class="brand-logo"
                        />
                        <span class="brand-title">{{ siteTitle }}</span>
                    </div>
                </div>

                <!-- Mobile Icons -->
                <div class="navbar-mobile">
                    <Button
                        icon="pi pi-clock"
                        @click="openSystemClockInOut"
                        severity="secondary"
                        text
                        rounded
                        size="small"
                        aria-label="Clock"
                        v-tooltip.bottom="'Time Logs'"
                    />
                    <!-- ADD HISTORY BUTTON FOR MOBILE -->
                    <Button
                        icon="pi pi-history"
                        @click="goToHistory"
                        severity="secondary"
                        text
                        rounded
                        size="small"
                        aria-label="History"
                        v-tooltip.bottom="'History Tracking'"
                    />

                    <div class="notification-wrapper">
                        <Button
                            icon="pi pi-list-check"
                            @click="goToKanban('kanban')"
                            severity="secondary"
                            text
                            class="nav-button with-label"
                        />
                        <Badge
                            v-if="kanbanCount > 0"
                            :value="kanbanCount"
                            severity="danger"
                            class="kanban-badge-mobile"
                        />
                    </div>

                    <div class="notification-wrapper">
                        <Button
                            icon="pi pi-bell"
                            @click="openNotificationModal"
                            severity="secondary"
                            text
                            rounded
                            size="small"
                            aria-label="Notifications"
                        />

                        <Badge
                            v-if="notificationCount > 0"
                            :value="notificationCount"
                            severity="danger"
                            class="notification-badge-mobile"
                        />
                    </div>

                    <Button
                        icon="pi pi-user"
                        @click="openProfileModal"
                        severity="secondary"
                        text
                        rounded
                        size="small"
                        aria-label="Profile"
                    />

                    <Button
                        icon="pi pi-cog"
                        @click="openSettingModal"
                        severity="secondary"
                        text
                        rounded
                        size="small"
                        aria-label="Settings"
                    />

                    <Button
                        icon="pi pi-sign-out"
                        @click="showLogoutModal"
                        severity="danger"
                        text
                        rounded
                        size="small"
                        aria-label="Logout"
                    />
                </div>
            </div>

            <!-- Desktop: All in one row -->
            <div class="navbar-row-desktop">
                <!-- Left Section -->
                <div class="navbar-left">
                    <Button
                        icon="pi pi-bars"
                        class="border-0"
                        size="small"
                        @click="visible = true"
                        style="background-color: #007bff"
                    />

                    <div class="navbar-brand">
                        <img
                            v-if="logo"
                            :src="logo"
                            alt="Logo"
                            class="brand-logo"
                        />
                        <span class="brand-title">{{ siteTitle }}</span>
                    </div>
                </div>

                <!-- Center: Search -->
                <div class="navbar-center">
                    <Searching @search="handleSearch" />
                </div>

                <!-- Right Section -->
                <div class="navbar-right">
                    <!---SYSTEM CLOCK IN--->
                    <Button
                        icon="pi pi-clock"
                        label="Time Logs"
                        @click="openSystemClockInOut"
                        severity="secondary"
                        text
                        class="nav-button with-label"
                    />

                    <!-- ADD HISTORY BUTTON FOR DESKTOP -->
                    <Button
                        icon="pi pi-history"
                        label="History"
                        @click="goToHistory"
                        severity="secondary"
                        text
                        class="nav-button with-label"
                    />

                    <!-- Announcements -->
                    <div class="notification-wrapper">
                        <Button
                            icon="pi pi-list-check"
                            label="Todo List"
                            @click="goToKanban('kanban')"
                            severity="secondary"
                            text
                            class="nav-button with-label"
                        />

                        <Badge
                            v-if="kanbanCount > 0"
                            :value="kanbanCount"
                            severity="danger"
                            class="notification-badge-desktop"
                        />
                    </div>

                    <Button
                        icon="pi pi-megaphone"
                        label="Announcements"
                        @click="openAnnouncementModal"
                        severity="secondary"
                        text
                        class="nav-button with-label"
                    />

                    <!-- Break -->
                    <Button
                        icon="pi pi-pause-circle"
                        label="Break"
                        @click="openBreakModal"
                        severity="secondary"
                        text
                        class="nav-button with-label"
                    />

                    <!-- Notifications -->
                    <div class="notification-wrapper">
                        <Button
                            icon="pi pi-bell"
                            label="Notifications"
                            @click="openNotificationModal"
                            severity="secondary"
                            text
                            class="nav-button with-label"
                        />
                        <Badge
                            v-if="notificationCount > 0"
                            :value="notificationCount"
                            severity="danger"
                            class="notification-badge-desktop"
                        />
                    </div>

                    <!-- User Dropdown Menu -->
                    <div class="user-menu-wrapper">
                        <Button
                            icon="pi pi-user"
                            label="Account"
                            @click="toggleUserMenu"
                            severity="secondary"
                            text
                            class="nav-button with-label"
                            aria-haspopup="true"
                            aria-controls="user_menu"
                        />
                        <Menu
                            ref="userMenu"
                            id="user_menu"
                            :model="userMenuItems"
                            :popup="true"
                        >
                            <template #item="{ item, props }">
                                <!-- Separator -->
                                <template v-if="item.separator">
                                    <hr class="my-1" />
                                </template>

                                <!-- Regular item -->
                                <a
                                    v-else
                                    v-bind="props.action"
                                    class="d-flex align-items-center justify-content-between px-3 py-2 text-decoration-none text-dark"
                                >
                                    <div
                                        class="d-flex align-items-center gap-2"
                                    >
                                        <i :class="item.icon"></i>
                                        <span>{{ item.label }}</span>
                                    </div>
                                    <Badge
                                        v-if="item.badge"
                                        :value="item.badge"
                                        severity="danger"
                                        style="font-size: 11px"
                                    />
                                </a>
                            </template>
                        </Menu>
                    </div>
                </div>
            </div>

            <!-- Mobile: Second Row - Search -->
            <div class="navbar-search-mobile">
                <Searching @search="handleSearch" />
            </div>
        </div>
    </nav>
    <Sidebar v-model:visible="visible" />
    <SystemInOutModal v-model:visible="systemInOutVisible" />
    <ProfileModal v-model:visible="profileVisible" />
    <SettingsModal v-model:visible="settingsVisible" />
    <NotificationModal
        v-model:visible="notificationVisible"
        :user-id="userId"
        @update-badge="updateNotificationCount"
        @open-modal="handleModalOpen"
        @custom-action="handleCustomAction"
    />
    <AnnouncementModal v-model:visible="announcementVisible" />
    <BreakModal v-model:visible="breakVisible" />

    <EwhModal
        v-model:visible="ewhModalVisible"
        @update-ewh-count="fetchNewCounts"
    />
    <PayrollModal
        v-model:visible="payrollModalVisible"
        @update-payroll-count="fetchNewCounts"
    />
</template>

<script>
import Button from "primevue/button";
import Badge from "primevue/badge";
import Menu from "primevue/menu";
import Searching from "../../page/searching/searching.vue";
import Sidebar from "../Sidebar.vue";

import AnnouncementModal from "../AnnouncementModal/AnnouncementModal.vue";
import BreakModal from "../BreakModal/BreakModal.vue";
import NotificationModal from "../NotificationModal/NotificationModal.vue";
import ProfileModal from "../ProfileModal/ProfileModal.vue";
import SettingsModal from "../SettingsModal/SettingsModal.vue";

import { OverlayBadge } from "primevue";
import SystemInOutModal from "../SystemInOutModal/SystemInOutModal.vue";

import EwhModal from "../../page/hr/modals/EWHModal.vue";
import PayrollModal from "../../page/hr/modals/PayrollModal.vue";

export default {
    name: "Navbar",
    components: {
        Button,
        Badge,
        Menu,
        Searching,
        Sidebar,
        NotificationModal,
        ProfileModal,
        SettingsModal,
        AnnouncementModal,
        SystemInOutModal,
        BreakModal,
        OverlayBadge,
        EwhModal,
        PayrollModal,
    },
    data() {
        return {
            logo: null,
            siteTitle: "IMS",
            visible: false,
            notificationVisible: false,
            notificationCount: 0,
            profileVisible: false,
            settingsVisible: false,
            systemInOutVisible: false,
            announcementVisible: false,
            breakVisible: false,
            kanbanCount: 0,
            userId: null,

            ewhModalVisible: false,
            payrollModalVisible: false,
            ewhNewCount: 0,
            payrollNewCount: 0,
        };
    },
    computed: {
        userMenuItems() {
            return [
                {
                    label: "My Payroll",
                    icon: "pi pi-money-bill",
                    badge:
                        this.payrollNewCount > 0 ? this.payrollNewCount : null,
                    command: () => {
                        this.openPayrollModal();
                    },
                },
                {
                    label: "My EWH",
                    icon: "pi pi-file-edit",
                    badge: this.ewhNewCount > 0 ? this.ewhNewCount : null,
                    command: () => {
                        this.openEwhModal();
                    },
                },
                {
                    label: "Profile",
                    icon: "pi pi-user",
                    command: () => {
                        this.openProfileModal();
                    },
                },
                {
                    label: "Settings",
                    icon: "pi pi-cog",
                    command: () => {
                        this.openSettingModal();
                    },
                },
                { separator: true },
                {
                    label: "Logout",
                    icon: "pi pi-sign-out",
                    class: "menu-item-logout",
                    command: () => {
                        this.showLogoutModal();
                    },
                },
            ];
        },
    },
    mounted() {
        this.logo = this.getSessionData("logo");
        this.siteTitle = this.getSessionData("site_title", "IMS");
        this.userId = this.getUserId();
        this.loadNotificationCount();
        setInterval(() => {
            this.kanbanCount = window.kanbanMentionedCount || 0;
        }, 1000);

        this.fetchNewCounts();
        setInterval(() => {
            this.fetchNewCounts();
        }, 60000);
    },
    methods: {
        getUserId() {
            return (
                document.querySelector('meta[name="user-id"]')?.content ||
                window.userId ||
                window.appData?.userId
            );
        },

        getSessionData(key, defaultValue = null) {
            const meta = document.querySelector(`meta[name="session-${key}"]`);
            return meta
                ? meta.content
                : window.sessionData?.[key] || defaultValue;
        },

        loadNotificationCount() {
            const badge =
                document.getElementById("notifBadgeMobile") ||
                document.getElementById("notifBadgeDesktop");
            if (badge) {
                this.notificationCount = parseInt(badge.textContent) || 0;
            }
        },

        toggleUserMenu(event) {
            this.$refs.userMenu.toggle(event);
        },

        toggleSidebar() {
            const sidebar = document.getElementById("sidebar");
            if (sidebar) {
                sidebar.classList.toggle("visible");
            }
        },

        handleSearch(query) {
            this.$emit("search", query);
        },

        openAnnouncementModal() {
            this.announcementVisible = true;
        },

        openBreakModal() {
            this.breakVisible = true;
        },

        openNotificationModal() {
            this.notificationVisible = true;
        },

        updateNotificationCount(count) {
            this.notificationCount = count;
        },

        handleModalOpen(payload) {
            console.log("Open modal:", payload.modalId, payload.data);

            if (payload.modalId === "order-details") {
                this.orderDetailsVisible = true;
                this.orderData = payload.data;
            }
        },

        handleCustomAction(linkData) {
            console.log("Custom action:", linkData);
        },

        openProfileModal() {
            this.profileVisible = true;
        },

        openSettingModal() {
            this.settingsVisible = true;
        },

        openSystemClockInOut() {
            this.systemInOutVisible = true;
        },

        showLogoutModal() {
            if (typeof showLogoutModal === "function") {
                showLogoutModal();
            }
        },

        goToKanban() {
            window.loadContent("kanban");
        },

        goToHistory() {
            window.loadContent("history");
        },

        openEwhModal() {
            this.ewhModalVisible = false;
            this.$nextTick(() => {
                this.ewhModalVisible = true;
            });
        },

        openPayrollModal() {
            this.payrollModalVisible = false;
            this.$nextTick(() => {
                this.payrollModalVisible = true;
            });
        },

        async fetchNewCounts() {
            try {
                const [ewhRes, payrollRes] = await Promise.all([
                    axios.get("/hr/ewh/new-count"),
                    axios.get("/hr/payslips/new-count"),
                ]);
                this.ewhNewCount = ewhRes.data.count ?? 0;
                this.payrollNewCount = payrollRes.data.count ?? 0;
            } catch (error) {
                console.error("Error fetching new counts:", error);
                this.ewhNewCount = 0;
                this.payrollNewCount = 0;
            }
        },
    },
};
</script>

<style scoped src="./Navbar.css"></style>

<style scoped>
/* User Menu Wrapper */
.user-menu-wrapper {
    position: relative;
}

/* Dropdown Menu Styling */
:deep(.p-menu) {
    min-width: 200px;
    margin-top: 0.5rem;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

:deep(.p-menu .p-menuitem-link) {
    padding: 0.75rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: all 0.2s ease;
    color: #334155;
    font-weight: 500;
}

:deep(.p-menu .p-menuitem-link:hover) {
    background: #f1f5f9;
}

:deep(.p-menu .p-menuitem-icon) {
    font-size: 1rem;
    color: #64748b;
}

/* Logout Item Special Styling */
:deep(.p-menu .menu-item-logout .p-menuitem-link) {
    color: #ef4444;
    border-top: 1px solid #e2e8f0;
    margin-top: 0.25rem;
}

:deep(.p-menu .menu-item-logout .p-menuitem-link:hover) {
    background: #fee2e2;
}

:deep(.p-menu .menu-item-logout .p-menuitem-icon) {
    color: #ef4444;
}

/* Separator */
:deep(.p-menu .p-menuitem-separator) {
    margin: 0.5rem 0;
    border-color: #e2e8f0;
}
</style>
