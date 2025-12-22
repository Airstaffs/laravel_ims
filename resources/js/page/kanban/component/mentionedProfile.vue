<template>
    <div>
        <p v-show="showLabel" class="font-bold">Mentioned Users</p>
        <p v-if="mentions.length === 0" class="text-secondary">No Mentions</p>
        <div class="mentions-stack" v-else>
            <Avatar v-for="(user, index) in displayedMentions" :key="user.id" v-tooltip.top="user.username"
                :image="user.profile_picture"
                :label="!user.profile_picture ? user.username.charAt(0).toUpperCase() : undefined" shape="circle"
                size="normal" class="mention-avatar"
                :style="{ left: `${index * 18}px`, zIndex: mentions.length - index }"
                @error="handleImageError($event, user)" />

            <!-- Ellipsis avatar for 6+ mentions -->
            <Avatar v-if="mentions.length >= 6" v-tooltip.top="remainingNames" :label="`+${mentions.length - 5}`"
                shape="circle" size="normal" class="mention-avatar ellipsis-avatar"
                :style="{ left: `${5 * 18}px`, zIndex: 1 }" />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import Avatar from 'primevue/avatar'

const props = defineProps({
    mentions: {
        type: Array,
        required: true,
        default: () => []
    },
    showLabel: { type: Boolean, default: () => false }
})

// Show only first 5 avatars if there are 6 or more mentions
const displayedMentions = computed(() => {
    return props.mentions.length >= 6 ? props.mentions.slice(0, 5) : props.mentions
})

// Get remaining names for tooltip
const remainingNames = computed(() => {
    if (props.mentions.length < 6) return ''
    const remaining = props.mentions.slice(5)
    return remaining.map(user => user.username).join(', ')
})

// Optional: replace broken image with letter
function handleImageError(event, user) {
    event.target.style.display = 'none'
}
</script>

<style scoped>
.text-secondary {
    color: var(--text-color-secondary);
}

.mentions-stack {
    position: relative;
    height: 32px;
    display: flex;
    align-items: center;
}

.mention-avatar {
    position: absolute;
    width: 30px !important;
    height: 30px !important;
    border: 2px solid var(--surface-0, #fff);
    background-color: var(--surface-600, #64748b) !important;
    color: var(--surface-0, #ffffff) !important;
    cursor: pointer;
    box-shadow: 0 0 2px rgba(0, 0, 0, 0.2);
    transition: transform 0.2s;
}

.mention-avatar:hover {
    transform: scale(1.2);
    z-index: 999 !important;
}

.mention-avatar :deep(.p-avatar-text) {
    font-size: 14px;
    user-select: none;
}

.mention-avatar.ellipsis-avatar {
    background-color: var(--surface-400, #94a3b8) !important;
}

.mention-avatar.ellipsis-avatar :deep(.p-avatar-text) {
    font-size: 11px;
    line-height: 1;
    font-weight: 600;
}
</style>