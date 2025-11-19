<template>
    <Dialog
        :visible="visible"
        @update:visible="$emit('update:visible', $event)"
        modal
        header="Admin Settings"
        :style="{ width: '90%', height: '80vh' }"
        class="settings-modal"
        :maximizable="true"
    >
        <TabView class="settings-tabs">
            <TabPanel>
                <template #header>
                    <i class="bi bi-palette"></i>
                    <span> Title & Design</span>
                </template>
                <!-- Add content here -->
            </TabPanel>

            <TabPanel>
                <template #header>
                    <i class="bi bi-person-plus"></i>
                    <span> Add User</span>
                </template>
                <!-- Add content here -->
            </TabPanel>

            <TabPanel>
                <template #header>
                    <i class="bi bi-shop"></i>
                    <span> Store List</span>
                </template>
                <!-- Add content here -->
            </TabPanel>

            <TabPanel>
                <template #header>
                    <i class="bi bi-shield-lock"></i>
                    <span> Privileges</span>
                </template>
                <!-- Add content here -->
            </TabPanel>

            <TabPanel>
                <template #header>
                    <i class="bi bi-clock"></i>
                    <span> Time Record</span>
                </template>
                <!-- Add content here -->
            </TabPanel>

            <TabPanel>
                <template #header>
                    <i class="bi bi-person-lines-fill"></i>
                    <span> User Logs</span>
                </template>
                <!-- Add content here -->
            </TabPanel>

            <TabPanel>
                <template #header>
                    <i class="bi bi-printer"></i>
                    <span> Printers</span>
                </template>
                <!-- Add content here -->
            </TabPanel>
        </TabView>
    </Dialog>
</template>

<script>
import Dialog from "primevue/dialog";
import TabView from "primevue/tabview";
import TabPanel from "primevue/tabpanel";

import Swal from "sweetalert2";

export default {
    name: "SettingsModal",
    components: {
        Dialog,
        TabView,
        TabPanel,
    },
    props: {
        visible: {
            type: Boolean,
            default: false,
        },
    },
    emits: ["update:visible"],
    data() {
        return {
            // Add any component-specific data here
        };
    },
    watch: {},
    mounted() {},
    beforeUnmount() {},
    computed: {},
    methods: {},
};
</script>

<style scoped>
.settings-tabs {
    height: 100%;
}

.settings-tabs :deep(.p-tabview-panels) {
    height: calc(100% - 60px);
    overflow-y: auto;
}

.settings-tabs :deep(.p-tabview-nav) {
    flex-wrap: nowrap;
    overflow-x: auto;
    overflow-y: hidden;
}

.settings-tabs :deep(.p-tabview-tab-header) {
    white-space: nowrap;
    font-size: 0.875rem;
}

.bi {
    margin-right: 0.5rem;
}
</style>
