<template>
  <div class="flex justify-end mt-6">
    <!-- 🚀 Start / ❌ Cancel -->
    <button
      v-if="!status.finished"
      @click="trainingActive ? cancelTraining() : startTraining()"
      class="px-4 py-2 rounded text-white text-sm font-semibold transition-all"
      :class="trainingActive ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'"
    >
      {{ trainingActive ? '❌ Cancel Training' : '🚀 Start Training' }}
    </button>

    <!-- 🧠 Update / 🔁 Retrain -->
    <div v-else class="mt-4 gap-2 flex">
      <button @click="updateModel" class="btn-primary">🧠 Update Model</button>
      <button @click="retrainModel" class="btn-secondary">🔁 Retrain</button>
    </div>
  </div>
</template>

<script setup>
import useTraining from '../scripts/training-script.js'
import { ref } from 'vue'

const { startTraining, cancelTraining, updateModel, retrainModel, trainingActive, status } = useTraining()

</script>

<style scoped>
.btn-primary {
  @apply px-4 py-2 bg-green-600 hover:bg-green-700 rounded text-white text-sm font-semibold transition-all;
}
.btn-secondary {
  @apply px-4 py-2 bg-gray-600 hover:bg-gray-700 rounded text-white text-sm font-semibold transition-all;
}
</style>
