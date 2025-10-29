<template>
    <div>
        <PriorityBadge :priority="task.priority" />
        <h4 class="my-2">{{ task.title }}</h4>
        <p class="text-secondary my-2">{{ task.description || 'No description' }}</p>
        <p class="my-2"><strong>Notes:</strong> {{ task.note || 'No notes' }}</p>
        <MentionedProfile :mentions="task.mentions" />
        <PictureGallery :images="task.medias" />
    </div>
</template>

<script setup>
import MentionedProfile from './mentionedProfile.vue';
import PictureGallery from './pictureGallery.vue';
import PriorityBadge from './priorityBadge.vue';

const props = defineProps({
    task: {
        type: Object
    }
})
</script>

<style></style>