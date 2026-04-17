<template>
    <Drawer v-model:visible="localVisible" header="IMS">
        <div class="flex flex-col h-full relative">
            <div class="mb-3">
                <p class="font-medium text-secondary mb-2">NAVIGATION</p>
                <Divider />
            </div>

            <!-- Scrollable navigation -->
            <nav class="flex-1 sidebar-nav overflow-auto">
                <!-- Main module -->
                <a
                    v-if="mainModule && modules[mainModule]"
                    :href="`/${mainModule}`"
                    class="nav-link"
                    :class="{ active: activeModule === mainModule }"
                    @click.prevent="handleNavClick(mainModule)"
                >
                    <i :class="['pi', moduleIcons[mainModule] || 'pi-folder']" class="mr-2"></i>
                    {{ modules[mainModule] }}
                </a>

                <!-- Sub-modules -->
                <template v-for="module in filteredSubModules" :key="module">
                    <!-- ASIN OPTION -->
                    <div v-if="module === 'asinoption'" class="nav-group">
                        <a
                            href="#"
                            class="nav-link"
                            :class="{ active: isAsinOptionActive }"
                            @click.prevent="toggleAsinSubmenu"
                        >
                            <i :class="['pi', moduleIcons[module] || 'pi-file']" class="mr-2"></i>
                            {{ modules[module] }}
                            <i
                                :class="[
                                    'pi',
                                    asinSubmenuOpen
                                        ? 'pi-chevron-down'
                                        : 'pi-chevron-right',
                                ]"
                                class="ml-auto submenu-toggle"
                            ></i>
                        </a>

                        <div v-show="asinSubmenuOpen" class="submenu">
                            <a
                                v-for="subItem in asinSubItems"
                                :key="subItem.id"
                                :href="`/${subItem.id}`"
                                class="nav-link submenu-link"
                                :class="{ active: activeModule === subItem.id }"
                                @click.prevent="handleNavClick(subItem.id)"
                            >
                                <i :class="['pi', subItem.icon]" class="mr-2"></i>
                                {{ subItem.label }}
                            </a>
                        </div>
                    </div>

                    <!-- UTILITY SCANNER -->
                    <div v-else-if="module === 'util_scanner'" class="nav-group">
                        <a
                            href="#"
                            class="nav-link"
                            :class="{ active: isUtilScannerActive }"
                            @click.prevent="toggleUtilScannerSubmenu"
                        >
                            <i :class="['pi', moduleIcons[module] || 'pi-file']" class="mr-2"></i>
                            {{ modules[module] }}
                            <i
                                :class="[
                                    'pi',
                                    utilScannerSubmenuOpen
                                        ? 'pi-chevron-down'
                                        : 'pi-chevron-right',
                                ]"
                                class="ml-auto submenu-toggle"
                            ></i>
                        </a>

                        <div v-show="utilScannerSubmenuOpen" class="submenu">
                            <a
                                v-for="subItem in utilScannerSubItems"
                                :key="subItem.id"
                                :href="`/${subItem.id}`"
                                class="nav-link submenu-link"
                                :class="{ active: activeModule === subItem.id }"
                                @click.prevent="handleNavClick(subItem.id)"
                            >
                                <i :class="['pi', subItem.icon]" class="mr-2"></i>
                                {{ subItem.label }}
                            </a>
                        </div>
                    </div>

                    <!-- REGULAR NAV LINKS -->
                    <a
                        v-else
                        :href="`/${module}`"
                        class="nav-link"
                        :class="{ active: activeModule === module }"
                        @click.prevent="handleNavClick(module)"
                    >
                        <i :class="['pi', moduleIcons[module] || 'pi-file']" class="mr-2"></i>
                        {{ modules[module] }}
                    </a>
                </template>
            </nav>

            <!-- Fixed bottom div -->
            <div class="fixed-bottom-div">
                <Avatar v-if="user.profile_picture" :image="user.profile_picture" shape="circle" />
                <Avatar v-else :label="user.username.charAt(0).toUpperCase()" shape="circle" />
                <p class="fw-semibold">{{ user.username }}</p>
            </div>
        </div>
    </Drawer>
</template>

<script setup>
import { Drawer, Divider, Avatar } from "primevue";
import { computed, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";

const router = useRouter();

const props = defineProps({
    visible: {
        type: Boolean,
        required: true,
    },
});

const emit = defineEmits([
    "update:visible",
    "load-content",
    "show-asin-modal",
    "asin-option-selected",
]);

// Drawer model bridge
const localVisible = computed({
    get: () => props.visible,
    set: (value) => emit("update:visible", value),
});

// Reactive data
const user = ref(window.user || {});
const mainModule = ref("");
const subModules = ref([]);
const activeModule = ref("");

const asinSubmenuOpen = ref(false);
const utilScannerSubmenuOpen = ref(false);

// ASIN submenu items
const asinSubItems = ref([
    { id: "asinlist", label: "ASIN List", icon: "pi-list" },
    { id: "fnsku", label: "FNSKU List", icon: "pi-barcode" },
    { id: "mskucreation", label: "FNSKU Creation", icon: "pi-plus-circle" },
]);

// Utility Scanner submenu items
const utilScannerSubItems = ref([
    { id: "itemchecker", label: "Item Checker", icon: "pi-search" },
]);

// Module names
const modules = ref({
    humanresource: "Human Resource",
    order: "Order",
    asinoption: "Asin Option",
    util_scanner: "Utility Scanner",
    itemchecker: "Item Checker",
    unreceived: "Unreceived",
    receiving: "Received",
    labeling: "Labeling",
    validation: "Validation",
    testing: "Testing",
    cleaning: "Cleaning",
    packing: "Packaging",
    stockroom: "Stockroom",
    productionarea: "Production Area",
    soldlist: "Sold Items",
    rts: "RTS",
    returnscanner: "Return Scanner",
    fbmorder: "FBM Order",
    shipment: "Shipment",
    notfound: "Not Found",
    houseage: "Houseage",
    suppliescomponents: "Supplies & Components",
    reconciliation: "Reconciliation",
    printer: "Printer",
    auxiliary: "Auxiliary Label",
    inventorystatistics: "Inventory Statistics",
    asinlist: "ASIN List",
    fnsku: "FNSKU List",
    mskucreation: "FNSKU Creation",
    switcheru: "Switcheru List",
    repair: "Repair List",
});

// Icons for each module
const moduleIcons = {
    humanresource: "pi-users",
    order: "pi-shopping-cart",
    asinoption: "pi-list",
    util_scanner: "pi-wrench",
    itemchecker: "pi-search",
    unreceived: "pi-inbox",
    receiving: "pi-download",
    labeling: "pi-tag",
    validation: "pi-check-circle",
    testing: "pi-wrench",
    cleaning: "pi-refresh",
    packing: "pi-box",
    stockroom: "pi-warehouse",
    productionarea: "pi-cog",
    rts: "pi-truck",
    returnscanner: "pi-qrcode",
    fbmorder: "pi-shopping-bag",
    shipment: "pi-list",
    soldlist: "pi-chart-line",
    notfound: "pi-ban",
    houseage: "pi-home",
    suppliescomponents: "pi-box",
    asinlist: "pi-list",
    fnsku: "pi-barcode",
    mskucreation: "pi-plus-circle",
    printer: "pi-print",
    auxiliary: "pi-print",
    inventorystatistics: "pi-chart-bar",
    reconciliation: "pi-check",
    switcheru: "pi-exchange",
    repair: "pi-hammer",
};

// Get array of ASIN sub-item IDs
const asinSubItemIds = computed(() =>
    asinSubItems.value.map((item) => item.id),
);

// Get array of Utility Scanner sub-item IDs
const utilScannerSubItemIds = computed(() =>
    utilScannerSubItems.value.map((item) => item.id),
);

// Computed: exclude main module and submenu child items (they appear in submenu)
const filteredSubModules = computed(() =>
    subModules.value.filter(
        (mod) =>
            mod !== mainModule.value &&
            modules.value[mod] &&
            !asinSubItemIds.value.includes(mod) &&
            !utilScannerSubItemIds.value.includes(mod),
    ),
);

// Check if any ASIN sub-item is active
const isAsinOptionActive = computed(() => {
    return asinSubItems.value.some((item) => item.id === activeModule.value);
});

// Check if any Utility Scanner sub-item is active
const isUtilScannerActive = computed(() => {
    return utilScannerSubItems.value.some(
        (item) => item.id === activeModule.value,
    );
});

// LocalStorage keys
const STORAGE_KEY = "ims_active_module";
const SUBMENU_KEY = "ims_asin_submenu_open";
const UTIL_SCANNER_SUBMENU_KEY = "ims_util_scanner_submenu_open";

// Save active module to localStorage
const saveActiveModule = (module) => {
    try {
        localStorage.setItem(STORAGE_KEY, module);
    } catch (error) {
        console.error("Error saving to localStorage:", error);
    }
};

// Load active module from localStorage
const loadActiveModule = () => {
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch (error) {
        console.error("Error loading from localStorage:", error);
        return null;
    }
};

// Save ASIN submenu state
const saveSubmenuState = (isOpen) => {
    try {
        localStorage.setItem(SUBMENU_KEY, isOpen.toString());
    } catch (error) {
        console.error("Error saving submenu state:", error);
    }
};

// Load ASIN submenu state
const loadSubmenuState = () => {
    try {
        const state = localStorage.getItem(SUBMENU_KEY);
        return state === "true";
    } catch (error) {
        console.error("Error loading submenu state:", error);
        return false;
    }
};

// Save Utility Scanner submenu state
const saveUtilScannerSubmenuState = (isOpen) => {
    try {
        localStorage.setItem(UTIL_SCANNER_SUBMENU_KEY, isOpen.toString());
    } catch (error) {
        console.error("Error saving utility scanner submenu state:", error);
    }
};

// Load Utility Scanner submenu state
const loadUtilScannerSubmenuState = () => {
    try {
        const state = localStorage.getItem(UTIL_SCANNER_SUBMENU_KEY);
        return state === "true";
    } catch (error) {
        console.error("Error loading utility scanner submenu state:", error);
        return false;
    }
};

// Toggle ASIN submenu
const toggleAsinSubmenu = () => {
    asinSubmenuOpen.value = !asinSubmenuOpen.value;
    saveSubmenuState(asinSubmenuOpen.value);
};

// Toggle Utility Scanner submenu
const toggleUtilScannerSubmenu = () => {
    utilScannerSubmenuOpen.value = !utilScannerSubmenuOpen.value;
    saveUtilScannerSubmenuState(utilScannerSubmenuOpen.value);
};

const fetchUserData = () => {
    try {
        mainModule.value = (window.mainModule || "").toLowerCase();
        subModules.value = (window.allowedModules || []).map((mod) =>
            mod.toLowerCase(),
        );

        subModules.value = subModules.value.filter(
            (mod) => mod !== mainModule.value,
        );

        const savedModule = loadActiveModule();

        const currentPath =
            router?.currentRoute?.value?.path || window.location.pathname;
        const currentModule = currentPath.replace(/^\//, "").split("/")[0];

        asinSubmenuOpen.value = loadSubmenuState();
        utilScannerSubmenuOpen.value = loadUtilScannerSubmenuState();

        const modalModules = ["asinoption", "printer"];

        // Priority: current URL > saved module > default
        if (currentModule && modules.value[currentModule]) {
            activeModule.value = currentModule;

            if (asinSubItems.value.some((item) => item.id === currentModule)) {
                asinSubmenuOpen.value = true;
                saveSubmenuState(true);
            }

            if (
                utilScannerSubItems.value.some(
                    (item) => item.id === currentModule,
                )
            ) {
                utilScannerSubmenuOpen.value = true;
                saveUtilScannerSubmenuState(true);
            }
        } else if (
            savedModule &&
            !modalModules.includes(savedModule) &&
            modules.value[savedModule]
        ) {
            activeModule.value = savedModule;

            if (asinSubItems.value.some((item) => item.id === savedModule)) {
                asinSubmenuOpen.value = true;
                saveSubmenuState(true);
            }

            if (
                utilScannerSubItems.value.some(
                    (item) => item.id === savedModule,
                )
            ) {
                utilScannerSubmenuOpen.value = true;
                saveUtilScannerSubmenuState(true);
            }

            if (window.loadContent) {
                window.loadContent(savedModule);
            } else {
                router
                    .push(`/${savedModule}`)
                    .catch(() => (window.location.href = `/${savedModule}`));
            }
        } else {
            activeModule.value =
                mainModule.value || subModules.value[0] || "dashboard";
        }
    } catch (error) {
        console.error("❌ Error fetching user data:", error);
    }
};

const handleNavClick = (module) => {
    if (window.hasAccess && typeof window.hasAccess === "function") {
        const hasAccess = window.hasAccess(
            module,
            mainModule.value,
            subModules.value,
        );

        if (!hasAccess) {
            return alert("You do not have permission to access this module");
        }
    }

    activeModule.value = module;

    const isAsinModule =
        module === "asinoption" ||
        asinSubItems.value.some((item) => item.id === module);

    const isUtilScannerModule =
        module === "util_scanner" ||
        utilScannerSubItems.value.some((item) => item.id === module);

    if (!isAsinModule) {
        asinSubmenuOpen.value = false;
        saveSubmenuState(false);
    }

    if (!isUtilScannerModule) {
        utilScannerSubmenuOpen.value = false;
        saveUtilScannerSubmenuState(false);
    }

    const modalModules = ["asinoption", "printer"];

    if (!modalModules.includes(module)) {
        saveActiveModule(module);

        if (window.loadContent) {
            window.loadContent(module);
        } else {
            router
                .push(`/${module}`)
                .catch(() => (window.location.href = `/${module}`));
        }
    } else {
        if (window.loadContent) {
            window.loadContent(module);
        }
    }

    emit("load-content", module);
    localVisible.value = false;
};

// Handle ASIN option selection from modal (if still needed)
const handleAsinOptionSelected = (selectedModule) => {
    const modalModules = ["asinoption", "printer"];

    if (selectedModule && !modalModules.includes(selectedModule)) {
        activeModule.value = selectedModule;
        saveActiveModule(selectedModule);

        if (window.loadContent) {
            window.loadContent(selectedModule);
        } else {
            router
                .push(`/${selectedModule}`)
                .catch(() => (window.location.href = `/${selectedModule}`));
        }
    }
};

// Expose function globally for modal to use
window.handleAsinOptionSelected = handleAsinOptionSelected;

// Watch for route changes and save to localStorage
watch(
    () => router?.currentRoute?.value?.path,
    (newPath) => {
        if (newPath) {
            const module = newPath.replace(/^\//, "").split("/")[0];
            const modalModules = ["asinoption", "printer"];

            if (module && modules.value[module]) {
                activeModule.value = module;

                if (!modalModules.includes(module)) {
                    saveActiveModule(module);
                }

                if (asinSubItems.value.some((item) => item.id === module)) {
                    asinSubmenuOpen.value = true;
                    saveSubmenuState(true);
                }

                if (
                    utilScannerSubItems.value.some(
                        (item) => item.id === module,
                    )
                ) {
                    utilScannerSubmenuOpen.value = true;
                    saveUtilScannerSubmenuState(true);
                }
            }
        }
    },
    { immediate: true },
);

// Watch activeModule changes
watch(activeModule, (newModule) => {
    const modalModules = ["asinoption", "printer"];

    if (newModule && !modalModules.includes(newModule)) {
        saveActiveModule(newModule);
    }
});

onMounted(fetchUserData);
</script>

<style scoped>
.sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding-bottom: 10rem;
}

.nav-group {
    display: flex;
    flex-direction: column;
}

.nav-link {
    color: #333 !important;
    text-decoration: none;
    border-radius: 0.375rem;
    transition: all 0.25s ease;
    cursor: pointer;
    font-weight: 500;
    font-size: 16px;
    padding: 0.75rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    position: relative;
}

.nav-link i {
    font-size: 1rem;
}

.submenu-toggle {
    font-size: 0.75rem;
    transition: transform 0.2s ease;
}

.nav-link:hover {
    background-color: white;
    color: #4780dc !important;
}

.nav-link.active {
    background-color: #4780dc;
    color: #fff !important;
    border-left: 3px solid #1e40af;
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.25);
}

.submenu {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    padding-left: 0.5rem;
    margin-top: 0.25rem;
    margin-bottom: 0.25rem;
}

.submenu-link {
    font-size: 14px;
    padding: 0.6rem 1rem 0.6rem 2rem;
    background-color: #ffffff;
}

.submenu-link:hover {
    background-color: #e9ecef;
    color: #4780dc !important;
    border-left-color: #4780dc;
}

.submenu-link.active {
    background-color: #4780dc;
    color: #fff !important;
    border-left: 3px solid #1e40af;
}

.font-medium {
    font-weight: 600;
    color: #333;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.text-secondary {
    color: #6c757d;
}

.fixed-bottom-div {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background-color: #f8f9fa;
    padding: 1rem;
    border-top: 1px solid #dee2e6;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
</style>