<template>
    <AnimateDiv class="section-header-page">
        <h1 class="section-title-page">{{ title }}</h1>
        <p class="section-subtitle text-secondary" v-if="subtitle">
            {{ subtitle }}
        </p>
    </AnimateDiv>
</template>

<script setup>
import AnimateDiv from '../AnimationDiv/AnimateDiv.vue';
defineProps({
    title: {
        type: String,
        required: true
    },
    subtitle: {
        type: String,
        default: ""
    }
});
</script>

<style scoped>
.section-header-page {
    padding: 1.5rem;
    /* border-bottom: 1px solid #e5e7eb; */
}

.section-title-page {
    font-size: 2rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.section-subtitle {
    margin: 0;
    font-size: 0.95rem;
}
</style>
