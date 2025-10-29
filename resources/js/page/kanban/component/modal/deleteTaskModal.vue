<template>
    <div class="modal fade show" tabindex="-1" style="display: block;">
        <div class="modal-dialog modal-dialog-centered modal-delete-width">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Task</h5>
                    <button type="button" class="btn-close" @click="$emit('close')"></button>
                </div>
                <div class="modal-body">
                    <p v-if="task">
                        Are you sure you want to delete <strong>{{ task.title }}</strong>?
                    </p>
                    <p v-else>Loading...</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="$emit('close')" :disabled="loading">Cancel</button>
                    <button class="btn btn-danger" @click="$emit('confirm')" :disabled="loading">
                        <span v-if="loading"><i class="bi bi-arrow-repeat spin me-1"></i>Deleting...</span>
                        <span v-else>Yes, Delete</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    </div>
</template>

<script setup>
const props = defineProps({
    task: Object,
    loading: { type: Boolean, default: false }
})
</script>


<style scoped>
.modal-delete-width {
    width: 40%;
    max-width: 400px;
    min-width: 280px;
}

/* Tablet */
@media (max-width: 992px) {
    .modal-delete-width {
        width: 60%;
    }
}

/* Mobile */
@media (max-width: 480px) {
    .modal-delete-width {
        width: 90%;
        margin: 0;
        border-radius: 8px;
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
