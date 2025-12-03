<template>
  <div class="flex flex-col md:flex-row gap-6">
    <!-- ⚙️ Model Config -->
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
          <input
            v-model.number="config.split"
            type="number"
            min="50"
            max="90"
            class="input"
          />
        </FormField>
      </div>
    </div>

    <!-- 🛠️ Advanced Options -->
    <div class="md:w-1/2 space-y-4">
      <div class="flex justify-between items-center">
        <h2 class="text-xl font-semibold">🛠️ Additional Options</h2>
        <label class="flex items-center space-x-2 text-sm text-gray-300">
          <span>Show</span>
          <input
            type="checkbox"
            v-model="showAdvancedConfig"
            class="form-checkbox text-blue-500"
          />
        </label>
      </div>

      <transition name="fade">
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
      </transition>
    </div>
  </div>
</template>

<script setup>
import useTraining from '../scripts/training-script.js'
import FormField from './FormField.vue' // ✅ Small reusable input wrapper (optional)

const { config, showAdvancedConfig } = useTraining()
</script>

<style scoped>
.input {
  @apply w-full px-3 py-2 rounded bg-gray-800 border border-gray-700 text-white text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none;
}

.input-disabled {
  @apply w-full px-3 py-2 rounded bg-gray-700 border border-gray-600 text-gray-400 text-sm cursor-not-allowed;
}

.form-checkbox {
  @apply h-4 w-4 text-blue-500 rounded border-gray-600 bg-gray-800 focus:ring-blue-500;
}

/* Fade animation for smooth show/hide */
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>
