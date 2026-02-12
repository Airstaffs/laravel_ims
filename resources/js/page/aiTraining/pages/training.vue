<template>
  <UploadLoadingModal
    :visible="uploading"
    :progress="uploadProgress"
  />

  <div class="p-6 space-y-8 bg-gray-900 text-white rounded-lg shadow-lg">
    <h1 class="text-2xl font-bold">🧪 Model Training</h1>

    <!-- 🧩 Modular Sections -->
    <div class="flex flex-col md:flex-row gap-4">
      <UploadDataset
        label="📁 Upload Dataset (zip dataset file)"
        accept=".zip"
        :multiple="false"
        @files-selected="handleDatasetFiles"
        class="md:w-full"
      />
    </div>

    <!-- Components -->
    <DatasetBrowser />
    <ModelConfig />
    <TrainingLogs />
    <TrainingProgress />
    <TrainingControls />
    <TestModel />
  </div>
</template>

<script setup>
import UploadLoadingModal from '../components/UploadLoadingModal.vue'
import UploadDataset from '../components/UploadDataset.vue'
import DatasetBrowser from '../components/DatasetBrowser.vue'
import ModelConfig from '../components/ModelConfig.vue'
import TrainingLogs from '../components/TrainingLogs.vue'
import TrainingProgress from '../components/TrainingProgress.vue'
import TrainingControls from '../components/TrainingControls.vue'
import TestModel from '../components/TestModel.vue'

// Import shared composable
import useTraining from '../scripts/training-script.js'

// Only need dataset upload handler from global composable now
const { handleDatasetFiles, uploading, uploadProgress } = useTraining()
</script>

<style scoped>
/* Shared UI consistency */
.input {
  @apply p-2 rounded bg-gray-800 text-white border border-gray-600;
}

.input-disabled {
  @apply input opacity-60 cursor-not-allowed;
}

.btn-primary {
  @apply px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded text-white text-sm font-semibold;
}

.btn-secondary {
  @apply px-4 py-2 bg-gray-700 hover:bg-gray-800 rounded text-white text-sm font-semibold;
}
</style>
