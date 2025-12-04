<template>
  <div class="flex flex-col">
    <label class="text-gray-300 text-sm font-medium mb-1">{{ label }}</label>
    <slot />
  </div>
</template>

<script setup>
defineProps({
  label: String
})
</script>
