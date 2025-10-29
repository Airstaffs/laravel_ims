<template>
    <div class="mentions-dropdown position-relative">
        <!-- Selected Mentions -->
        <div class="mb-2">
            <span v-for="(mention, index) in selectedMentions" :key="mention.id" class="badge bg-primary me-1">
                {{ mention.username }}
                <button type="button" class="btn-close btn-close-white btn-sm ms-1"
                    @click="removeMention(index)"></button>
            </span>
        </div>


        <!-- Dropdown -->
        <div class="dropdown w-100">
            <button class="btn btn-outline-secondary w-100 dropdown-toggle" type="button" @click="toggleDropdown">
                Select Mentions
            </button>
            <ul v-show="isOpen" class="dropdown-menu w-100 show" style="max-height: 150px; overflow-y: auto">
                <li v-for="user in users" :key="user.id" class="dropdown-item" @click="addMention(user)">
                    {{ user.username }}
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

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

const isOpen = ref(false)
const selectedMentions = ref([...props.modelValue])

function toggleDropdown() {
    isOpen.value = !isOpen.value
}

function addMention(user) {
    if (!selectedMentions.value.some(m => m.id === user.id)) {
        selectedMentions.value.push({ id: user.id, username: user.username })
        emit('update:modelValue', selectedMentions.value)
        console.log('Selected mentions:', selectedMentions.value)
    }
}


function removeMention(index) {
    selectedMentions.value.splice(index, 1)
    emit('update:modelValue', selectedMentions.value)
}

// ✅ Convert IDs → full user info for display only
const selectedMentionUsers = computed(() =>
    selectedMentions.value.map(id => props.users.find(u => u.id === id)).filter(Boolean)
)

// Keep in sync with parent updates
watch(() => props.modelValue, val => {
    selectedMentions.value = [...val]
})
</script>


<style scoped>
.mentions-dropdown {
    z-index: 1055;
    /* make sure it's above modal backdrop */
}
</style>
