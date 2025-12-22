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

                    <!-- Profile -->
                    <Button
                        icon="pi pi-user"
                        label="Profile"
                        @click="profileVisible = true"
                        severity="secondary"
                        text
                        class="nav-button with-label"
                    />

                    <!-- Settings -->
                    <Button
                        icon="pi pi-cog"
                        label="Settings"
                        @click="settingsVisible = true"
                        severity="secondary"
                        text
                        class="nav-button with-label"
                    />

                    <!-- Logout -->
                    <Button
                        icon="pi pi-sign-out"
                        label="Logout"
                        @click="showLogoutModal"
                        severity="danger"
                        text
                        class="nav-button with-label"
                    />
                </div>
            </div>

            <!-- Mobile: Second Row - Search -->
            <div class="navbar-search-mobile">
                <Searching @search="handleSearch" />
            </div>
        </div>
    </nav>
    <Sidebar v-model:visible="visible" />
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
</template>

<script>
import Button from "primevue/button";
import Badge from "primevue/badge";
import Searching from "../../page/searching/searching.vue";
import Sidebar from "../Sidebar.vue";

import AnnouncementModal from "../AnnouncementModal/AnnouncementModal.vue";
import BreakModal from "../BreakModal/BreakModal.vue";
import NotificationModal from "../NotificationModal/NotificationModal.vue";
import ProfileModal from "../ProfileModal/ProfileModal.vue";
import SettingsModal from "../SettingsModal/SettingsModal.vue";

import { OverlayBadge } from "primevue";

export default {
    name: "Navbar",
    components: {
        Button,
        Badge,
        Searching,
        Sidebar,
        NotificationModal,
        ProfileModal,
        SettingsModal,
        AnnouncementModal,
        BreakModal,
        OverlayBadge,
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
            announcementVisible: false,
            breakVisible: false,
            kanbanCount: 0,
            userId: null,
        };
    },
    mounted() {
        this.logo = this.getSessionData("logo");
        this.siteTitle = this.getSessionData("site_title", "IMS");
        this.userId = this.getUserId();
        this.loadNotificationCount();
        setInterval(() => {
            this.kanbanCount = window.kanbanMentionedCount || 0;
        }, 1000);
    },
    methods: {
        getUserId() {
            // Try multiple sources
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

        showLogoutModal() {
            if (typeof showLogoutModal === "function") {
                showLogoutModal();
            }
        },

        goToKanban() {
            window.loadContent("kanban");
        },

        // ADD THIS NEW METHOD
        goToHistory() {
            window.loadContent("history");
        },
    },
};
</script>

<style scoped src="./Navbar.css"></style>
