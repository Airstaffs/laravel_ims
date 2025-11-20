<template>
    <div class="section-header">
        <h1 class="section-title">{{ title }}</h1>
        <p class="section-subtitle text-secondary" v-if="subtitle">
            {{ subtitle }}
        </p>
    </div>
</template>

<script setup>
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
.section-header {
    padding: 1.5rem;
    /* border-bottom: 1px solid #e5e7eb; */
}

.section-title {
    font-size: 2rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.section-subtitle {
    margin: 0;
    font-size: 0.95rem;
}
</style>
