<template>
    <span :class="[priorityColorClass(priority), ' px-2 rounded-pill mb-2 fw-bolder']" style="font-size: 14px;">
        {{ priority.charAt(0).toUpperCase() + priority.slice(1) }}
    </span>
</template>

<script setup>
const props = defineProps({
    priority: {
        type: String,
        required: true
    }
})

function priorityColorClass(priority) {
    switch (priority) {
        case 'low':
            return 'text-white text-bg-primary'
        case 'medium':
            return 'text-dark text-bg-warning'
        case 'high':
            return 'text-white text-bg-danger'
        default:
            return 'text-secondary'
    }
}
</script>
