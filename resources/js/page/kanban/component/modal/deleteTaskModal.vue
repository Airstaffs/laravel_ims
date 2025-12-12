<template>
    <Dialog :visible="true" modal header="Delete Task" :style="{ width: '40vw' }"
        :breakpoints="{ '992px': '60vw', '480px': '90vw' }" :draggable="false" @update:visible="$emit('close')">
        <div class="dialog-content">
            <p v-if="task">
                Are you sure you want to delete <strong>{{ task.title }}</strong>?
            </p>
            <p v-else>Loading...</p>
        </div>

        <template #footer>
            <Button size="small" label="Cancel" severity="secondary" @click="$emit('close')" :disabled="loading" text />
            <Button size="small" :label="loading ? 'Deleting...' : 'Yes, Delete'" severity="danger"
                @click="$emit('confirm')" :loading="loading" :disabled="loading" />
        </template>
    </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'

const props = defineProps({
    task: Object,
    loading: { type: Boolean, default: false }
})

defineEmits(['close', 'confirm'])
</script>

<style scoped>
.dialog-content {
    padding: .5rem 0;
}

.dialog-content p {
    margin-bottom: 0;
    font-size: 1rem;
    line-height: 1.5;
}

.dialog-content strong {
    color: var(--p-text-color);
    font-weight: 600;
}
</style>