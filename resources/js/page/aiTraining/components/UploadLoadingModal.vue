<template>
  <Teleport to="body">
    <div
      v-if="visible"
      class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80"
    >
      <div class="bg-gray-900 border border-gray-700 rounded-xl p-6 w-96 text-center shadow-2xl">
        <!-- Spinner -->
        <div
          class="mx-auto mb-4 h-12 w-12 animate-spin rounded-full border-4 border-blue-500 border-t-transparent"
        />

        <h2 class="text-lg font-semibold text-white mb-2">
          Uploading Dataset
        </h2>

        <p class="text-sm text-gray-400 mb-4">
          Please don’t close or refresh this page
        </p>

        <!-- Progress -->
        <div class="w-full bg-gray-700 rounded-full h-2 overflow-hidden">
          <div
            class="bg-blue-500 h-2 transition-all duration-200"
            :style="{ width: progress + '%' }"
          />
        </div>

        <p class="text-xs text-gray-400 mt-2">
          {{ progress }}%
        </p>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
defineProps({
  visible: Boolean,
  progress: {
    type: Number,
    default: 0,
  },
})
</script>
