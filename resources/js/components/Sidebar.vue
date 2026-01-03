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
                    <i
                        :class="['pi', moduleIcons[mainModule] || 'pi-folder']"
                        class="mr-2"
                    ></i>
                    {{ modules[mainModule] }}
                </a>

                <!-- Sub-modules -->
                <a
                    v-for="module in filteredSubModules"
                    :key="module"
                    :href="module === 'asinoption' ? '#' : `/${module}`"
                    class="nav-link"
                    :class="{ active: activeModule === module }"
                    @click.prevent="handleNavClick(module)"
                >
                    <i
                        :class="['pi', moduleIcons[module] || 'pi-file']"
                        class="mr-2"
                    ></i>
                    {{ modules[module] }}
                </a>
            </nav>

            <!-- Fixed bottom div -->
            <div class="fixed-bottom-div">
                <Avatar
                    v-if="user.profile_picture"
                    :image="user.profile_picture"
                    shape="circle"
                />
                <Avatar
                    v-else
                    :label="user.username.charAt(0).toUpperCase()"
                    shape="circle"
                />
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

const emit = defineEmits(["update:visible", "load-content", "show-asin-modal"]);

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

// Module names
const modules = ref({
    humanresource: "Human Resource",
    order: "Order",
    asinoption: "Asin Option",
    unreceived: "Unreceived",
    receiving: "Received",
    labeling: "Labeling",
    validation: "Validation",
    testing: "Testing",
    cleaning: "Cleaning",
    packing: "Packaging",
    stockroom: "Stockroom",
    productionarea: "Production Area",
    rts: "RTS",
    returnscanner: "Return Scanner",
    fbmorder: "FBM Order",
    notfound: "Not Found",
    houseage: "Houseage",
    // asinlist: 'ASIN List',
    // fnsku: 'FNSKU',
    printer: "Printer",
});

// Icons for each module
const moduleIcons = {
    humanresource: "pi-users",
    order: "pi-shopping-cart",
    asinoption: "pi-list",
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
    notfound: "pi-ban",
    houseage: "pi-home",
    asinlist: "pi-list",
    fnsku: "pi-barcode",
    printer: "pi-print",
};

// Computed: exclude main module
const filteredSubModules = computed(() =>
    subModules.value.filter(
        (mod) => mod !== mainModule.value && modules.value[mod]
    )
);

const fetchUserData = () => {
    try {
        mainModule.value = (window.mainModule || "").toLowerCase();
        subModules.value = (window.allowedModules || []).map((mod) =>
            mod.toLowerCase()
        );
        subModules.value = subModules.value.filter(
            (mod) => mod !== mainModule.value
        );
        activeModule.value =
            mainModule.value || subModules.value[0] || "dashboard";
    } catch (error) {
        console.error("❌ Error fetching user data:", error);
    }
};

const handleNavClick = (module) => {
    if (window.hasAccess && typeof window.hasAccess === "function") {
        const hasAccess = window.hasAccess(
            module,
            mainModule.value,
            subModules.value
        );
        if (!hasAccess)
            return alert("You do not have permission to access this module");
    }

    activeModule.value = module;

    if (module === "Asin Option") {
        emit("show-asin-modal");
    } else if (window.loadContent) {
        window.loadContent(module);
    } else {
        router
            .push(`/${module}`)
            .catch(() => (window.location.href = `/${module}`));
    }

    emit("load-content", module);
    localVisible.value = false;
};

watch(
    () => router?.currentRoute?.value?.path,
    (newPath) => {
        if (newPath) {
            const module = newPath.replace(/^\//, "").split("/")[0];
            if (module && modules.value[module]) {
                activeModule.value = module;
            }
        }
    },
    { immediate: true }
);

onMounted(fetchUserData);
</script>

<style scoped>
.sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding-bottom: 10rem;
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
}

.nav-link i {
    font-size: 1rem;
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
