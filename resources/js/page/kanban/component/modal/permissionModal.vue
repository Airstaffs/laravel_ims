<template>
    <Dialog :visible="true" modal header="User Permissions" :style="{ width: '50vw' }"
        :breakpoints="{ '992px': '70vw', '480px': '95vw' }" :draggable="false" @update:visible="$emit('close')">
        <div class="dialog-content">
            <p class="mb-3 text-secondary">
                Grants mentioned users permission to edit, comment, or delete a task.
            </p>

            <DataTable :value="permissionData" stripedRows :loading="initialLoading" responsiveLayout="scroll"
                class="permission-table">
                <Column field="username" header="Name" style="min-width: 150px;" />

                <Column header="Edit" style="width: 100px; text-align: center;">
                    <template #body="{ data }">
                        <div class="flex justify-content-center">
                            <ToggleSwitch :modelValue="data.can_edit === 1"
                                @update:modelValue="togglePermission(data, 'can_edit', $event)" />
                        </div>
                    </template>
                </Column>

                <Column header="Comment" style="width: 100px; text-align: center;">
                    <template #body="{ data }">
                        <div class="flex justify-content-center">
                            <ToggleSwitch :modelValue="data.can_comment === 1"
                                @update:modelValue="togglePermission(data, 'can_comment', $event)" />
                        </div>
                    </template>
                </Column>

                <Column header="Delete" style="width: 100px; text-align: center;">
                    <template #body="{ data }">
                        <div class="flex justify-content-center">
                            <ToggleSwitch :modelValue="data.can_delete === 1"
                                @update:modelValue="togglePermission(data, 'can_delete', $event)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <template #footer>
            <Button label="Close" severity="secondary" @click="$emit('close')" :disabled="loadingState" text />
            <Button :label="loadingState ? 'Saving...' : 'Save'" @click="savePermissions" :loading="loadingState"
                :disabled="loadingState" />
        </template>
    </Dialog>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'

import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import ToggleSwitch from 'primevue/toggleswitch'

const props = defineProps({
    task: { type: Object, required: true }
})

defineEmits(['close'])

// Reactive state
const permissionData = ref([])
const loadingState = ref(false)
const initialLoading = ref(true)

// Fetch permissions
async function getUserPermissions() {
    try {
        initialLoading.value = true
        const { data } = await axios.post('/user/kanban/getUserPermissions', {
            taskId: props.task.id
        })

        if (data.permissions) {
            permissionData.value = data.permissions
        }
    } catch (error) {
        console.error(error)
        Swal.fire({
            icon: 'error',
            title: 'Failed to load permissions',
            text: 'Please try again later'
        })
    } finally {
        initialLoading.value = false
    }
}

// Toggle permission - receives the new boolean value from the switch
function togglePermission(permission, key, newValue) {
    permission[key] = newValue ? 1 : 0
}

// Save permissions
async function savePermissions() {
    try {
        loadingState.value = true

        await axios.post('/user/kanban/saveUserPermissions', {
            taskId: props.task.id,
            permissions: permissionData.value
        })

        await Swal.fire({
            icon: 'success',
            title: 'Permissions saved successfully!',
            confirmButtonText: 'OK'
        })
    } catch (error) {
        console.error(error)
        Swal.fire({
            icon: 'error',
            title: 'Failed to save permissions',
            text: error.response?.data?.message || 'An unexpected error occurred'
        })
    } finally {
        loadingState.value = false
    }
}

onMounted(getUserPermissions)
</script>

<style scoped>
.dialog-content {
    padding: 0.5rem 0;
}

.dialog-content p {
    margin-bottom: 1rem;
    font-size: 0.95rem;
    line-height: 1.5;
}

/* DataTable customization */
:deep(.permission-table) {
    font-size: 0.95rem;
}

:deep(.permission-table .p-datatable-thead > tr > th) {
    background-color: var(--p-surface-50);
    font-weight: 600;
    padding: 0.75rem 1rem;
}

:deep(.permission-table .p-datatable-tbody > tr > td) {
    padding: 0.75rem 1rem;
}

/* Center toggle switches */
.flex.justify-content-center {
    display: flex;
    justify-content: center;
    align-items: center;
}

/* Mobile responsive */
@media (max-width: 480px) {

    :deep(.permission-table .p-datatable-thead > tr > th),
    :deep(.permission-table .p-datatable-tbody > tr > td) {
        padding: 0.5rem;
        font-size: 0.85rem;
    }
}
</style>