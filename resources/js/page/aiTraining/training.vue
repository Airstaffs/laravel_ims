<template>
  <div class="p-6 space-y-8 bg-gray-900 text-white rounded-lg shadow-lg">
    <h1 class="text-2xl font-bold">🧪 Model Training</h1>

    <!-- Upload Section -->
    <div class="flex flex-col md:flex-row gap-4">
      <UploadDataset
        label="📁 Upload Dataset (zip dataset file)"
        accept=".zip"
        :multiple="false"
        @files-selected="handleDatasetFiles"
        class="md:w-full"
      />
    </div>

    <!-- Upload Progress + Logs -->
    <div class="mt-6 space-y-2">
      <div v-if="uploading" class="text-sm text-yellow-400">
        ⏳ Uploading... {{ uploadProgress }}%
      </div>
      <div class="bg-black p-3 h-40 overflow-y-auto rounded text-green-400 text-sm font-mono whitespace-pre">
        <div v-for="(log, index) in logs" :key="index">{{ log }}</div>
      </div>
    </div>
  </div>
</template>

<!-- ✅ Composition API Setup -->
<script setup>
import UploadDataset from './components/UploadDataset.vue'
import useTraining from './training-script.js'

// Pull in upload logic
const { logs, uploadProgress, uploading, handleDatasetFiles } = useTraining()
</script>

<style scoped>
.btn-primary {
  @apply px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded;
}
</style>
