<template>
    <div class="mentions-stack">
        <div v-for="(user, index) in mentions" :key="user.id" class="mention-avatar"
            :style="{ left: `${index * 18}px`, zIndex: mentions.length - index }" :title="user.username"
            data-bs-toggle="tooltip" data-bs-placement="top">
            <img v-if="user.profile_picture" :src="user.profile_picture" :alt="user.username"
                @error="handleImageError($event, user)" />
            <span v-else class="fallback-letter">{{ user.username.charAt(0).toUpperCase() }}</span>
        </div>
    </div>
</template>

<script setup>
import { onMounted } from 'vue'
import * as bootstrap from 'bootstrap'

const props = defineProps({
    mentions: {
        type: Array,
        required: true,
        default: () => []
    }
})

// Optional: replace broken image with letter
function handleImageError(event, user) {
    event.target.style.display = 'none'
}

// Initialize Bootstrap tooltips
onMounted(() => {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    tooltipTriggerList.map(function (el) {
        return new bootstrap.Tooltip(el)
    })
})
</script>

<style scoped>
.mentions-stack {
    position: relative;
    height: 32px;
    display: flex;
    align-items: center;
}

.mention-avatar {
    position: absolute;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    overflow: hidden;
    border: 1px solid #fff;
    background-color: #6c757d;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    cursor: pointer;
    box-shadow: 0 0 2px rgba(0, 0, 0, 0.2);
    transition: transform 0.2s;
}

.mention-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.mention-avatar:hover {
    transform: scale(1.2);
    z-index: 999;
}

.fallback-letter {
    font-size: 14px;
    user-select: none;
}
</style>
