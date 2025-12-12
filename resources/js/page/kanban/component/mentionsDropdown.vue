<template>
    <div class="mentions-dropdown">
        <MultiSelect v-model="selectedMentions" :options="props.users" optionLabel="username"
            placeholder="Select Mentions" :maxSelectedLabels="3" class="w-100" display="chip"
            @update:modelValue="handleUpdate" dataKey="id">
            <template #value="slotProps">
                <div v-if="slotProps.value && slotProps.value.length" class="d-flex flex-wrap gap-1">
                    <Chip v-for="mention in slotProps.value" :key="mention.id" :label="mention.username" removable
                        @remove="removeMention(mention)" />
                </div>
                <span v-else class="text-secondary">
                    {{ slotProps.placeholder }}
                </span>
            </template>

            <template #option="slotProps">
                <div class="d-flex align-items-center">
                    <span>{{ slotProps.option.username }}</span>
                </div>
            </template>
        </MultiSelect>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import MultiSelect from 'primevue/multiselect'
import Chip from 'primevue/chip'

const props = defineProps({
    users: {
        type: Array,
        required: true,
        default: () => []
    },
    modelValue: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['update:modelValue'])

const selectedMentions = ref([])

// Initialize with matching object references from props.users
function syncSelectedMentions(value) {
    if (!value || value.length === 0) {
        selectedMentions.value = []
        return
    }

    // Map modelValue IDs to actual objects from props.users
    selectedMentions.value = value.map(v => {
        const userId = typeof v === 'object' ? v.id : v
        return props.users.find(u => u.id === userId) || v
    }).filter(Boolean)
}

function handleUpdate(value) {
    emit('update:modelValue', value)
}

function removeMention(mention) {
    selectedMentions.value = selectedMentions.value.filter(m => m.id !== mention.id)
    emit('update:modelValue', selectedMentions.value)
}

watch(() => props.modelValue, val => {
    syncSelectedMentions(val)
}, { immediate: true, deep: true })

watch(() => props.users, () => {
    // Re-sync when users list changes
    syncSelectedMentions(props.modelValue)
}, { deep: true })
</script>

<style scoped>
:deep(.p-multiselect) {
    width: 100%;
}

:deep(.p-multiselect-panel) {
    max-height: 250px;
}
</style>