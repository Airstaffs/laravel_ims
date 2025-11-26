<template>
    <div :class="['animated-wrapper', type]" :style="{ transitionDelay: delay + 'ms' }" ref="el">
        <slot />
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
    type: { type: String, default: 'fade-in-up' },
    delay: { type: Number, default: 0 },
})

const el = ref(null)
let observer = null

onMounted(() => {
    observer = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) {
                el.value.classList.add('in-view')
                observer.unobserve(el.value) // stop observing once animated
            }
        },
        { threshold: 0.1 }
    )
    observer.observe(el.value)
})

onBeforeUnmount(() => {
    if (observer && el.value) observer.unobserve(el.value)
})
</script>

<style scoped>
.animated-wrapper {
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.5s ease;
}

.animated-wrapper.in-view {
    opacity: 1;
    transform: translateY(0);
}
</style>
