<template>
  <div class="p-6 space-y-6 bg-gray-900 text-white rounded-lg shadow-lg">
    <h1 class="text-2xl font-bold">🧪 Standalone Model Testing</h1>

    <!-- Upload Area -->
    <div
      class="border-2 border-dashed border-gray-500 p-6 rounded text-center cursor-pointer transition"
      :class="{ 'bg-gray-800 border-blue-500': isDragging }"
      @dragover.prevent
      @dragenter.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="handleDrop"
      @click="triggerFileInput"
    >
      <p class="text-gray-400">Drag & drop test images here, or click to upload</p>

      <input
        ref="fileInput"
        type="file"
        accept="image/*"
        multiple
        class="hidden"
        @change="handleFileSelect"
      />
    </div>

    <!-- Preview thumbnails -->
    <div v-if="previews.length" class="grid grid-cols-3 md:grid-cols-6 gap-3 mt-4">
      <div
        v-for="(img, index) in previews"
        :key="index"
        class="p-2 bg-gray-800 rounded border border-gray-700"
      >
        <img :src="img" class="w-full h-24 object-cover rounded" />
      </div>
    </div>

    <!-- Evaluate Button -->
    <button
      v-if="selectedFiles.length"
      @click="testModel"
      class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded mt-3"
    >
      🔍 Evaluate Selected Images
    </button>

    <!-- Results -->
    <div v-if="testResults.length" class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
      <div
        v-for="(result, idx) in testResults"
        :key="idx"
        class="p-2 border border-gray-700 rounded bg-gray-900"
      >
        <img
          :src="result.preview"
          class="w-full h-32 object-cover rounded mb-2 border border-gray-700"
        />
        <p class="text-sm text-green-400 font-mono truncate">
          {{ result.asin }}
        </p>
        <p class="text-xs text-gray-400">
          {{ (result.confidence * 100).toFixed(2) }}%
        </p>
      </div>
    </div>
    <!-- Summary Block -->
    <div v-if="summary.totalImages" class="mt-6 p-4 bg-gray-800 rounded border border-gray-700">
      <h3 class="text-lg font-semibold">📊 Summary</h3>

      <p><strong>Total Images:</strong> {{ summary.totalImages }}</p>
      <p><strong>Majority Class:</strong> {{ summary.predictedClass }}</p>
      <p><strong>Average Confidence:</strong> {{ (summary.avgConfidence * 100).toFixed(2) }}%</p>

      <div class="mt-3">
        <span v-if="summary.isKnown" class="px-3 py-1 bg-green-700 rounded text-sm">
          🟢 Existing ASIN Class
        </span>

        <span v-else class="px-3 py-1 bg-red-700 rounded text-sm">
          🔴 Unknown SET — Not in dataset, needs training
        </span>
      </div>

      <!-- Final ASIN Info -->
      <div v-if="summary.asinInfo" class="mt-4 p-3 bg-gray-700 rounded">
        <h4 class="font-semibold text-yellow-300">📦 ASIN Details</h4>

        <p><strong>ASIN List:</strong></p>

        <ul class="ml-4 list-disc text-gray-200">
          <li v-for="(asin, index) in summary.asinInfo.asins" :key="index">
            {{ asin }}
          </li>
        </ul>

        <p class="mt-2"><strong>Title:</strong> {{ summary.asinInfo.title || 'N/A' }}</p>
        <p><strong>Brand:</strong> {{ summary.asinInfo.brand || 'N/A' }}</p>
      </div>
      
    </div>

  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'

const fileInput = ref(null)
const isDragging = ref(false)
const selectedFiles = ref([])
const previews = ref([])
const testResults = ref([])

const summary = ref({
  avgConfidence: 0,
  predictedClass: null,
  totalImages: 0,
  isKnown: false,
  asinInfo: null
})

// const SITE_URL = window.location.origin.includes('localhost')
//   ? 'http://localhost:8001'
//   : 'https://test.techniquyality.com'

const triggerFileInput = () => fileInput.value?.click()

function handleFileSelect(e) {
  loadFiles(e.target.files)
}

function handleDrop(e) {
  isDragging.value = false
  loadFiles(e.dataTransfer.files)
}

function loadFiles(fileList) {
  selectedFiles.value = Array.from(fileList)
  previews.value = []

  for (const file of selectedFiles.value) {
    const reader = new FileReader()
    reader.onload = (e) => previews.value.push(e.target.result)
    reader.readAsDataURL(file)
  }
}

// Fetch ASIN details from Laravel API
async function findAsinInfo(predictedClass) {
  try {
    const apiURL = `${window.location.origin}/api/asin-details/${predictedClass}`
    const res = await axios.get(apiURL)
    return res.data
  } catch (err) {
    console.warn("❌ No ASIN record found for:", predictedClass)
    return null
  }
}

async function testModel() {
  testResults.value = []
  summary.value = {
    avgConfidence: 0,
    predictedClass: null,
    totalImages: selectedFiles.value.length,
    isKnown: false,
    asinInfo: null
  }

  let totalConfidence = 0
  let classVotes = {}

  for (const file of selectedFiles.value) {
    const formData = new FormData()
    formData.append('image', file)

    try {
      const res = await axios.post('/api/ai/asin-test', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })

      testResults.value.push({
        asin: res.data.asin,
        confidence: res.data.confidence,
        preview: URL.createObjectURL(file)
      })

      totalConfidence += res.data.confidence
      classVotes[res.data.asin] = (classVotes[res.data.asin] || 0) + 1

    } catch (err) {
      console.error("❌ Error testing model:", err)
    }
  }

  summary.value.avgConfidence = totalConfidence / selectedFiles.value.length

  summary.value.predictedClass = Object.entries(classVotes)
    .sort((a, b) => b[1] - a[1])[0][0]

  summary.value.isKnown = summary.value.avgConfidence >= 0.85

  if (summary.value.isKnown) {
    summary.value.asinInfo = await findAsinInfo(summary.value.predictedClass)
  } else {
    summary.value.asinInfo = null
  }
}
</script>

