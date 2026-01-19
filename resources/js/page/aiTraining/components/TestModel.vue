<template>
  <div v-if="status.finished" class="space-y-4">
    <h2 class="text-xl font-semibold">🧠 Evaluate / Test Model</h2>

    <!-- Drag-and-drop test upload -->
    <div
      class="border-2 border-dashed border-gray-400 p-6 rounded text-center cursor-pointer transition hover:border-blue-500"
      @dragover.prevent
      @dragenter.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="handleDrop"
      @click="triggerFileInput"
      :class="{ 'bg-blue-50': isDragging }"
    >
      <p class="text-gray-600">
        Drag & drop one or multiple test images, or click to browse
      </p>

      <input
        ref="fileInput"
        type="file"
        accept="image/*"
        multiple
        class="hidden"
        @change="handleFileSelect"
      />
    </div>

    <!-- Evaluate button -->
    <button
      v-if="selectedFiles.length"
      @click="testModel"
      class="btn-primary mt-3"
      :disabled="testing"
    >
      🔍 {{ testing ? 'Testing...' : 'Evaluate Selected Images' }}
    </button>

    <!-- Result list -->
    <div
      v-if="testResults.length"
      class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4"
    >
      <div
        v-for="(result, idx) in testResults"
        :key="idx"
        class="p-2 border border-gray-700 rounded bg-gray-900"
      >
        <img
          :src="result.preview"
          class="w-full h-32 object-cover rounded mb-2 border border-gray-700"
        />

        <p
          class="text-sm font-mono truncate"
          :class="result.error ? 'text-red-400' : 'text-green-400'"
        >
          {{ result.asin }}
        </p>

        <p class="text-xs text-gray-400">
          {{ result.error ? 'Failed' : (result.confidence * 100).toFixed(2) + '%' }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import useTraining from '../scripts/training-script.js'

const { status } = useTraining()

const fileInput = ref(null)
const isDragging = ref(false)
const selectedFiles = ref([])
const testResults = ref([])
const testing = ref(false)

function triggerFileInput() {
  fileInput.value?.click()
}

function handleFileSelect(e) {
  selectedFiles.value = Array.from(e.target.files)
}

function handleDrop(e) {
  selectedFiles.value = Array.from(e.dataTransfer.files)
  isDragging.value = false
}

async function testModel() {
  if (!selectedFiles.value.length) return

  testing.value = true
  testResults.value = []

  for (const file of selectedFiles.value) {
    const formData = new FormData()
    formData.append('image', file)

    try {
      // ✅ CALL LARAVEL — NOT PYTHON DIRECTLY
      const res = await axios.post('/api/test-model', formData)

      testResults.value.push({
        asin: res.data.asin,
        confidence: res.data.confidence,
        preview: URL.createObjectURL(file),
        error: false,
      })
    } catch (err) {
      console.error('❌ Error testing model:', err)

      testResults.value.push({
        asin: 'ERROR',
        confidence: 0,
        preview: URL.createObjectURL(file),
        error: true,
      })
    }
  }

  testing.value = false
}
</script>

<style scoped>
.btn-primary {
  @apply px-4 py-2 bg-green-600 hover:bg-green-700 rounded text-white text-sm font-semibold disabled:opacity-50;
}
</style>
