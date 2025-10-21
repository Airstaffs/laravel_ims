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

    <!-- Configuration Section -->
    <div class="flex flex-col md:flex-row gap-6">
      <!-- 3. Model Config -->
      <div class="md:w-1/2 space-y-4">
        <h2 class="text-xl font-semibold">⚙️ Model Configuration</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <FormField label="Model Type (Fixed):">
            <input v-model="config.modelType" readonly class="input-disabled" />
          </FormField>
          <FormField label="Epochs:">
            <input v-model.number="config.epochs" type="number" class="input" />
          </FormField>
          <FormField label="Train/Val Split (%):">
            <input v-model.number="config.split" type="number" min="50" max="90" class="input" />
          </FormField>
        </div>
      </div>

      <!-- 4. Advanced Options -->
      <div class="md:w-1/2 space-y-4">
        <div class="flex justify-between items-center">
          <h2 class="text-xl font-semibold">🛠️ Additional Options</h2>
          <label class="flex items-center space-x-2 text-sm text-gray-300">
            <span>Show</span>
            <input type="checkbox" v-model="showAdvancedConfig" class="form-checkbox text-blue-500" />
          </label>
        </div>

        <div v-if="showAdvancedConfig" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <FormField label="Model Name:">
            <input v-model="config.modelName" placeholder="asin_model" class="input" />
          </FormField>
          <FormField label="Auto Replace Current Model:">
            <input type="checkbox" v-model="config.autoReplace" class="form-checkbox" />
          </FormField>
          <FormField label="Use GPU if Available:">
            <input type="checkbox" v-model="config.useGPU" class="form-checkbox" />
          </FormField>
        </div>
      </div>
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
import { defineComponent } from 'vue'
import UploadDataset from './components/UploadDataset.vue'
import useTraining from './training-script.js'

// Pull in upload logic
const { logs, uploadProgress, uploading, handleDatasetFiles, config, showAdvancedConfig } = useTraining()

// ✅ Define FormField inline
const FormField = defineComponent({
  name: 'FormField',
  props: { label: String },
  template: `
    <div class="flex flex-col">
      <label class="text-sm text-gray-400 mb-1">{{ label }}</label>
      <slot></slot>
    </div>
  `
})
</script>

<style scoped>
.btn-primary {
  @apply px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded;
}
.input {
  @apply p-2 rounded bg-gray-800 text-white border border-gray-600;
}
.input-disabled {
  @apply input opacity-60 cursor-not-allowed;
}
</style>
