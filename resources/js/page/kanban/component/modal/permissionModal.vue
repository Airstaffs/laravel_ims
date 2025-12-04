<template>
    <div class="modal fade show" tabindex="-1" style="display: block;">
        <div class="modal-dialog modal-dialog-centered modal-permission-width">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">User Permissions</h5>
                    <button type="button" class="btn-close" @click="$emit('close')"></button>
                </div>

                <div class="modal-body">
                    <p class="mb-2">Grants mentioned users permission to edit, comment, or delete a task.</p>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Edit</th>
                                    <th>Comment</th>
                                    <th>Delete</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="permission in permissionData" :key="permission.id">
                                    <td>{{ permission.username }}</td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox"
                                                :checked="permission.can_edit === 1"
                                                @change="togglePermission(permission, 'can_edit')" />
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox"
                                                :checked="permission.can_comment === 1"
                                                @change="togglePermission(permission, 'can_comment')" />
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox"
                                                :checked="permission.can_delete === 1"
                                                @change="togglePermission(permission, 'can_delete')" />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="$emit('close')" :disabled="loadingState">
                        Close
                    </button>
                    <button class="btn btn-primary" @click="savePermissions" :disabled="loadingState">
                        <span v-if="loadingState"><i class="bi bi-arrow-repeat spin me-1"></i>Saving...</span>
                        <span v-else>Save</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    </div>
</template>

<script setup>
import axios from 'axios';
import Swal from 'sweetalert2';
import { ref, onMounted } from 'vue';

const props = defineProps({
    task: { type: Object, required: true }
});

// Reactive state
const permissionData = ref([]);
const loadingState = ref(false);

// Fetch permissions
async function getUserPermissions() {
    try {
        const { data } = await axios.post('/user/kanban/getUserPermissions', { taskId: props.task.id });
        if (data.permissions) permissionData.value = data.permissions;
    } catch (error) {
        console.error(error);
    }
}

// Toggle numeric permission
function togglePermission(permission, key) {
    permission[key] = permission[key] === 1 ? 0 : 1;
}

// Save permissions
async function savePermissions() {
    try {
        loadingState.value = true;
        await axios.post('/user/kanban/saveUserPermissions', {
            taskId: props.task.id,
            permissions: permissionData.value
        });
        Swal.fire({
            icon: 'success',
            title: 'Permissions saved successfully!',
            confirmButtonText: 'Exit'
        })
    } catch (error) {
        console.error(error);
        Swal.fire({
            icon: 'error',
            title: 'Failed to saved permission',
            confirmButtonText: 'Exit'
        })
    } finally {
        loadingState.value = false;
    }
}

onMounted(getUserPermissions);
</script>

<style scoped>
.modal-permission-width {
    width: 50%;
    max-width: 600px;
    min-width: 320px;
}

@media (max-width: 992px) {
    .modal-permission-width {
        width: 70%;
    }
}

@media (max-width: 480px) {
    .modal-permission-width {
        width: 95%;
        margin: 0;
        border-radius: 8px;
    }

    .table-responsive {
        overflow-x: auto;
    }
}

.spin {
    display: inline-block;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1040;
}
</style>
