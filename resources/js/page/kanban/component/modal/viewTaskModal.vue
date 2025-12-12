<template>
    <Dialog :visible="true" modal header="View Task" :style="{ width: '90vw', maxWidth: '900px' }"
        :breakpoints="{ '768px': '95vw' }" :draggable="false" @update:visible="$emit('close')"
        :contentStyle="{ maxHeight: '80vh', overflowY: 'auto' }">
        <TabView @tab-change="handleTabChange" v-model:activeIndex="activeTab">
            <TabPanel header="Details">
                <TaskDetails :task="task" />
            </TabPanel>

            <TabPanel header="Comments">
                <TaskComments v-if="showComments" :task-id="task.id" :mentions="task.mentions" />
            </TabPanel>

            <TabPanel header="Activity Logs">
                <TaskActivityLogs v-if="showActivityLogs" :task-id="task.id" />
            </TabPanel>
        </TabView>

        <template #footer>
            <Button label="Close" @click="$emit('close')" class="w-full sm:w-auto" />
        </template>
    </Dialog>
</template>

<script setup>
import { ref } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'

import TaskComments from '../taskComments.vue'
import TaskDetails from '../taskDetails.vue'
import TaskActivityLogs from '../taskActivityLogs.vue'

const props = defineProps({
    task: { type: Object, required: true }
})

defineEmits(['close'])

const activeTab = ref(0)
const showComments = ref(false)
const showActivityLogs = ref(false)

function handleTabChange(event) {
    const index = event.index

    if (index === 0) {
        // Details tab
        showComments.value = false
        showActivityLogs.value = false
    } else if (index === 1) {
        // Comments tab
        showComments.value = true
        showActivityLogs.value = false
    } else if (index === 2) {
        // Activity Logs tab
        showComments.value = false
        showActivityLogs.value = true
    }
}
</script>

<style scoped>
/* Responsive button width */
.w-full {
    width: 100%;
}

@media (min-width: 640px) {
    .sm\:w-auto {
        width: auto;
    }
}

/* TabView customization */
:deep(.p-tabview) {
    overflow: visible;
}

:deep(.p-tabview-nav) {
    overflow-x: auto;
    overflow-y: hidden;
    flex-wrap: nowrap;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
}

:deep(.p-tabview-nav::-webkit-scrollbar) {
    height: 4px;
}

:deep(.p-tabview-nav::-webkit-scrollbar-thumb) {
    background-color: rgba(0, 0, 0, 0.2);
    border-radius: 4px;
}

:deep(.p-tabview-panels) {
    padding: 1rem;
}

/* Mobile optimizations */
@media (max-width: 768px) {
    :deep(.p-tabview-panels) {
        padding: 0.75rem;
    }

    :deep(.p-tabview-nav-link) {
        padding: 0.5rem 0.75rem;
        font-size: 0.9rem;
    }
}
</style>