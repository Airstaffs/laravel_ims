<template>
    <nav class="top-navbar">
        <div class="navbar-container">
            <!-- Mobile: First Row -->
            <div class="navbar-row-mobile">
                <!-- Burger + Logo -->
                <div class="navbar-left">
                    <!-- <Button icon="pi pi-bars" @click="toggleSidebar" severity="secondary" text rounded
                        class="burger-menu" aria-label="Toggle Menu" /> -->
                    <Button icon="pi pi-bars" class="border-0" size="small" @click="visible = true"
                        style="background-color: #007bff" />

                    <div class="navbar-brand">
                        <img v-if="logo" :src="logo" alt="Logo" class="brand-logo" />
                        <span class="brand-title">{{ siteTitle }}</span>
                    </div>
                </div>

                <!-- Mobile Icons -->
                <div class="navbar-mobile">

                    <div class="notification-wrapper">
                        <Button icon="pi pi-list-check" @click="goToKanban('kanban')" severity="secondary" text
                            class="nav-button with-label" />
                        <Badge v-if="kanbanCount > 0" :value="kanbanCount" severity="danger"
                            class="kanban-badge-mobile" />
                    </div>



                    <div class="notification-wrapper">
                        <Button icon="pi pi-bell" @click="openNotificationModal" severity="secondary" text rounded
                            size="small" aria-label="Notifications" />
                        <Badge v-if="notificationCount > 0" :value="notificationCount" severity="danger"
                            class="notification-badge-mobile" />
                    </div>

                    <Button icon="pi pi-user" @click="openProfileModal" severity="secondary" text rounded size="small"
                        aria-label="Profile" />

                    <Button icon="pi pi-cog" @click="openSettingModal" severity="secondary" text rounded size="small"
                        aria-label="Settings" />

                    <Button icon="pi pi-sign-out" @click="showLogoutModal" severity="danger" text rounded size="small"
                        aria-label="Logout" />
                </div>
            </div>

            <!-- Desktop: All in one row -->
            <div class="navbar-row-desktop">
                <!-- Left Section -->
                <div class="navbar-left">
                    <!-- <Button icon="pi pi-bars" @click="toggleSidebar" severity="secondary" text rounded
                        class="burger-menu" aria-label="Toggle Menu" /> -->

                    <Button icon="pi pi-bars" class="border-0" size="small" @click="visible = true"
                        style="background-color: #007bff" />

                    <div class="navbar-brand">
                        <img v-if="logo" :src="logo" alt="Logo" class="brand-logo" />
                        <span class="brand-title">{{ siteTitle }}</span>
                    </div>
                </div>

                <!-- Center: Search -->
                <div class="navbar-center">
                    <Searching @search="handleSearch" />
                </div>

                <!-- Right Section -->
                <div class="navbar-right">
                    <!-- Announcements -->

                    <div class="notification-wrapper">
                        <Button icon="pi pi-list-check" label="Todo List" @click="goToKanban('kanban')"
                            severity="secondary" text class="nav-button with-label" />

                        <Badge v-if="kanbanCount > 0" :value="kanbanCount" severity="danger"
                            class="notification-badge-desktop" />
                    </div>


                    <Button icon="pi pi-megaphone" label="Announcements" @click="openAnnouncementModal"
                        severity="secondary" text class="nav-button with-label" />

                    <!-- Break -->
                    <Button icon="pi pi-pause-circle" label="Break" @click="openBreakModal" severity="secondary" text
                        class="nav-button with-label" />

                    <!-- Notifications -->
                    <div class="notification-wrapper">
                        <Button icon="pi pi-bell" label="Notifications" @click="openNotificationModal"
                            severity="secondary" text class="nav-button with-label" />
                        <Badge v-if="notificationCount > 0" :value="notificationCount" severity="danger"
                            class="notification-badge-desktop" />
                    </div>

                    <!-- Profile -->
                    <Button icon="pi pi-user" label="Profile" @click="profileVisible = true" severity="secondary" text
                        class="nav-button with-label" />

                    <!-- Settings -->
                    <Button icon="pi pi-cog" label="Settings" @click="settingsVisible = true" severity="secondary" text
                        class="nav-button with-label" />

                    <!-- Logout -->
                    <Button icon="pi pi-sign-out" label="Logout" @click="showLogoutModal" severity="danger" text
                        class="nav-button with-label" />
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
    <NotificationModal v-model:visible="notificationVisible" :user-id="userId" @update-badge="updateNotificationCount"
        @open-modal="handleModalOpen" @custom-action="handleCustomAction" />
</template>

<script>
import Button from "primevue/button";
import Badge from "primevue/badge";
import Searching from "../page/searching/searching.vue";
import Sidebar from "./Sidebar.vue";
import NotificationModal from "./NotificationModal.vue";
import ProfileModal from "./ProfileModal.vue";
import SettingsModal from "./SettingsModal.vue";
import AnnouncementModal from "./AnnouncementModal.vue";
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

        // openAnnouncementModal() {
        //     if (typeof ANN !== "undefined" && ANN.onOpenManage) {
        //         ANN.onOpenManage();
        //     }
        //     const modal = new bootstrap.Modal(
        //         document.getElementById("annManageModal")
        //     );
        //     modal.show();
        // },

        openBreakModal(event) {
            if (event) event.preventDefault();
            if (typeof openBreakModal === "function") {
                openBreakModal(event);
            }
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
    },
};
</script>

<style scoped>
/* Base Navbar Styles */
.top-navbar {
    background-color: var(--navbar-bg, #007bff);
    padding: 0.75rem 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.navbar-container {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    max-width: 100%;
}

/* Desktop Row - Space Between Layout */
.navbar-row-desktop {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 2rem;
    width: 100%;
}

/* Mobile Rows - Hidden on Desktop */
.navbar-row-mobile {
    display: none;
}

.navbar-search-mobile {
    display: none;
}

/* Left Section - Stays Left */
.navbar-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    justify-self: start;
}

.burger-menu {
    color: white !important;
}

.burger-menu:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
}

.navbar-brand {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: white;
}

.brand-logo {
    max-width: 40px;
    max-height: 40px;
    border-radius: 8px;
    object-fit: contain;
}

.brand-title {
    font-size: 1.25rem;
    font-weight: 600;
    white-space: nowrap;
}

/* Center Section - Stays Centered */
.navbar-center {
    display: flex;
    justify-content: center;
    justify-self: center;
    width: 100%;
    max-width: 600px;
}

/* Right Section - Stays Right */
.navbar-right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    justify-self: end;
}

.nav-button {
    color: white !important;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.nav-button:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
    transform: translateY(-2px);
}

.nav-button :deep(.p-button-label) {
    color: white;
}

.nav-button :deep(.p-button-icon) {
    color: white;
}

/* Notification Badge */
.notification-wrapper {
    position: relative;
}

.notification-badge-desktop {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 20px;
    height: 20px;
}

.notification-badge-mobile {
    position: absolute;
    top: -2px;
    right: -2px;
    min-width: 18px;
    height: 18px;
    font-size: 10px;
}

.kanban-badge-mobile {
    position: absolute;
    top: -4px;
    right: -2px;
    min-width: 18px;
    height: 18px;
    font-size: 10px;
}

/* Mobile Icons - Hidden on Desktop */
.navbar-mobile {
    display: none;
}

/* ==================== RESPONSIVE BREAKPOINTS ==================== */

/* Large Tablets and Below (≤1200px) - Hide labels */
@media (max-width: 1200px) {
    .nav-button.with-label :deep(.p-button-label) {
        display: none;
    }

    .navbar-row-desktop {
        gap: 1.5rem;
    }

    .navbar-center {
        max-width: 500px;
    }
}

/* Tablets (≤992px) */
@media (max-width: 992px) {
    .navbar-row-desktop {
        gap: 1rem;
    }

    .navbar-center {
        max-width: 400px;
    }

    .navbar-right {
        gap: 0.25rem;
    }

    .brand-title {
        font-size: 1.1rem;
    }
}

/* Mobile (≤768px) - Switch to mobile layout */
@media (max-width: 768px) {
    .top-navbar {
        padding: 0.5rem 1rem;
    }

    .navbar-container {
        gap: 0.5rem;
    }

    /* Hide desktop row */
    .navbar-row-desktop {
        display: none;
    }

    /* Show mobile rows */
    .navbar-row-mobile {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
    }

    .navbar-search-mobile {
        display: flex;
        width: 100%;
    }

    /* Show mobile icons */
    .navbar-mobile {
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .navbar-mobile .p-button {
        color: white !important;
        min-width: 32px;
        width: 32px;
        height: 32px;
    }

    .navbar-mobile .p-button:hover {
        background-color: rgba(255, 255, 255, 0.1) !important;
    }

    .brand-title {
        font-size: 1rem;
    }

    .brand-logo {
        max-width: 35px;
        max-height: 35px;
    }
}

/* Small Mobile (≤576px) */
@media (max-width: 576px) {
    .top-navbar {
        padding: 0.5rem 0.75rem;
    }

    .navbar-left {
        gap: 0.5rem;
    }

    .brand-title {
        display: none;
    }

    .navbar-mobile {
        gap: 0.125rem;
    }

    .navbar-mobile .p-button {
        min-width: 28px;
        width: 28px;
        height: 28px;
    }

    .navbar-mobile :deep(.p-button-icon) {
        font-size: 0.875rem;
    }

    .brand-logo {
        max-width: 30px;
        max-height: 30px;
    }

    .burger-menu {
        min-width: 32px !important;
        width: 32px !important;
        height: 32px !important;
    }
}

/* Extra Small Mobile (≤400px) */
@media (max-width: 400px) {
    .top-navbar {
        padding: 0.4rem 0.5rem;
    }

    .navbar-mobile .p-button {
        min-width: 26px;
        width: 26px;
        height: 26px;
    }

    .navbar-mobile :deep(.p-button-icon) {
        font-size: 0.75rem;
    }
}
</style>
