<template>
    <div class="section-header-page d-flex align-items-center gap-3">
        <h1 class="section-title-page">{{ title }}</h1>
        <div v-if="subtitle">
            <i class="pi pi-info-circle text-secondary" @click="togglePopover" style="cursor: pointer;">
            </i>
            <Popover ref="popover">
                <div class="p-3" style="max-width: 300px;">
                    {{ subtitle }}
                </div>
            </Popover>
        </div>
    </div>
</template>

<script setup>
import Popover from 'primevue/popover';
import { ref } from 'vue';

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

const popover = ref();

const togglePopover = (event) => {
    popover.value.toggle(event);
};
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